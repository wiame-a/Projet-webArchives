import { useState } from 'react'
import { documentsApi } from '../api/services'
import { parseApiError } from '../utils/errors'
import { useToast } from '../context/ToastContext'
import ErrorAlert from './ErrorAlert'
import Modal from './Modal'
import UserPicker from './UserPicker'

/** Boîte de partage : accorder, modifier ou retirer un droit (lecture / modification). */
export default function ShareModal({ document, onClose }) {
  const notify = useToast()
  const [permissions, setPermissions] = useState(document.permissions ?? [])
  const [selected, setSelected] = useState(null)
  const [droit, setDroit] = useState('lecture')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [changed, setChanged] = useState(false)

  const upsert = (permission) =>
    setPermissions((list) =>
      list.some((p) => p.user_id === permission.user_id)
        ? list.map((p) => (p.user_id === permission.user_id ? permission : p))
        : [...list, permission],
    )

  const share = async (userId, right) => {
    setBusy(true)
    setError('')
    try {
      const res = await documentsApi.share(document.id, { user_id: userId, droit: right })
      upsert(res.data.data)
      setChanged(true)
      return true
    } catch (err) {
      const parsed = parseApiError(err)
      setError(parsed.fields.user_id ?? parsed.fields.droit ?? parsed.message)
      return false
    } finally {
      setBusy(false)
    }
  }

  const add = async () => {
    if (!selected) return
    if (await share(selected.id, droit)) {
      notify(`Document partagé avec ${selected.name}.`)
      setSelected(null)
    }
  }

  const remove = async (permission) => {
    setBusy(true)
    setError('')
    try {
      await documentsApi.unshare(document.id, permission.user_id)
      setPermissions((list) => list.filter((p) => p.user_id !== permission.user_id))
      setChanged(true)
      notify('Partage retiré.')
    } catch (err) {
      setError(parseApiError(err).message)
    } finally {
      setBusy(false)
    }
  }

  const close = () => onClose(changed)

  return (
    <Modal
      title={`Partager « ${document.titre} »`}
      onClose={close}
      footer={
        <button type="button" className="btn btn-primary" onClick={close}>
          Terminer
        </button>
      }
    >
      <ErrorAlert message={error} />

      <p className="small text-muted">
        <strong>Lecture</strong> : consulter et télécharger. <strong>Modification</strong> : en plus, modifier les informations
        et ajouter des versions. Seul le propriétaire peut supprimer, archiver ou partager.
      </p>

      <div className="row g-2 align-items-start mb-4">
        <div className="col-sm-6">
          {selected ? (
            <div className="d-flex align-items-center justify-content-between border rounded px-3 py-2">
              <span>
                <strong>{selected.name}</strong>
                <small className="d-block text-muted">{selected.email}</small>
              </span>
              <button type="button" className="btn-close" aria-label="Retirer" onClick={() => setSelected(null)} />
            </div>
          ) : (
            <UserPicker excludeIds={permissions.map((p) => p.user_id)} onSelect={setSelected} />
          )}
        </div>
        <div className="col-sm-3">
          <select className="form-select" value={droit} onChange={(e) => setDroit(e.target.value)} aria-label="Droit">
            <option value="lecture">Lecture</option>
            <option value="modification">Modification</option>
          </select>
        </div>
        <div className="col-sm-3 d-grid">
          <button type="button" className="btn btn-primary" disabled={!selected || busy} onClick={add}>
            Partager
          </button>
        </div>
      </div>

      <h6 className="fw-bold">Personnes ayant accès</h6>
      {permissions.length === 0 ? (
        <p className="text-muted small mb-0">Ce document n'est partagé avec personne.</p>
      ) : (
        <ul className="list-group list-group-flush border rounded-3">
          {permissions.map((p) => (
            <li key={p.user_id} className="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
              <span className="share-person">
                <span className="share-avatar">{(p.user?.name ?? '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()}</span>
                <span>
                  <strong>{p.user?.name}</strong>
                  <small className="d-block text-muted">{p.user?.email}</small>
                </span>
              </span>
              <span className="d-flex gap-2">
                <select
                  className="form-select form-select-sm"
                  value={p.droit}
                  disabled={busy}
                  aria-label={`Droit de ${p.user?.name}`}
                  onChange={(e) => share(p.user_id, e.target.value)}
                >
                  <option value="lecture">Lecture</option>
                  <option value="modification">Modification</option>
                </select>
                <button type="button" className="btn btn-sm btn-outline-danger" disabled={busy} onClick={() => remove(p)} title="Retirer l'accès">
                  <i className="bi bi-x-lg" />
                </button>
              </span>
            </li>
          ))}
        </ul>
      )}
    </Modal>
  )
}
