import { useState } from 'react'
import { profileApi } from '../api/services'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { parseApiError } from '../utils/errors'
import usePageTitle from '../utils/usePageTitle'

const ROLE_LABELS = { admin: 'Administrateur', archiviste: 'Archiviste', user: 'Utilisateur' }

function Field({ id, label, error, children }) {
  return (
    <div className="mb-3">
      <label className="form-label fw-semibold" htmlFor={id}>{label}</label>
      {children}
      {error && <div className="invalid-feedback d-block">{error}</div>}
    </div>
  )
}

export default function Profile() {
  const { user, updateUser } = useAuth()
  const notify = useToast()
  usePageTitle('Mon profil')

  const [info, setInfo] = useState({ name: user?.name ?? '', email: user?.email ?? '' })
  const [infoErrors, setInfoErrors] = useState({})
  const [savingInfo, setSavingInfo] = useState(false)

  const empty = { current_password: '', password: '', password_confirmation: '' }
  const [pwd, setPwd] = useState(empty)
  const [pwdErrors, setPwdErrors] = useState({})
  const [savingPwd, setSavingPwd] = useState(false)

  const saveInfo = async (e) => {
    e.preventDefault()
    setSavingInfo(true)
    setInfoErrors({})
    try {
      const res = await profileApi.update(info)
      updateUser(res.data.data)
      notify('Profil mis à jour.')
    } catch (err) {
      const { message, fields } = parseApiError(err)
      if (Object.keys(fields ?? {}).length) setInfoErrors(fields)
      else notify(message, 'error')
    } finally {
      setSavingInfo(false)
    }
  }

  const savePwd = async (e) => {
    e.preventDefault()
    setSavingPwd(true)
    setPwdErrors({})
    try {
      const res = await profileApi.changePassword(pwd)
      notify(res.data.message ?? 'Mot de passe modifié.')
      setPwd(empty)
    } catch (err) {
      const { message, fields } = parseApiError(err)
      if (Object.keys(fields ?? {}).length) setPwdErrors(fields)
      else notify(message, 'error')
    } finally {
      setSavingPwd(false)
    }
  }

  const initials = (user?.name ?? '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()

  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Mon profil</h1>
          <p className="page-sub">Gérez vos informations personnelles et votre mot de passe.</p>
        </div>
      </div>

      <div className="row g-3">
        <div className="col-lg-6">
          <section className="panel panel-body h-100">
            <div className="d-flex align-items-center gap-3 mb-3">
              <span className="profile-avatar">{initials}</span>
              <div>
                <div className="fw-semibold">{user?.name}</div>
                <span className="badge text-bg-light border fw-normal">{ROLE_LABELS[user?.role] ?? 'Utilisateur'}</span>
              </div>
            </div>
            <h2 className="panel-title"><i className="bi bi-person" /> Informations</h2>
            <form onSubmit={saveInfo} noValidate>
              <Field id="p-name" label="Nom complet" error={infoErrors.name}>
                <input id="p-name" className={`form-control ${infoErrors.name ? 'is-invalid' : ''}`} value={info.name} onChange={(e) => setInfo({ ...info, name: e.target.value })} required />
              </Field>
              <Field id="p-email" label="Adresse email" error={infoErrors.email}>
                <input id="p-email" type="email" className={`form-control ${infoErrors.email ? 'is-invalid' : ''}`} value={info.email} onChange={(e) => setInfo({ ...info, email: e.target.value })} required />
              </Field>
              <button className="btn btn-primary" disabled={savingInfo}>
                {savingInfo ? 'Enregistrement…' : 'Enregistrer'}
              </button>
            </form>
          </section>
        </div>

        <div className="col-lg-6">
          <section className="panel panel-body h-100">
            <h2 className="panel-title"><i className="bi bi-key" /> Mot de passe</h2>
            <form onSubmit={savePwd} noValidate>
              <Field id="p-current" label="Mot de passe actuel" error={pwdErrors.current_password}>
                <input id="p-current" type="password" autoComplete="current-password" className={`form-control ${pwdErrors.current_password ? 'is-invalid' : ''}`} value={pwd.current_password} onChange={(e) => setPwd({ ...pwd, current_password: e.target.value })} required />
              </Field>
              <Field id="p-new" label="Nouveau mot de passe (8 caractères minimum)" error={pwdErrors.password}>
                <input id="p-new" type="password" autoComplete="new-password" className={`form-control ${pwdErrors.password ? 'is-invalid' : ''}`} value={pwd.password} onChange={(e) => setPwd({ ...pwd, password: e.target.value })} required />
              </Field>
              <Field id="p-confirm" label="Confirmer le nouveau mot de passe">
                <input id="p-confirm" type="password" autoComplete="new-password" className="form-control" value={pwd.password_confirmation} onChange={(e) => setPwd({ ...pwd, password_confirmation: e.target.value })} required />
              </Field>
              <button className="btn btn-primary" disabled={savingPwd}>
                {savingPwd ? 'Modification…' : 'Modifier le mot de passe'}
              </button>
              <p className="text-muted small mt-3 mb-0">Vos autres sessions seront déconnectées.</p>
            </form>
          </section>
        </div>
      </div>
    </>
  )
}
