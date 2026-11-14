import { useEffect, useState } from 'react'
import { activityApi } from '../api/services'
import { parseApiError } from '../utils/errors'
import usePageTitle from '../utils/usePageTitle'
import ActivityFeed from '../components/ActivityFeed'
import { ACTION_OPTIONS } from '../components/activityMeta'
import ErrorAlert from '../components/ErrorAlert'
import Pagination from '../components/Pagination'
import Spinner from '../components/Spinner'

const EMPTY = { q: '', action: '', date_debut: '', date_fin: '' }

export default function Activity() {
  usePageTitle("Journal d'activité")
  const [draft, setDraft] = useState(EMPTY)
  const [applied, setApplied] = useState({ ...EMPTY, page: 1 })
  const [state, setState] = useState({ items: [], meta: null })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)
    const params = Object.fromEntries(Object.entries(applied).filter(([, v]) => v !== '' && v !== null))
    activityApi
      .list(params, controller.signal)
      .then((res) => {
        setState({ items: res.data.data, meta: res.data.meta })
        setError('')
        setLoading(false)
      })
      .catch((err) => {
        if (err.code === 'ERR_CANCELED') return
        setError(parseApiError(err).message)
        setLoading(false)
      })
    return () => controller.abort()
  }, [applied])

  const submit = (e) => {
    e.preventDefault()
    setApplied({ ...draft, page: 1 })
  }
  const reset = () => {
    setDraft(EMPTY)
    setApplied({ ...EMPTY, page: 1 })
  }

  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Journal d'activité</h1>
          <p className="page-sub">Qui a fait quoi, et quand, sur l'ensemble des documents.</p>
        </div>
      </div>

      <form className="panel panel-body mb-3" onSubmit={submit}>
        <div className="row g-2 align-items-end">
          <div className="col-md-4">
            <label className="form-label small fw-semibold" htmlFor="a-q">Recherche</label>
            <input id="a-q" className="form-control" placeholder="Titre, code, motif…" value={draft.q} onChange={(e) => setDraft({ ...draft, q: e.target.value })} />
          </div>
          <div className="col-md-3">
            <label className="form-label small fw-semibold" htmlFor="a-action">Action</label>
            <select id="a-action" className="form-select" value={draft.action} onChange={(e) => setDraft({ ...draft, action: e.target.value })}>
              <option value="">Toutes les actions</option>
              {ACTION_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          </div>
          <div className="col-6 col-md-2">
            <label className="form-label small fw-semibold" htmlFor="a-from">Du</label>
            <input id="a-from" type="date" className="form-control" value={draft.date_debut} onChange={(e) => setDraft({ ...draft, date_debut: e.target.value })} />
          </div>
          <div className="col-6 col-md-2">
            <label className="form-label small fw-semibold" htmlFor="a-to">Au</label>
            <input id="a-to" type="date" className="form-control" value={draft.date_fin} onChange={(e) => setDraft({ ...draft, date_fin: e.target.value })} />
          </div>
          <div className="col-md-1 d-flex gap-1">
            <button className="btn btn-primary flex-grow-1" aria-label="Filtrer"><i className="bi bi-funnel" /></button>
            <button type="button" className="btn btn-outline-secondary" aria-label="Réinitialiser" onClick={reset}><i className="bi bi-x-lg" /></button>
          </div>
        </div>
      </form>

      <ErrorAlert message={error} />
      <section className="panel panel-body">
        {loading ? (
          <Spinner label="Chargement du journal…" />
        ) : state.items.length ? (
          <ActivityFeed items={state.items} showDocument />
        ) : (
          <p className="text-muted text-center py-4 mb-0">Aucune activité ne correspond à ces critères.</p>
        )}
      </section>
      <Pagination meta={state.meta} onPage={(page) => setApplied((a) => ({ ...a, page }))} />
    </>
  )
}
