export function StatusBadge({ statut }) {
  return statut === 'archive' ? (
    <span className="badge badge-status badge-archive">
      <i className="bi bi-archive me-1" />
      Archivé
    </span>
  ) : (
    <span className="badge badge-status badge-actif">Actif</span>
  )
}

const RIGHT_LABELS = {
  admin: 'Administrateur',
  proprietaire: 'Propriétaire',
  modification: 'Modification',
  lecture: 'Lecture seule',
}

export function RightBadge({ right }) {
  if (!right) return null
  return <span className="badge badge-right">{RIGHT_LABELS[right] ?? right}</span>
}
