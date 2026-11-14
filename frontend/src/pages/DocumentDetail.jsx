import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { activityApi, documentsApi } from '../api/services'
import { downloadVersion } from '../utils/download'
import { parseApiError } from '../utils/errors'
import { fileIcon, formatBytes, formatDate, splitKeywords, validateFile } from '../utils/format'
import { useToast } from '../context/ToastContext'
import ActivityFeed from '../components/ActivityFeed'
import ConfirmModal from '../components/ConfirmModal'
import ErrorAlert from '../components/ErrorAlert'
import FileDropzone from '../components/FileDropzone'
import RejectModal from '../components/RejectModal'
import ShareModal from '../components/ShareModal'
import Spinner from '../components/Spinner'
import { RightBadge, StatusBadge } from '../components/StatusBadge'
import usePageTitle from '../utils/usePageTitle'

export default function DocumentDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const notify = useToast()

  const [doc, setDoc] = useState(null)
  const [error, setError] = useState('')
  const [confirm, setConfirm] = useState(null) // 'delete' | 'archive' | 'restore'
  const [busy, setBusy] = useState(false)
  const [sharing, setSharing] = useState(false)
  const [activity, setActivity] = useState([])
  const [rejecting, setRejecting] = useState(false)
  const [rejectError, setRejectError] = useState('')
  usePageTitle(doc?.titre ?? 'Document')

  const load = useCallback(async () => {
    try {
      const res = await documentsApi.get(id)
      setDoc(res.data.data)
      setError('')
    } catch (err) {
      setError(parseApiError(err).message)
    }
  }, [id])

  // Historique du document (propriétaire, archivistes, administrateurs).
  const canSeeActivity = Boolean(doc?.can?.activity)
  useEffect(() => {
    if (!canSeeActivity) return undefined
    let active = true
    activityApi
      .forDocument(id)
      .then((res) => active && setActivity(res.data.data))
      .catch(() => active && setActivity([]))
    return () => {
      active = false
    }
  }, [id, canSeeActivity, doc?.updated_at, doc?.validation, doc?.versions?.length])

  useEffect(() => {
    let active = true
    documentsApi
      .get(id)
      .then((res) => active && (setDoc(res.data.data), setError('')))
      .catch((err) => active && setError(parseApiError(err).message))
    return () => {
      active = false
    }
  }, [id])

  const download = async (version) => {
    try {
      await downloadVersion(doc.id, version)
    } catch (err) {
      notify(parseApiError(err).message, 'error')
    }
  }

  const runConfirmed = async () => {
    setBusy(true)
    try {
      if (confirm === 'delete') {
        await documentsApi.remove(doc.id)
        notify('Document supprimé.')
        navigate('/documents', { replace: true })
        return
      }
      if (confirm === 'archive') {
        await documentsApi.archive(doc.id)
        notify('Document archivé : il est maintenant en lecture seule.')
      }
      if (confirm === 'restore') {
        await documentsApi.restore(doc.id)
        notify('Document restauré.')
      }
      if (confirm === 'validate') {
        await documentsApi.validate(doc.id)
        notify('Document validé.')
      }
      if (confirm === 'reopen') {
        await documentsApi.reopen(doc.id)
        notify('Document remis en attente de validation.')
      }
      setConfirm(null)
      await load()
    } catch (err) {
      notify(parseApiError(err).message, 'error')
      setConfirm(null)
    } finally {
      setBusy(false)
    }
  }

  const reject = async (motif) => {
    setBusy(true)
    setRejectError('')
    try {
      await documentsApi.reject(doc.id, motif)
      notify('Document rejeté.')
      setRejecting(false)
      await load()
    } catch (err) {
      const e = parseApiError(err)
      setRejectError(e.fields?.motif ?? e.message)
    } finally {
      setBusy(false)
    }
  }

  if (error && !doc) {
    return (
      <>
        <ErrorAlert message={error} />
        <Link to="/documents">← Retour aux documents</Link>
      </>
    )
  }
  if (!doc) return <Spinner />

  const archived = doc.statut === 'archive'
  const latest = doc.versions?.[0]
  const confirmTexts = {
    delete: { title: 'Supprimer le document', confirmLabel: 'Supprimer', danger: true, message: 'Le document, toutes ses versions et ses partages seront définitivement supprimés.' },
    archive: { title: 'Archiver le document', confirmLabel: 'Archiver', danger: false, message: 'Le document passera en lecture seule (consultation et téléchargement restent possibles).' },
    validate: { title: 'Valider le document', confirmLabel: 'Valider', danger: false, message: 'Le document sera marqué « Validé ». Vous pourrez revenir sur cette décision.' },
    reopen: { title: 'Remettre en attente', confirmLabel: 'Remettre en attente', danger: false, message: 'La décision actuelle sera annulée : le document repassera « En attente » de validation.' },
    restore: { title: 'Restaurer le document', confirmLabel: 'Restaurer', danger: false, message: 'Le document redeviendra actif et modifiable.' },
  }

  return (
    <>
      <nav aria-label="Fil d'Ariane" className="mb-2 small">
        <Link to={archived ? '/archives' : '/documents'}>{archived ? 'Archives' : 'Documents'}</Link>
        <span className="text-muted"> / </span>
        <span className="text-muted">{doc.titre}</span>
      </nav>

      <div className="page-header">
        <div className="d-flex align-items-center gap-3">
          <i className={`bi ${fileIcon(latest?.nom_original)} file-ico`} style={{ fontSize: '2.2rem' }} />
          <div>
            <h1 className="page-title">{doc.titre}</h1>
            <div className="d-flex flex-wrap gap-2 align-items-center mt-1">
              <StatusBadge status={doc.status} statut={doc.statut} />
              <RightBadge right={doc.my_right} />
            </div>
          </div>
        </div>

        <div className="d-flex flex-wrap gap-2">
          {latest && (
            <button type="button" className="btn btn-primary" onClick={() => download(latest)}>
              <i className="bi bi-download me-1" />
              Télécharger (v{latest.numero_version})
            </button>
          )}
          {doc.can.update && (
            <Link to={`/documents/${doc.id}/edit`} className="btn btn-outline-primary">
              <i className="bi bi-pencil me-1" />
              Modifier
            </Link>
          )}
          {doc.can.share && (
            <button type="button" className="btn btn-outline-secondary" onClick={() => setSharing(true)}>
              <i className="bi bi-share me-1" />
              Partager
            </button>
          )}
          {doc.can.archive && (
            <button type="button" className="btn btn-outline-warning" onClick={() => setConfirm('archive')}>
              <i className="bi bi-archive me-1" />
              Archiver
            </button>
          )}
          {doc.can.restore && archived && (
            <button type="button" className="btn btn-outline-success" onClick={() => setConfirm('restore')}>
              <i className="bi bi-arrow-counterclockwise me-1" />
              Restaurer
            </button>
          )}
          {doc.can.delete && (
            <button type="button" className="btn btn-outline-danger" onClick={() => setConfirm('delete')}>
              <i className="bi bi-trash me-1" />
              Supprimer
            </button>
          )}
        </div>
      </div>

      {doc.validation === 'rejete' && !archived && (
        <div className="decision-banner rejected mb-3" role="alert">
          <i className="bi bi-x-octagon-fill" />
          <div>
            <strong>Document rejeté</strong>
            {doc.validated_by?.name && <> par {doc.validated_by.name}</>}
            {doc.validated_at && <> le {formatDate(doc.validated_at, true)}</>}
            <div>Motif : {doc.motif_rejet || 'non précisé.'}</div>
            <div className="small">Ajoutez une nouvelle version corrigée : le document repassera en validation.</div>
          </div>
        </div>
      )}
      {doc.validation === 'valide' && !archived && doc.validated_by && (
        <div className="decision-banner approved mb-3" role="status">
          <i className="bi bi-patch-check-fill" />
          <div>Validé par {doc.validated_by.name}{doc.validated_at && <> le {formatDate(doc.validated_at, true)}</>}.</div>
        </div>
      )}
      {doc.can.validate && !archived && (
        <div className="validation-bar mb-3" role="group" aria-label="Validation du document">
          <span className="validation-bar-label"><i className="bi bi-clipboard-check me-2" />Validation</span>
          {doc.validation !== 'valide' && (
            <button type="button" className="btn btn-success btn-sm" onClick={() => setConfirm('validate')}>
              <i className="bi bi-check-lg me-1" />Valider
            </button>
          )}
          {doc.validation !== 'rejete' && (
            <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => { setRejectError(''); setRejecting(true) }}>
              <i className="bi bi-x-lg me-1" />Rejeter
            </button>
          )}
          {doc.validation !== 'en_attente' && (
            <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => setConfirm('reopen')}>
              <i className="bi bi-arrow-counterclockwise me-1" />Remettre en attente
            </button>
          )}
        </div>
      )}

      {archived && (
        <div className="archive-banner mb-3" role="status">
          <i className="bi bi-lock-fill" />
          <span>
            Document archivé le {formatDate(doc.archived_at, true)} : il est en lecture seule. Vous pouvez toujours le
            consulter et le télécharger.
          </span>
        </div>
      )}

      <div className="row g-3">
        <div className="col-lg-4">
          <section className="panel panel-body h-100">
            <h2 className="panel-title"><i className="bi bi-info-circle" /> Informations</h2>
            <dl className="meta-list">
              {doc.code && (
                <>
                  <dt>Code</dt>
                  <dd><span className="doc-code mt-0">{doc.code}</span></dd>
                </>
              )}
              {doc.reference && (
                <>
                  <dt>Référence</dt>
                  <dd>{doc.reference}</dd>
                </>
              )}
              <dt>Catégorie</dt>
              <dd>{doc.category?.nom ?? '—'}</dd>
              <dt>Auteur</dt>
              <dd>{doc.owner?.name ?? '—'}</dd>
              <dt>Description</dt>
              <dd>{doc.description || <span className="text-muted">Aucune description.</span>}</dd>
              <dt>Mots-clés</dt>
              <dd>
                {splitKeywords(doc.mots_cles).length === 0 ? (
                  <span className="text-muted">—</span>
                ) : (
                  splitKeywords(doc.mots_cles).map((k) => <span className="kw" key={k}>{k}</span>)
                )}
              </dd>
              <dt>Créé le</dt>
              <dd>{formatDate(doc.created_at, true)}</dd>
              <dt>Modifié le</dt>
              <dd>{formatDate(doc.updated_at, true)}</dd>
            </dl>
          </section>
        </div>

        <div className="col-lg-8">
          <section className="panel panel-body h-100">
            <h2 className="panel-title">
              <i className="bi bi-clock-history" /> Historique des versions
              <span className="badge badge-version ms-1">{doc.versions?.length ?? 0}</span>
            </h2>

            {doc.can.add_version && <AddVersionForm documentId={doc.id} onDone={load} />}
            {!doc.can.add_version && !archived && (
              <p className="small text-muted"><i className="bi bi-lock me-1" />Droit de lecture seule : vous ne pouvez pas ajouter de version.</p>
            )}

            <ol className="timeline">
              {doc.versions.map((v, i) => (
                <li key={v.id} className={`version-item ${i === 0 ? 'version-current' : ''}`}>
                  <span className="tl-dot" aria-hidden="true">v{v.numero_version}</span>
                  <div className="tl-card">
                    <div style={{ minWidth: 0 }}>
                      <div className="tl-name">
                        <strong>v{v.numero_version}</strong>
                        {i === 0 && <span className="badge-current">actuelle</span>}
                        <span className="text-muted fw-normal"> · </span>
                        {v.nom_original}
                      </div>
                      <div className="tl-meta">
                        {formatBytes(v.taille)} · {v.author?.name ?? '—'} · {formatDate(v.created_at, true)}
                      </div>
                    </div>
                    <button
                      type="button"
                      className="btn btn-outline-secondary btn-sm"
                      title={`Télécharger la version ${v.numero_version}`}
                      onClick={() => download(v)}
                    >
                      <i className="bi bi-download me-1" />
                      Télécharger
                    </button>
                  </div>
                </li>
              ))}
            </ol>
          </section>
        </div>

        {doc.can.activity && (
          <div className="col-12">
            <section className="panel panel-body">
              <h2 className="panel-title"><i className="bi bi-clock-history" /> Activité récente</h2>
              {activity.length ? (
                <ActivityFeed items={activity} />
              ) : (
                <p className="text-muted mb-0">Aucune activité enregistrée.</p>
              )}
            </section>
          </div>
        )}

        {doc.can.share && (
          <div className="col-12">
            <section className="panel panel-body">
              <h2 className="panel-title"><i className="bi bi-people" /> Partage</h2>
              {doc.permissions?.length ? (
                <div className="d-flex flex-wrap gap-2">
                  {doc.permissions.map((p) => (
                    <span key={p.id} className="badge text-bg-light border fw-normal py-2 px-3" style={{ borderRadius: 999 }}>
                      <i className="bi bi-person me-1" />
                      {p.user?.name} · <strong>{p.droit === 'modification' ? 'modification' : 'lecture'}</strong>
                    </span>
                  ))}
                </div>
              ) : (
                <p className="text-muted mb-0">Ce document n'est partagé avec personne.</p>
              )}
            </section>
          </div>
        )}
      </div>

      {sharing && (
        <ShareModal
          document={doc}
          onClose={(changed) => {
            setSharing(false)
            if (changed) load()
          }}
        />
      )}

      {rejecting && (
        <RejectModal busy={busy} error={rejectError} onSubmit={reject} onCancel={() => setRejecting(false)} />
      )}

      {confirm && (
        <ConfirmModal
          {...confirmTexts[confirm]}
          message={<><strong>{doc.titre}</strong><br />{confirmTexts[confirm].message}</>}
          busy={busy}
          onConfirm={runConfirmed}
          onCancel={() => setConfirm(null)}
        />
      )}
    </>
  )
}

/** Envoi d'une nouvelle version (les précédentes sont conservées). */
function AddVersionForm({ documentId, onDone }) {
  const notify = useToast()
  const [file, setFile] = useState(null)
  const [error, setError] = useState('')
  const [progress, setProgress] = useState(null)
  const [busy, setBusy] = useState(false)

  const onFile = (chosen) => {
    setFile(chosen)
    setError(chosen ? (validateFile(chosen) ?? '') : '')
  }

  const submit = async (e) => {
    e.preventDefault()
    const problem = validateFile(file)
    if (problem) {
      setError(problem)
      return
    }
    setBusy(true)
    setError('')
    setProgress(0)
    try {
      const data = new FormData()
      data.append('fichier', file)
      const res = await documentsApi.addVersion(documentId, data, (evt) => {
        if (evt.total) setProgress(Math.round((evt.loaded * 100) / evt.total))
      })
      notify(`Version ${res.data.data.numero_version} ajoutée.`)
      setFile(null)
      await onDone()
    } catch (err) {
      const parsed = parseApiError(err)
      setError(parsed.fields.fichier ?? parsed.message)
    } finally {
      setBusy(false)
      setProgress(null)
    }
  }

  return (
    <form onSubmit={submit} className="mb-4" noValidate>
      <label className="form-label fw-semibold mb-2" htmlFor="new-version">Ajouter une nouvelle version</label>
      <FileDropzone id="new-version" file={file} onFile={onFile} error={error} disabled={busy} progress={progress} compact />
      <div className="d-flex justify-content-between align-items-center mt-2 gap-2 flex-wrap">
        <span className="form-text m-0">L'ancienne version reste disponible.</span>
        <button type="submit" className="btn btn-primary" disabled={!file || busy}>
          {busy ? <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" /> : <i className="bi bi-upload me-1" />}
          Envoyer
        </button>
      </div>
    </form>
  )
}
