import { Link } from 'react-router-dom'
import { fileIcon, formatBytes, formatDate, splitKeywords } from '../utils/format'
import Modal from './Modal'
import { StatusBadge } from './StatusBadge'

/** Aperçu rapide des métadonnées d'un document (sans quitter la page). */
export default function DocumentPreviewModal({ doc, onClose, onDownload }) {
  const latest = doc.latest_version
  const ext = (doc.file_type ?? latest?.nom_original?.split('.').pop() ?? '').toUpperCase()
  const keywords = splitKeywords(doc.mots_cles)

  return (
    <Modal
      title="Aperçu du document"
      onClose={onClose}
      footer={
        <>
          {latest && onDownload && (
            <button type="button" className="btn btn-outline-secondary" onClick={() => onDownload(doc)}>
              <i className="bi bi-download me-1" />
              Télécharger
            </button>
          )}
          <Link to={`/documents/${doc.id}`} className="btn btn-primary" onClick={onClose}>
            Ouvrir la fiche
          </Link>
        </>
      }
    >
      <div className="d-flex align-items-start gap-3 mb-3">
        <i className={`bi ${fileIcon(latest?.nom_original)} file-ico`} style={{ fontSize: '2.4rem' }} />
        <div>
          <h2 className="h5 mb-1">{doc.titre}</h2>
          <div className="d-flex flex-wrap gap-2 align-items-center">
            {doc.code && <span className="doc-code mt-0">{doc.code}</span>}
            <StatusBadge status={doc.status} statut={doc.statut} />
          </div>
        </div>
      </div>

      <dl className="meta-list">
        <dt>Référence</dt>
        <dd>{doc.reference || <span className="text-muted">—</span>}</dd>
        <dt>Catégorie</dt>
        <dd>
          {doc.category?.couleur && <span className="cat-dot" style={{ background: doc.category.couleur }} />}
          {doc.category?.nom ?? '—'}
        </dd>
        <dt>Auteur</dt>
        <dd>{doc.owner?.name ?? '—'}</dd>
        <dt>Fichier</dt>
        <dd>
          {latest ? (
            <>
              {latest.nom_original}
              <span className="text-muted"> · {ext} · {formatBytes(latest.taille)} · version {latest.numero_version}</span>
            </>
          ) : (
            '—'
          )}
        </dd>
        <dt>Créé le</dt>
        <dd>{formatDate(doc.created_at, true)}</dd>
        <dt>Modifié le</dt>
        <dd>{formatDate(doc.updated_at, true)}</dd>
        <dt>Mots-clés</dt>
        <dd>
          {keywords.length ? keywords.map((k) => <span className="kw mt-0" key={k}>{k}</span>) : <span className="text-muted">—</span>}
        </dd>
        <dt>Description</dt>
        <dd>{doc.description || <span className="text-muted">Aucune description.</span>}</dd>
      </dl>
    </Modal>
  )
}
