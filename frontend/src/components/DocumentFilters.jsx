import { useState } from 'react'
import UserPicker from './UserPicker'

const SCOPES = [
  { value: '', label: 'Tous' },
  { value: 'mes', label: 'Mes documents' },
  { value: 'partages', label: 'Partagés avec moi' },
]

/**
 * Filtres combinables.
 *  - mode "list" / "archives" : recherche rapide + catégorie (+ portée pour la liste)
 *  - mode "search"            : tous les critères (titre, mots-clés, catégorie, auteur, dates, statut…)
 * Les filtres actifs vivent dans l'URL (?q=…&category_id=…), donc partageables.
 */
export default function DocumentFilters({ mode, params, categories, onApply }) {
  // Le composant est remonté (key) quand l'URL change : l'état initial vient donc de l'URL.
  const [form, setForm] = useState(() => ({ ...params }))
  const [author, setAuthor] = useState(() => (params.user_id ? { id: params.user_id, name: params.auteur_nom ?? `#${params.user_id}` } : null))
  const advanced = mode === 'search'

  const set = (name) => (e) => setForm((f) => ({ ...f, [name]: e.target.value }))

  const submit = (e) => {
    e.preventDefault()
    const clean = {}
    for (const [k, v] of Object.entries(form)) {
      if (v !== '' && v != null && k !== 'page') clean[k] = v
    }
    if (author) {
      clean.user_id = author.id
      clean.auteur_nom = author.name
    } else {
      delete clean.user_id
      delete clean.auteur_nom
    }
    onApply(clean)
  }

  const reset = () => {
    setForm({})
    setAuthor(null)
    onApply({})
  }

  const hasFilters = Object.keys(params).some((k) => k !== 'page')

  return (
    <form onSubmit={submit} className="filters panel panel-body mb-3">
      {mode === 'list' && (
        <div className="scope-tabs d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Portée">
          {SCOPES.map((s) => (
            <button
              type="button"
              key={s.value}
              className={`btn btn-sm ${(form.portee ?? '') === s.value ? 'btn-primary' : 'btn-outline-secondary'}`}
              onClick={() => {
                const next = { ...form, portee: s.value }
                setForm(next)
                const clean = Object.fromEntries(Object.entries(next).filter(([k, v]) => v !== '' && v != null && k !== 'page'))
                onApply(clean)
              }}
            >
              {s.label}
            </button>
          ))}
        </div>
      )}

      <div className="row g-3 align-items-end">
        <div className={advanced ? 'col-md-4' : 'col-md-5'}>
          <label className="form-label" htmlFor="f-q">
            {advanced ? 'Texte libre (titre, description, mots-clés)' : 'Rechercher'}
          </label>
          <input id="f-q" className="form-control" placeholder="Ex. contrat, facture…" value={form.q ?? ''} onChange={set('q')} />
        </div>

        {advanced && (
          <>
            <div className="col-md-4">
              <label className="form-label" htmlFor="f-titre">Titre contient</label>
              <input id="f-titre" className="form-control" value={form.titre ?? ''} onChange={set('titre')} />
            </div>
            <div className="col-md-4">
              <label className="form-label" htmlFor="f-kw">Mots-clés (séparés par des virgules)</label>
              <input id="f-kw" className="form-control" placeholder="contrat, 2026" value={form.mots_cles ?? ''} onChange={set('mots_cles')} />
            </div>
          </>
        )}

        <div className="col-md-4">
          <label className="form-label" htmlFor="f-cat">Catégorie</label>
          <select id="f-cat" className="form-select" value={form.category_id ?? ''} onChange={set('category_id')}>
            <option value="">Toutes les catégories</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nom}
              </option>
            ))}
          </select>
        </div>

        {advanced && (
          <>
            <div className="col-md-4">
              <label className="form-label" htmlFor="f-statut">Statut</label>
              <select id="f-statut" className="form-select" value={form.statut ?? ''} onChange={set('statut')}>
                <option value="">Actifs et archivés</option>
                <option value="actif">Actifs</option>
                <option value="archive">Archivés</option>
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label" htmlFor="f-portee">Portée</label>
              <select id="f-portee" className="form-select" value={form.portee ?? ''} onChange={set('portee')}>
                {SCOPES.map((s) => (
                  <option key={s.value} value={s.value}>{s.label}</option>
                ))}
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label" htmlFor="f-date-debut">Créé à partir du</label>
              <input id="f-date-debut" type="date" className="form-control" value={form.date_debut ?? ''} onChange={set('date_debut')} />
            </div>
            <div className="col-md-4">
              <label className="form-label" htmlFor="f-date-fin">Créé jusqu'au</label>
              <input id="f-date-fin" type="date" className="form-control" value={form.date_fin ?? ''} onChange={set('date_fin')} />
            </div>
            <div className="col-md-4">
              <label className="form-label">Auteur</label>
              {author ? (
                <div className="d-flex align-items-center justify-content-between border rounded px-3 py-2 bg-white">
                  <span><i className="bi bi-person me-2" />{author.name}</span>
                  <button type="button" className="btn-close" aria-label="Retirer l'auteur" onClick={() => setAuthor(null)} />
                </div>
              ) : (
                <UserPicker placeholder="Rechercher un auteur…" onSelect={setAuthor} />
              )}
            </div>
            <div className="col-md-4">
              <label className="form-label" htmlFor="f-tri">Trier par</label>
              <div className="input-group">
                <select id="f-tri" className="form-select" value={form.tri ?? 'created_at'} onChange={set('tri')}>
                  <option value="created_at">Date de création</option>
                  <option value="updated_at">Dernière modification</option>
                  <option value="titre">Titre</option>
                </select>
                <select className="form-select" aria-label="Ordre" value={form.ordre ?? 'desc'} onChange={set('ordre')}>
                  <option value="desc">Décroissant</option>
                  <option value="asc">Croissant</option>
                </select>
              </div>
            </div>
          </>
        )}

        <div className={`${advanced ? 'col-12' : 'col-md-3'} d-flex gap-2 justify-content-end`}>
          {hasFilters && (
            <button type="button" className="btn btn-light" onClick={reset}>
              <i className="bi bi-x-circle me-1" />
              Réinitialiser
            </button>
          )}
          <button type="submit" className="btn btn-primary">
            <i className="bi bi-search me-1" />
            Rechercher
          </button>
        </div>
      </div>
    </form>
  )
}
