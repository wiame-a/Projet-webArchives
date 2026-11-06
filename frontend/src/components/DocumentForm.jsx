import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { categoriesApi, documentsApi } from '../api/services'
import { parseApiError } from '../utils/errors'
import { validateFile } from '../utils/format'
import usePageTitle from '../utils/usePageTitle'
import { useToast } from '../context/ToastContext'
import ErrorAlert from './ErrorAlert'
import FileDropzone from './FileDropzone'
import Spinner from './Spinner'

/**
 * Formulaire partagé création / modification.
 *  - création : envoi en multipart/form-data (FormData) avec le fichier
 *  - modification : métadonnées seulement (le fichier se change via "Nouvelle version")
 */
export default function DocumentForm({ document: existing = null }) {
  const isEdit = Boolean(existing)
  const navigate = useNavigate()
  const notify = useToast()
  usePageTitle(isEdit ? 'Modifier le document' : 'Nouveau document')

  const [categories, setCategories] = useState([])
  const [loadingCats, setLoadingCats] = useState(true)
  const [values, setValues] = useState({
    titre: existing?.titre ?? '',
    description: existing?.description ?? '',
    mots_cles: existing?.mots_cles ?? '',
    category_id: existing?.category?.id ?? '',
  })
  const [file, setFile] = useState(null)
  const [fieldErrors, setFieldErrors] = useState({})
  const [error, setError] = useState('')
  const [progress, setProgress] = useState(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    categoriesApi
      .list()
      .then((res) => setCategories(res.data.data))
      .catch((err) => setError(parseApiError(err).message))
      .finally(() => setLoadingCats(false))
  }, [])

  const set = (name) => (e) => {
    const value = e.target.value
    setValues((v) => ({ ...v, [name]: value }))
    // L'erreur d'un champ disparaît dès que l'utilisateur le corrige.
    setFieldErrors((errs) => (errs[name] ? { ...errs, [name]: undefined } : errs))
  }

  const onFile = (chosen) => {
    setFile(chosen)
    setFieldErrors((errs) => ({ ...errs, fichier: chosen ? validateFile(chosen) : undefined }))
  }

  const submit = async (e) => {
    e.preventDefault()
    setError('')

    // Contrôles rapides côté client (le serveur revalide tout).
    const errs = {}
    if (!values.titre.trim()) errs.titre = 'Le titre est obligatoire.'
    if (!values.category_id) errs.category_id = 'Choisissez une catégorie.'
    if (!isEdit) {
      const fileError = validateFile(file)
      if (fileError) errs.fichier = fileError
    }
    setFieldErrors(errs)
    if (Object.keys(errs).length > 0) return

    setSaving(true)
    try {
      if (isEdit) {
        await documentsApi.update(existing.id, {
          titre: values.titre.trim(),
          description: values.description,
          mots_cles: values.mots_cles,
          category_id: values.category_id,
        })
        notify('Document modifié.')
        navigate(`/documents/${existing.id}`)
      } else {
        const data = new FormData()
        data.append('titre', values.titre.trim())
        data.append('description', values.description)
        data.append('mots_cles', values.mots_cles)
        data.append('category_id', values.category_id)
        data.append('fichier', file)
        setProgress(0)
        const res = await documentsApi.create(data, (evt) => {
          if (evt.total) setProgress(Math.round((evt.loaded * 100) / evt.total))
        })
        notify('Document créé (version 1).')
        navigate(`/documents/${res.data.data.id}`)
      }
    } catch (err) {
      const parsed = parseApiError(err)
      setError(parsed.message)
      setFieldErrors(parsed.fields)
      setSaving(false)
      setProgress(null)
    }
  }

  if (loadingCats) return <Spinner />

  const invalid = (name) => (fieldErrors[name] ? 'is-invalid' : '')

  return (
    <form onSubmit={submit} noValidate className="panel panel-body">
      <ErrorAlert message={error} />

      <div className="row g-3">
        <div className="col-12">
          <label className="form-label fw-semibold" htmlFor="titre">Titre *</label>
          <input id="titre" className={`form-control ${invalid('titre')}`} value={values.titre} onChange={set('titre')} maxLength={255} />
          <div className="invalid-feedback">{fieldErrors.titre}</div>
        </div>

        <div className="col-md-6">
          <label className="form-label fw-semibold" htmlFor="category_id">Catégorie *</label>
          <select id="category_id" className={`form-select ${invalid('category_id')}`} value={values.category_id} onChange={set('category_id')}>
            <option value="">— Choisir —</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{c.nom}</option>
            ))}
          </select>
          <div className="invalid-feedback">{fieldErrors.category_id}</div>
          {categories.length === 0 && (
            <div className="form-text text-danger">Aucune catégorie : demandez à un administrateur d'en créer.</div>
          )}
        </div>

        <div className="col-md-6">
          <label className="form-label fw-semibold" htmlFor="mots_cles">Mots-clés (indexation)</label>
          <input
            id="mots_cles"
            className={`form-control ${invalid('mots_cles')}`}
            placeholder="contrat, 2026, fournisseur"
            value={values.mots_cles}
            onChange={set('mots_cles')}
            maxLength={255}
          />
          <div className="invalid-feedback">{fieldErrors.mots_cles}</div>
          <div className="form-text">Séparés par des virgules. Utilisés par la recherche.</div>
        </div>

        <div className="col-12">
          <label className="form-label fw-semibold" htmlFor="description">Description</label>
          <textarea id="description" rows={3} className={`form-control ${invalid('description')}`} value={values.description} onChange={set('description')} />
          <div className="invalid-feedback">{fieldErrors.description}</div>
        </div>

        {!isEdit && (
          <div className="col-12">
            <label className="form-label fw-semibold" htmlFor="fichier">Fichier *</label>
            <FileDropzone id="fichier" file={file} onFile={onFile} error={fieldErrors.fichier} disabled={saving} progress={progress} />
          </div>
        )}

        <div className="col-12 d-flex justify-content-end gap-2 pt-2">
          <button type="button" className="btn btn-light" onClick={() => navigate(-1)} disabled={saving}>
            Annuler
          </button>
          <button type="submit" className="btn btn-primary" disabled={saving}>
            {saving && <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />}
            {isEdit ? 'Enregistrer' : 'Créer le document'}
          </button>
        </div>
      </div>
    </form>
  )
}
