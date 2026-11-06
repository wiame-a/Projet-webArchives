import { useEffect, useState } from 'react'
import { usersApi } from '../api/services'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { parseApiError } from '../utils/errors'
import { formatDate } from '../utils/format'
import ConfirmModal from '../components/ConfirmModal'
import ErrorAlert from '../components/ErrorAlert'
import Pagination from '../components/Pagination'
import Spinner from '../components/Spinner'

export default function Users() {
  const { user: me } = useAuth()
  const notify = useToast()
  const [query, setQuery] = useState('')
  const [applied, setApplied] = useState({ q: '', page: 1 })
  const [state, setState] = useState({ items: [], meta: null })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [reloadKey, setReloadKey] = useState(0)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let active = true
    setLoading(true)
    usersApi
      .list({ q: applied.q || undefined, page: applied.page })
      .then((res) => {
        if (!active) return
        setState({ items: res.data.data, meta: res.data.meta })
        setError('')
      })
      .catch((err) => active && setError(parseApiError(err).message))
      .finally(() => active && setLoading(false))
    return () => {
      active = false
    }
  }, [applied, reloadKey])

  const changeRole = async (target, role) => {
    try {
      await usersApi.update(target.id, { role })
      notify(`Rôle de ${target.name} : ${role === 'admin' ? 'administrateur' : 'utilisateur'}.`)
      setReloadKey((k) => k + 1)
    } catch (err) {
      notify(parseApiError(err).message, 'error')
    }
  }

  const remove = async () => {
    setBusy(true)
    try {
      await usersApi.remove(deleting.id)
      notify('Utilisateur supprimé.')
      setDeleting(null)
      setReloadKey((k) => k + 1)
    } catch (err) {
      notify(parseApiError(err).message, 'error')
      setDeleting(null)
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Utilisateurs</h1>
          <p className="page-sub">Gérez les comptes et leurs rôles.</p>
        </div>
      </div>

      <form
        className="panel panel-body mb-3 d-flex gap-2"
        onSubmit={(e) => {
          e.preventDefault()
          setApplied({ q: query.trim(), page: 1 })
        }}
      >
        <input className="form-control" placeholder="Rechercher par nom ou email…" value={query} onChange={(e) => setQuery(e.target.value)} aria-label="Rechercher un utilisateur" />
        <button type="submit" className="btn btn-primary"><i className="bi bi-search" /></button>
      </form>

      <ErrorAlert message={error} onRetry={() => setReloadKey((k) => k + 1)} />

      <div className="panel">
        {loading ? (
          <Spinner />
        ) : (
          <div className="table-responsive p-2 p-md-3">
            <table className="table table-docs align-middle">
              <thead>
                <tr><th>Nom</th><th>Email</th><th>Rôle</th><th className="text-center">Documents</th><th>Inscrit le</th><th className="text-end">Actions</th></tr>
              </thead>
              <tbody>
                {state.items.map((u) => {
                  const self = u.id === me.id
                  return (
                    <tr key={u.id}>
                      <td className="fw-semibold">{u.name}{self && <span className="badge text-bg-light border ms-2">vous</span>}</td>
                      <td>{u.email}</td>
                      <td style={{ minWidth: 150 }}>
                        <select
                          className="form-select form-select-sm"
                          value={u.role}
                          disabled={self}
                          title={self ? 'Vous ne pouvez pas modifier votre propre rôle' : undefined}
                          aria-label={`Rôle de ${u.name}`}
                          onChange={(e) => changeRole(u, e.target.value)}
                        >
                          <option value="user">Utilisateur</option>
                          <option value="admin">Administrateur</option>
                        </select>
                      </td>
                      <td className="text-center"><span className="badge text-bg-light border">{u.documents_count}</span></td>
                      <td className="text-nowrap">{formatDate(u.created_at)}</td>
                      <td className="text-end">
                        <button type="button" className="btn btn-outline-danger icon-btn" disabled={self} title="Supprimer" onClick={() => setDeleting(u)}>
                          <i className="bi bi-trash" />
                        </button>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
            {state.items.length === 0 && <div className="empty-state"><p className="mb-0">Aucun utilisateur trouvé.</p></div>}
          </div>
        )}
      </div>

      {!loading && <Pagination meta={state.meta} onPage={(page) => setApplied((a) => ({ ...a, page }))} />}

      {deleting && (
        <ConfirmModal
          title="Supprimer l'utilisateur"
          message={<>Supprimer le compte de <strong>{deleting.name}</strong> ? Impossible s'il possède encore des documents.</>}
          confirmLabel="Supprimer"
          danger
          busy={busy}
          onConfirm={remove}
          onCancel={() => setDeleting(null)}
        />
      )}
    </>
  )
}
