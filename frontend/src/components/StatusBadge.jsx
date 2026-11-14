const STATUSES = {
  valide: { label: 'Validé', icon: 'bi-check-circle-fill', cls: 'badge-valide' },
  en_attente: { label: 'En attente', icon: 'bi-hourglass-split', cls: 'badge-attente' },
  archive: { label: 'Archivé', icon: 'bi-archive-fill', cls: 'badge-archive' },
  rejete: { label: 'Rejeté', icon: 'bi-x-circle-fill', cls: 'badge-rejete' },
}

/**
 * Pastille de statut d'un document : Vert = Validé, Jaune = En attente,
 * Bleu = Archivé, Rouge = Rejeté.
 * `status` vient de l'API (en_attente | valide | rejete | archive).
 * `statut` (actif | archive) n'est qu'un repli pour une ancienne réponse d'API.
 */
export function StatusBadge({ status, statut }) {
  const key = status ?? (statut === 'archive' ? 'archive' : 'valide')
  const s = STATUSES[key] ?? STATUSES.en_attente
  return (
    <span className={`badge badge-status ${s.cls}`}>
      <i className={`bi ${s.icon}`} />
      {s.label}
    </span>
  )
}

export const STATUS_OPTIONS = Object.entries(STATUSES).map(([value, s]) => ({ value, label: s.label }))

const RIGHT_LABELS = {
  admin: 'Administrateur',
  archiviste: 'Archiviste',
  proprietaire: 'Propriétaire',
  modification: 'Modification',
  lecture: 'Lecture seule',
}

export function RightBadge({ right }) {
  if (!right) return null
  return <span className="badge badge-right">{RIGHT_LABELS[right] ?? right}</span>
}
