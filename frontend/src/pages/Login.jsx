import { useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { parseApiError } from '../utils/errors'
import ErrorAlert from '../components/ErrorAlert'
import usePageTitle from '../utils/usePageTitle'
import AuthShell from './AuthShell'

export default function Login() {
  usePageTitle('Connexion')
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [fields, setFields] = useState({})
  const [busy, setBusy] = useState(false)

  const from = location.state?.from?.pathname ?? '/'
  if (user) return <Navigate to={from} replace />

  const set = (name) => (e) => setForm((f) => ({ ...f, [name]: e.target.value }))

  const submit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError('')
    setFields({})
    try {
      await login(form)
      navigate(from, { replace: true })
    } catch (err) {
      const parsed = parseApiError(err)
      setError(parsed.message)
      setFields(parsed.fields)
      setBusy(false)
    }
  }

  return (
    <AuthShell title="Connexion" subtitle="Accédez à vos documents.">
      <ErrorAlert message={error} />
      <form onSubmit={submit} noValidate>
        <div className="mb-3">
          <label className="form-label fw-semibold" htmlFor="email">Adresse email</label>
          <input id="email" type="email" autoComplete="username" className={`form-control ${fields.email ? 'is-invalid' : ''}`} value={form.email} onChange={set('email')} required autoFocus />
          <div className="invalid-feedback">{fields.email}</div>
        </div>
        <div className="mb-4">
          <label className="form-label fw-semibold" htmlFor="password">Mot de passe</label>
          <input id="password" type="password" autoComplete="current-password" className={`form-control ${fields.password ? 'is-invalid' : ''}`} value={form.password} onChange={set('password')} required />
          <div className="invalid-feedback">{fields.password}</div>
        </div>
        <button type="submit" className="btn btn-primary w-100" disabled={busy}>
          {busy && <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />}
          Se connecter
        </button>
      </form>
      <p className="text-center text-muted mt-4 mb-0">
        Pas encore de compte ? <Link to="/register">Créer un compte</Link>
      </p>
    </AuthShell>
  )
}
