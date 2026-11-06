import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import axios from 'axios'
import { categoriesApi, documentsApi } from '../api/services'
import { parseApiError } from '../utils/errors'
import usePageTitle from '../utils/usePageTitle'
import DocumentFilters from './DocumentFilters'
import DocumentTable from './DocumentTable'
import ErrorAlert from './ErrorAlert'
import Pagination from './Pagination'
import Spinner from './Spinner'

const TEXTS = {
  list: { title: 'Documents', sub: 'Les documents que vous possédez ou qui ont été partagés avec vous.' },
  archives: { title: 'Archives', sub: 'Documents archivés : consultables en lecture seule, restaurables à tout moment.' },
  search: { title: 'Recherche avancée', sub: 'Combinez plusieurs critères. Seuls les documents auxquels vous avez accès sont retournés.' },
}

/**
 * Page de liste réutilisable (liste, archives, recherche avancée).
 * Les filtres et la page courante sont dans l'URL (useSearchParams).
 */
export default function DocumentsBrowser({ mode }) {
  usePageTitle(TEXTS[mode].title)
  const [searchParams, setSearchParams] = useSearchParams()
  const [categories, setCategories] = useState([])
  const [state, setState] = useState({ items: [], meta: null })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [reloadKey, setReloadKey] = useState(0)

  const paramsKey = searchParams.toString()
  const params = useMemo(() => Object.fromEntries(new URLSearchParams(paramsKey)), [paramsKey])

  useEffect(() => {
    categoriesApi.list().then((res) => setCategories(res.data.data)).catch(() => {})
  }, [])

  const apiParams = useMemo(() => {
    const p = { ...params, per_page: 10 }
    delete p.auteur_nom // simple libellé d'affichage, inconnu de l'API
    if (mode === 'list') p.statut = 'actif'
    if (mode === 'archives') p.statut = 'archive'
    return p
  }, [params, mode])

  useEffect(() => {
    const ctrl = new AbortController()
    setLoading(true)
    setError('')
    documentsApi
      .list(apiParams, ctrl.signal)
      .then((res) => setState({ items: res.data.data, meta: res.data.meta }))
      .catch((err) => {
        if (axios.isCancel(err)) return
        setError(parseApiError(err).message)
      })
      .finally(() => {
        if (!ctrl.signal.aborted) setLoading(false)
      })
    return () => ctrl.abort()
  }, [apiParams, reloadKey])

  const apply = useCallback((filters) => setSearchParams(filters), [setSearchParams])
  const goToPage = (page) => setSearchParams({ ...params, page })
  const reload = () => {
    // Si on vient de supprimer le dernier élément d'une page, on revient à la précédente.
    if (state.items.length === 1 && state.meta?.current_page > 1) {
      setSearchParams({ ...params, page: state.meta.current_page - 1 })
    } else {
      setReloadKey((k) => k + 1)
    }
  }

  const { title, sub } = TEXTS[mode]

  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">{title}</h1>
          <p className="page-sub">{sub}</p>
        </div>
        {mode === 'list' && (
          <Link to="/documents/new" className="btn btn-primary">
            <i className="bi bi-plus-lg me-1" />
            Nouveau document
          </Link>
        )}
      </div>

      <DocumentFilters key={paramsKey} mode={mode} params={params} categories={categories} onApply={apply} />

      <ErrorAlert message={error} onRetry={() => setReloadKey((k) => k + 1)} />

      <div className="panel">
        {loading ? (
          <Spinner />
        ) : state.items.length === 0 && !error ? (
          <div className="empty-state">
            <i className="bi bi-inbox" />
            <p className="mt-2 mb-0">
              {Object.keys(params).length > 0 ? 'Aucun document ne correspond à ces critères.' : 'Aucun document pour le moment.'}
            </p>
          </div>
        ) : (
          <div className="p-2 p-md-3">
            <DocumentTable documents={state.items} onChanged={reload} />
          </div>
        )}
      </div>

      {!loading && <Pagination meta={state.meta} onPage={goToPage} />}
    </>
  )
}
