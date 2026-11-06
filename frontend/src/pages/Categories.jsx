import { useCallback, useEffect, useState } from 'react'
import { categoriesApi } from '../api/services'
import { parseApiError } from '../utils/errors'
import { useToast } from '../context/ToastContext'
import ConfirmModal from '../components/ConfirmModal'
import ErrorAlert from '../components/ErrorAlert'
import Modal from '../components/Modal'
import Spinner from '../components/Spinner'

export default function Categories() {
  const notify = useToast()
  const [items, setItems] = useState(null)
  const [error, setError] = useState('')
  const [editing, setEditing] = useState(null) // null | {} (création) | category (édition)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  const load = useCallback(async () => {
    try {
      const res = await categoriesApi.list()
      setItems(res.data.data)
      setError('')
    } catch (err) {
      setError(parseApiError(err).message)
    }
  }, [])

  useEffect(() => {
    let active = true
    categoriesApi
      .list()
      .then((res) => active && setItems(res.data.data))
      .catch((err) => active && setError(parseApiError(err).message))
    return () => {
      active = false
    }
  }, [])

  const remove = async () => {
    setBusy(true)
    try {
      await categoriesApi.remove(deleting.id)
      notify('Catégorie supprimée.')
      setDeleting(null)
      await load()
    } catch (err) {
      // 409 : la catégorie contient encore des documents -> message de l'API
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
          <h1 className="page-title">Catégories</h1>
          <p className="page-sub">Une catégorie ne peut être supprimée que si elle ne contient aucun document.</p>
        </div>
        <button type="button" className="btn btn-primary" onClick={() => setEditing({})}>
          <i className="bi bi-plus-lg me-1" />
          Nouvelle catégorie
        </button>
      </div>

      <ErrorAlert message={error} onRetry={load} />

      <div className="panel">
        {!items ? (
          !error && <Spinner />
        ) : items.length === 0 ? (
          <div className="empty-state"><i className="bi bi-tags" /><p className="mt-2 mb-0">Aucune catégorie. Créez-en une pour classer vos documents.</p></div>
        ) : (
          <div className="table-responsive p-2 p-md-3">
            <table className="table table-docs align-middle">
              <thead>
                <tr><th>Nom</th><th>Description</th><th className="text-center">Documents</th><th className="text-end">Actions</th></tr>
              </thead>
              <tbody>
                {items.map((c) => (
                  <tr key={c.id}>
                    <td className="fw-semibold">{c.nom}</td>
                    <td className="text-muted">{c.description || '—'}</td>
                    <td className="text-center"><span className="badge text-bg-light border">{c.documents_count}</span></td>
                    <td className="text-end text-nowrap">
                      <button type="button" className="btn btn-outline-primary icon-btn me-1" title="Modifier" onClick={() => setEditing(c)}>
                        <i className="bi bi-pencil" />
                      </button>
                      <button type="button" className="btn btn-outline-danger icon-btn" title="Supprimer" onClick={() => setDeleting(c)}>
                        <i className="bi bi-trash" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {editing && (
        <CategoryModal
          category={editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            load()
          }}
        />
      )}

      {deleting && (
        <ConfirmModal
          title="Supprimer la catégorie"
          message={<>Supprimer la catégorie <strong>{deleting.nom}</strong> ?</>}
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

function CategoryModal({ category, onClose, onSaved }) {
  const notify = useToast()
  const isEdit = Boolean(category.id)
  const [values, setValues] = useState({ nom: category.nom ?? '', description: category.description ?? '' })
  const [fields, setFields] = useState({})
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  const submit = async (e) => {
    e.preventDefault()
    if (!values.nom.trim()) {
      setFields({ nom: 'Le nom est obligatoire.' })
      return
    }
    setBusy(true)
    setError('')
    setFields({})
    try {
      const payload = { nom: values.nom.trim(), description: values.description }
      if (isEdit) await categoriesApi.update(category.id, payload)
      else await categoriesApi.create(payload)
      notify(isEdit ? 'Catégorie modifiée.' : 'Catégorie créée.')
      onSaved()
    } catch (err) {
      const parsed = parseApiError(err)
      setError(parsed.message)
      setFields(parsed.fields)
      setBusy(false)
    }
  }

  return (
    <Modal
      title={isEdit ? 'Modifier la catégorie' : 'Nouvelle catégorie'}
      onClose={busy ? () => {} : onClose}
      footer={
        <>
          <button type="button" className="btn btn-light" onClick={onClose} disabled={busy}>Annuler</button>
          <button type="submit" form="category-form" className="btn btn-primary" disabled={busy}>
            {busy && <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />}
            Enregistrer
          </button>
        </>
      }
    >
      <ErrorAlert message={error} />
      <form id="category-form" onSubmit={submit} noValidate>
        <div className="mb-3">
          <label className="form-label fw-semibold" htmlFor="cat-nom">Nom *</label>
          <input id="cat-nom" className={`form-control ${fields.nom ? 'is-invalid' : ''}`} value={values.nom} onChange={(e) => setValues((v) => ({ ...v, nom: e.target.value }))} autoFocus />
          <div className="invalid-feedback">{fields.nom}</div>
        </div>
        <div>
          <label className="form-label fw-semibold" htmlFor="cat-desc">Description</label>
          <textarea id="cat-desc" rows={3} className={`form-control ${fields.description ? 'is-invalid' : ''}`} value={values.description} onChange={(e) => setValues((v) => ({ ...v, description: e.target.value }))} />
          <div className="invalid-feedback">{fields.description}</div>
        </div>
      </form>
    </Modal>
  )
}
