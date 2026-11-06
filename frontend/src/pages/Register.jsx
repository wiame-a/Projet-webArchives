import { useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { parseApiError } from '../utils/errors'
import ErrorAlert from '../components/ErrorAlert'
import usePageTitle from '../utils/usePageTitle'
import AuthShell from './AuthShell'

export default function Register() {
  usePageTitle('Créer un compte')
  const { user, register } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' })
  const [error, setError] = useState('')
  const [fields, setFields] = useState({})
  const [busy, setBusy] = useState(false)

  if (user) return <Navigate to="/" replace />

  const set = (name) => (e) => setForm((f) => ({ ...f, [name]: e.target.value }))

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    const local = {}
    if (!form.name.trim()) local.name = 'Le nom est obligatoire.'
    if (form.password.length < 8) local.password = 'Le mot de passe doit contenir au moins 8 caractères.'
    if (form.password !== form.password_confirmation) local.password_confirmation = 'La confirmation ne correspond pas.'
    setFields(local)
    if (Object.keys(local).length) return

    setBusy(true)
    try {
      await register(form)
      navigate('/', { replace: true })
    } catch (err) {
      const parsed = parseApiError(err)
      setError(parsed.message)
      setFields(parsed.fields)
      setBusy(false)
    }
  }

  const input = (name, label, type = 'text', extra = {}) => (
    <div className="mb-3">
      <label className="form-label fw-semibold" htmlFor={name}>{label}</label>
      <input id={name} type={type} className={`form-control ${fields[name] ? 'is-invalid' : ''}`} value={form[name]} onChange={set(name)} {...extra} />
      <div className="invalid-feedback">{fields[name]}</div>
    </div>
  )

  return (
    <AuthShell title="Créer un compte" subtitle="Rejoignez Archivium en quelques secondes.">
      <ErrorAlert message={error} />
      <form onSubmit={submit} noValidate>
        {input('name', 'Nom complet', 'text', { autoComplete: 'name', autoFocus: true })}
        {input('email', 'Adresse email', 'email', { autoComplete: 'username' })}
        {input('password', 'Mot de passe (8 caractères minimum)', 'password', { autoComplete: 'new-password' })}
        {input('password_confirmation', 'Confirmer le mot de passe', 'password', { autoComplete: 'new-password' })}
        <button type="submit" className="btn btn-primary w-100 mt-2" disabled={busy}>
          {busy && <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />}
          Créer mon compte
        </button>
      </form>
      <p className="text-center text-muted mt-4 mb-0">
        Déjà inscrit ? <Link to="/login">Se connecter</Link>
      </p>
    </AuthShell>
  )
}
