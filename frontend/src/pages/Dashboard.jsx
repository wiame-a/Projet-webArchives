import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { dashboardApi } from '../api/services'
import { useAuth } from '../context/AuthContext'
import { parseApiError } from '../utils/errors'
import usePageTitle from '../utils/usePageTitle'
import { fileIcon, formatDate } from '../utils/format'
import ErrorAlert from '../components/ErrorAlert'
import Spinner from '../components/Spinner'
import { StatusBadge } from '../components/StatusBadge'

const CARDS = [
  { key: 'documents_actifs', label: 'Documents actifs', icon: 'bi-folder2-open', tone: 'green', to: '/documents' },
  { key: 'mes_documents', label: 'Mes documents', icon: 'bi-person-workspace', tone: '', to: '/documents?portee=mes' },
  { key: 'partages_avec_moi', label: 'Partagés avec moi', icon: 'bi-people', tone: 'blue', to: '/documents?portee=partages' },
  { key: 'documents_archives', label: 'Archivés', icon: 'bi-archive', tone: 'amber', to: '/archives' },
  { key: 'versions', label: 'Versions stockées', icon: 'bi-clock-history', tone: 'slate', to: null },
  { key: 'categories', label: 'Catégories', icon: 'bi-tags', tone: 'rose', to: null },
]

export default function Dashboard() {
  usePageTitle('Tableau de bord')
  const { user, isAdmin } = useAuth()
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let active = true
    dashboardApi
      .get()
      .then((res) => active && setData(res.data))
      .catch((err) => active && setError(parseApiError(err).message))
    return () => {
      active = false
    }
  }, [reloadKey])

  const cards = isAdmin
    ? [...CARDS, { key: 'utilisateurs', label: 'Utilisateurs', icon: 'bi-person-gear', tone: 'blue', to: '/users' }]
    : CARDS

  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Bonjour, {user?.name?.split(' ')[0]}</h1>
          <p className="page-sub">Voici l'état de vos archives dans Archivium.</p>
        </div>
        <Link to="/documents/new" className="btn btn-primary">
          <i className="bi bi-plus-lg me-1" />
          Nouveau document
        </Link>
      </div>

      <ErrorAlert
        message={error}
        onRetry={() => {
          setError('')
          setReloadKey((k) => k + 1)
        }}
      />
      {!data && !error && <Spinner />}

      {data && (
        <>
          <div className="row g-3 mb-4">
            {cards.map((c) => {
              const body = (
                <div className="stat-card">
                  <span className={`stat-icon ${c.tone}`}>
                    <i className={`bi ${c.icon}`} />
                  </span>
                  <div>
                    <div className="stat-value">{data.stats[c.key] ?? 0}</div>
                    <div className="stat-label">{c.label}</div>
                  </div>
                </div>
              )
              return (
                <div className="col-6 col-lg-4 col-xl-3" key={c.key}>
                  {c.to ? (
                    <Link to={c.to} className="text-decoration-none text-reset d-block h-100">
                      {body}
                    </Link>
                  ) : (
                    body
                  )}
                </div>
              )
            })}
          </div>

          <div className="panel">
            <div className="panel-body pb-3 d-flex justify-content-between align-items-center">
              <h2 className="panel-title mb-0">
                <i className="bi bi-stars" /> Documents récents
              </h2>
              <Link to="/documents" className="small">Tout voir</Link>
            </div>
            {data.recents.length === 0 ? (
              <div className="empty-state">
                <i className="bi bi-inbox" />
                <p className="mt-2 mb-0">Aucun document pour le moment.</p>
              </div>
            ) : (
              <ul className="list-group list-group-flush">
                {data.recents.map((doc) => (
                  <li key={doc.id} className="list-group-item d-flex align-items-center gap-3 py-3 px-4">
                    <i className={`bi ${fileIcon(doc.latest_version?.nom_original)} file-ico`} />
                    <div className="flex-grow-1">
                      <Link to={`/documents/${doc.id}`} className="doc-title">{doc.titre}</Link>
                      <div className="small text-muted">
                        {doc.category?.nom} · {doc.owner?.name} · {formatDate(doc.created_at)}
                      </div>
                    </div>
                    <span className="badge badge-version">v{doc.latest_version?.numero_version ?? 1}</span>
                    <StatusBadge statut={doc.statut} />
                  </li>
                ))}
              </ul>
            )}
          </div>
        </>
      )}
    </>
  )
}
