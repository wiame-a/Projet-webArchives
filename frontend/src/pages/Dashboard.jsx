import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { categoriesApi, dashboardApi, documentsApi } from '../api/services'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'
import { downloadVersion } from '../utils/download'
import { parseApiError } from '../utils/errors'
import usePageTitle from '../utils/usePageTitle'
import { fileIcon, formatBytes, formatDate } from '../utils/format'
import DocumentPreviewModal from '../components/DocumentPreviewModal'
import ErrorAlert from '../components/ErrorAlert'
import Spinner from '../components/Spinner'
import { StatusBadge } from '../components/StatusBadge'

const RECENT_LIMIT = 8

/** Cartes KPI : `value` et `sub` sont calculés à partir des statistiques de l'API. */
function buildCards(s, isAdmin) {
  const actifs = s.documents_actifs ?? 0
  const valides = s.documents_valides ?? 0
  const taux = actifs > 0 ? Math.round((valides / actifs) * 100) : 0

  const cards = [
    { key: 'actifs', label: 'Documents actifs', icon: 'bi-folder2-open', tone: 'green', to: '/documents',
      value: actifs, sub: `${actifs + (s.documents_archives ?? 0)} au total, archives comprises` },
    { key: 'stockage', label: 'Espace de stockage', icon: 'bi-hdd', tone: 'blue', to: null,
      value: formatBytes(s.stockage_octets ?? 0), sub: `${s.versions ?? 0} version(s) stockée(s)` },
    { key: 'categories', label: 'Catégories actives', icon: 'bi-tags', tone: 'violet', to: isAdmin ? '/categories' : null,
      value: s.categories_actives ?? 0, sub: `sur ${s.categories ?? 0} département(s)` },
    { key: 'valides', label: 'Documents validés', icon: 'bi-patch-check', tone: 'green', to: '/search?validation=valide',
      value: valides, sub: `${taux} % des documents actifs`, progress: taux },
    { key: 'attente', label: 'En attente', icon: 'bi-hourglass-split', tone: 'amber', to: '/search?validation=en_attente',
      value: s.documents_en_attente ?? 0, sub: 'à valider' },
    { key: 'archives', label: 'Archivés', icon: 'bi-archive', tone: 'blue', to: '/archives',
      value: s.documents_archives ?? 0, sub: 'en lecture seule' },
    { key: 'mes', label: 'Mes documents', icon: 'bi-person-workspace', tone: '', to: '/documents?portee=mes',
      value: s.mes_documents ?? 0, sub: `${s.partages_avec_moi ?? 0} partagé(s) avec moi` },
  ]
  if (isAdmin) {
    cards.push({ key: 'users', label: 'Utilisateurs', icon: 'bi-person-gear', tone: 'rose', to: '/users', value: s.utilisateurs ?? 0, sub: 'comptes actifs' })
  }
  return cards
}

export default function Dashboard() {
  usePageTitle('Tableau de bord')
  const { user, isAdmin } = useAuth()
  const notify = useToast()

  // --- Statistiques
  const [stats, setStats] = useState(null)
  const [error, setError] = useState('')
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let active = true
    dashboardApi
      .get()
      .then((res) => active && setStats(res.data.stats))
      .catch((err) => active && setError(parseApiError(err).message))
    return () => {
      active = false
    }
  }, [reloadKey])

  // --- Documents récents : recherche instantanée + filtre par catégorie (côté serveur)
  const [categories, setCategories] = useState([])
  const [q, setQ] = useState('')
  const [debouncedQ, setDebouncedQ] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [docs, setDocs] = useState(null) // null = chargement
  const [docsError, setDocsError] = useState('')
  const [docsKey, setDocsKey] = useState(0)
  const [preview, setPreview] = useState(null)

  useEffect(() => {
    categoriesApi.list().then((res) => setCategories(res.data.data)).catch(() => {})
  }, [])

  // On attend 300 ms après la dernière frappe pour ne pas interroger l'API à chaque lettre.
  useEffect(() => {
    const t = setTimeout(() => setDebouncedQ(q.trim()), 300)
    return () => clearTimeout(t)
  }, [q])

  useEffect(() => {
    const controller = new AbortController()
    const params = { per_page: RECENT_LIMIT }
    if (debouncedQ) params.q = debouncedQ
    if (categoryId) params.category_id = categoryId

    documentsApi
      .list(params, controller.signal)
      .then((res) => {
        setDocs(res.data.data)
        setDocsError('')
      })
      .catch((err) => {
        if (err.code === 'ERR_CANCELED') return // requête remplacée par une plus récente
        setDocsError(parseApiError(err).message)
        setDocs([])
      })
    return () => controller.abort()
  }, [debouncedQ, categoryId, docsKey])

  const filtered = debouncedQ !== '' || categoryId !== ''
  const resetFilters = () => {
    setQ('')
    setDebouncedQ('')
    setCategoryId('')
  }

  const onDownload = useCallback(
    async (doc) => {
      try {
        await downloadVersion(doc.id, doc.latest_version)
      } catch (err) {
        notify(parseApiError(err).message, 'error')
      }
    },
    [notify],
  )

  const cards = stats ? buildCards(stats, isAdmin) : []

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
      {!stats && !error && <Spinner />}

      {stats && (
        <div className="row g-3 mb-4">
          {cards.map((c) => {
            const body = (
              <div className="stat-card">
                <span className={`stat-icon ${c.tone}`}>
                  <i className={`bi ${c.icon}`} />
                </span>
                <div className="flex-grow-1">
                  <div className="stat-value" data-kpi={c.key}>{c.value}</div>
                  <div className="stat-label">{c.label}</div>
                  <div className="stat-sub">{c.sub}</div>
                  {c.progress !== undefined && (
                    <div className="progress-slim" aria-hidden="true">
                      <span style={{ width: `${c.progress}%` }} />
                    </div>
                  )}
                </div>
              </div>
            )
            return (
              <div className="col-12 col-sm-6 col-lg-4 col-xl-3" key={c.key}>
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
      )}

      <div className="panel">
        <div className="panel-body pb-3">
          <div className="d-flex justify-content-between align-items-center mb-3">
            <h2 className="panel-title mb-0">
              <i className="bi bi-stars" /> Documents récents
            </h2>
            <Link to="/documents" className="small">Tout voir</Link>
          </div>

          <div className="quick-filters">
            <div className="search-box">
              <i className="bi bi-search" />
              <input
                type="search"
                className="form-control"
                placeholder="Rechercher par titre, code ARC-…, référence, mot-clé"
                aria-label="Rechercher un document"
                value={q}
                onChange={(e) => setQ(e.target.value)}
              />
            </div>
            <select
              className="form-select"
              aria-label="Filtrer par catégorie"
              value={categoryId}
              onChange={(e) => setCategoryId(e.target.value)}
            >
              <option value="">Toutes les catégories</option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>{c.nom}</option>
              ))}
            </select>
            {filtered && (
              <button type="button" className="btn btn-outline-secondary" onClick={resetFilters}>
                <i className="bi bi-x-lg me-1" />
                Réinitialiser
              </button>
            )}
          </div>
        </div>

        <ErrorAlert message={docsError} onRetry={() => { setDocsError(''); setDocsKey((k) => k + 1) }} />

        {docs === null ? (
          <Spinner />
        ) : docs.length === 0 ? (
          !docsError && (
            <div className="empty-state">
              <i className="bi bi-inbox" />
              <p className="mt-2 mb-0">
                {filtered ? 'Aucun document ne correspond à votre recherche.' : 'Aucun document pour le moment.'}
              </p>
            </div>
          )
        ) : (
          <div className="table-responsive">
            <table className="table table-docs align-middle">
              <thead>
                <tr>
                  <th>Document</th>
                  <th>Catégorie</th>
                  <th>Fichier</th>
                  <th>Statut</th>
                  <th>Créé le</th>
                  <th className="text-end">Aperçu</th>
                </tr>
              </thead>
              <tbody>
                {docs.map((doc) => {
                  const v = doc.latest_version
                  return (
                    <tr key={doc.id}>
                      <td style={{ minWidth: 240 }}>
                        <div className="d-flex gap-2 align-items-start">
                          <i className={`bi ${fileIcon(v?.nom_original)} file-ico`} />
                          <div>
                            <button type="button" className="btn btn-link doc-title p-0 text-start" onClick={() => setPreview(doc)}>
                              {doc.titre}
                            </button>
                            <div className="doc-code d-block">
                              {doc.code}{doc.reference ? ` · ${doc.reference}` : ''}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="text-nowrap">
                        {doc.category?.couleur && <span className="cat-dot" style={{ background: doc.category.couleur }} />}
                        {doc.category?.nom ?? '—'}
                      </td>
                      <td className="text-nowrap">
                        <span className="text-uppercase small fw-semibold">{doc.file_type ?? '—'}</span>
                        <span className="text-muted small"> · {formatBytes(v?.taille)}</span>
                      </td>
                      <td><StatusBadge status={doc.status} statut={doc.statut} /></td>
                      <td className="text-nowrap">{formatDate(doc.created_at)}</td>
                      <td className="text-end">
                        <button
                          type="button"
                          className="btn btn-outline-secondary icon-btn"
                          title="Aperçu rapide"
                          aria-label={`Aperçu de ${doc.titre}`}
                          onClick={() => setPreview(doc)}
                        >
                          <i className="bi bi-eye" />
                        </button>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {preview && <DocumentPreviewModal doc={preview} onClose={() => setPreview(null)} onDownload={onDownload} />}
    </>
  )
}
