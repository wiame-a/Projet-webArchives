import { useEffect, useRef, useState } from 'react'
import axios from 'axios'
import { usersApi } from '../api/services'

/**
 * Recherche d'utilisateur par nom ou email (min. 2 caractères, via /users/lookup).
 * Utilisé par la boîte de partage et par le filtre "auteur" de la recherche.
 */
export default function UserPicker({ onSelect, excludeIds = [], placeholder = 'Nom ou email…', disabled = false }) {
  const [term, setTerm] = useState('')
  const [results, setResults] = useState([])
  const [open, setOpen] = useState(false)
  const [searching, setSearching] = useState(false)
  const box = useRef(null)
  const excluded = excludeIds.join(',')

  useEffect(() => {
    if (term.trim().length < 2) return undefined
    const ctrl = new AbortController()
    const timer = setTimeout(() => {
      setSearching(true)
      usersApi
        .lookup(term.trim(), ctrl.signal)
        .then((res) => {
          const skip = excluded ? excluded.split(',').map(Number) : []
          setResults(res.data.data.filter((u) => !skip.includes(u.id)))
          setOpen(true)
        })
        .catch((err) => {
          if (!axios.isCancel(err)) setResults([])
        })
        .finally(() => setSearching(false))
    }, 300)
    return () => {
      clearTimeout(timer)
      ctrl.abort()
    }
  }, [term, excluded])

  useEffect(() => {
    const onClick = (e) => box.current && !box.current.contains(e.target) && setOpen(false)
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  const choose = (user) => {
    onSelect(user)
    setTerm('')
    setResults([])
    setOpen(false)
  }

  const showList = open && term.trim().length >= 2

  return (
    <div className="position-relative" ref={box}>
      <input
        type="search"
        className="form-control"
        placeholder={placeholder}
        value={term}
        disabled={disabled}
        onChange={(e) => setTerm(e.target.value)}
        onFocus={() => results.length > 0 && setOpen(true)}
        autoComplete="off"
        aria-label="Rechercher un utilisateur"
      />
      {showList && (
        <div className="picker-list">
          {searching && <div className="px-3 py-2 text-muted small">Recherche…</div>}
          {!searching && results.length === 0 && <div className="px-3 py-2 text-muted small">Aucun utilisateur trouvé.</div>}
          {results.map((u) => (
            <button key={u.id} type="button" className="picker-item" onClick={() => choose(u)}>
              <strong>{u.name}</strong>
              <span className="d-block small text-muted">{u.email}</span>
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
