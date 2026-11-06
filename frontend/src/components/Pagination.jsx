/** Pagination à partir de la méta Laravel (current_page, last_page, total). */
export default function Pagination({ meta, onPage }) {
  if (!meta || meta.last_page <= 1) return null

  const { current_page: current, last_page: last } = meta
  const pages = []
  for (let p = 1; p <= last; p++) {
    if (p === 1 || p === last || Math.abs(p - current) <= 1) pages.push(p)
    else if (pages[pages.length - 1] !== '…') pages.push('…')
  }

  return (
    <nav aria-label="Pagination" className="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
      <small className="text-muted">
        {meta.total} résultat{meta.total > 1 ? 's' : ''} — page {current} sur {last}
      </small>
      <ul className="pagination pagination-sm mb-0">
        <li className={`page-item ${current === 1 ? 'disabled' : ''}`}>
          <button type="button" className="page-link" onClick={() => onPage(current - 1)} aria-label="Page précédente">
            <i className="bi bi-chevron-left" />
          </button>
        </li>
        {pages.map((p, i) =>
          p === '…' ? (
            <li key={`gap-${i}`} className="page-item disabled">
              <span className="page-link">…</span>
            </li>
          ) : (
            <li key={p} className={`page-item ${p === current ? 'active' : ''}`}>
              <button type="button" className="page-link" onClick={() => onPage(p)}>
                {p}
              </button>
            </li>
          ),
        )}
        <li className={`page-item ${current === last ? 'disabled' : ''}`}>
          <button type="button" className="page-link" onClick={() => onPage(current + 1)} aria-label="Page suivante">
            <i className="bi bi-chevron-right" />
          </button>
        </li>
      </ul>
    </nav>
  )
}
