import { useState } from 'react'
import { Link } from 'react-router-dom'
import { documentsApi } from '../api/services'
import { downloadVersion } from '../utils/download'
import { parseApiError } from '../utils/errors'
import { fileIcon, formatDate, splitKeywords } from '../utils/format'
import { useToast } from '../context/ToastContext'
import ConfirmModal from './ConfirmModal'
import { RightBadge, StatusBadge } from './StatusBadge'

/**
 * Tableau de documents avec actions contextuelles.
 * Les boutons dépendent de `doc.can` (calculé par l'API selon les Policies).
 */
export default function DocumentTable({ documents, onChanged }) {
  const notify = useToast()
  const [pending, setPending] = useState(null) // { type: 'delete' | 'archive' | 'restore', doc }
  const [busy, setBusy] = useState(false)

  const onDownload = async (doc) => {
    try {
      await downloadVersion(doc.id, doc.latest_version)
    } catch (err) {
      notify(parseApiError(err).message, 'error')
    }
  }

  const confirm = async () => {
    const { type, doc } = pending
    setBusy(true)
    try {
      if (type === 'delete') await documentsApi.remove(doc.id)
      if (type === 'archive') await documentsApi.archive(doc.id)
      if (type === 'restore') await documentsApi.restore(doc.id)
      notify(
        { delete: 'Document supprimé.', archive: 'Document archivé.', restore: 'Document restauré.' }[type],
      )
      setPending(null)
      onChanged()
    } catch (err) {
      notify(parseApiError(err).message, 'error')
      setPending(null)
    } finally {
      setBusy(false)
    }
  }

  const texts = {
    delete: {
      title: 'Supprimer le document',
      confirmLabel: 'Supprimer',
      danger: true,
      message: 'Le document, toutes ses versions et ses partages seront définitivement supprimés.',
    },
    archive: {
      title: 'Archiver le document',
      confirmLabel: 'Archiver',
      danger: false,
      message: 'Le document passera en lecture seule. Vous pourrez le restaurer à tout moment.',
    },
    restore: {
      title: 'Restaurer le document',
      confirmLabel: 'Restaurer',
      danger: false,
      message: 'Le document redeviendra actif et modifiable.',
    },
  }

  return (
    <>
      <div className="table-responsive">
        <table className="table table-docs align-middle">
          <thead>
            <tr>
              <th>Document</th>
              <th>Catégorie</th>
              <th>Auteur</th>
              <th className="text-center">Version</th>
              <th>Statut</th>
              <th>Créé le</th>
              <th className="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            {documents.map((doc) => (
              <tr key={doc.id}>
                <td style={{ minWidth: 240 }}>
                  <div className="d-flex gap-2 align-items-start">
                    <i className={`bi ${fileIcon(doc.latest_version?.nom_original)} file-ico`} />
                    <div>
                      <Link to={`/documents/${doc.id}`} className="doc-title">
                        {doc.titre}
                      </Link>
                      <div>
                        {splitKeywords(doc.mots_cles).map((k) => (
                          <span className="kw" key={k}>
                            {k}
                          </span>
                        ))}
                      </div>
                    </div>
                  </div>
                </td>
                <td>{doc.category?.nom ?? '—'}</td>
                <td>
                  {doc.owner?.name ?? '—'}
                  {doc.my_right && doc.my_right !== 'proprietaire' && (
                    <div className="mt-1">
                      <RightBadge right={doc.my_right} />
                    </div>
                  )}
                </td>
                <td className="text-center">
                  <span className="badge badge-version">v{doc.latest_version?.numero_version ?? 1}</span>
                </td>
                <td>
                  <StatusBadge statut={doc.statut} />
                </td>
                <td className="text-nowrap">{formatDate(doc.created_at)}</td>
                <td className="text-end text-nowrap">
                  <div className="row-actions">
                  <Link to={`/documents/${doc.id}`} className="btn btn-outline-secondary icon-btn me-1" title="Voir le détail" aria-label="Voir le détail">
                    <i className="bi bi-eye" />
                  </Link>
                  {doc.latest_version && (
                    <button
                      type="button"
                      className="btn btn-outline-secondary icon-btn me-1"
                      title="Télécharger la dernière version"
                      onClick={() => onDownload(doc)}
                    >
                      <i className="bi bi-download" />
                    </button>
                  )}
                  {doc.can.update && (
                    <Link to={`/documents/${doc.id}/edit`} className="btn btn-outline-primary icon-btn me-1" title="Modifier">
                      <i className="bi bi-pencil" />
                    </Link>
                  )}
                  {doc.can.archive && (
                    <button
                      type="button"
                      className="btn btn-outline-warning icon-btn me-1"
                      title="Archiver"
                      onClick={() => setPending({ type: 'archive', doc })}
                    >
                      <i className="bi bi-archive" />
                    </button>
                  )}
                  {doc.can.restore && doc.statut === 'archive' && (
                    <button
                      type="button"
                      className="btn btn-outline-success icon-btn me-1"
                      title="Restaurer"
                      onClick={() => setPending({ type: 'restore', doc })}
                    >
                      <i className="bi bi-arrow-counterclockwise" />
                    </button>
                  )}
                  {doc.can.delete && (
                    <button
                      type="button"
                      className="btn btn-outline-danger icon-btn"
                      title="Supprimer"
                      onClick={() => setPending({ type: 'delete', doc })}
                    >
                      <i className="bi bi-trash" />
                    </button>
                  )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {pending && (
        <ConfirmModal
          {...texts[pending.type]}
          message={
            <>
              <strong>{pending.doc.titre}</strong>
              <br />
              {texts[pending.type].message}
            </>
          }
          busy={busy}
          onConfirm={confirm}
          onCancel={() => setPending(null)}
        />
      )}
    </>
  )
}
