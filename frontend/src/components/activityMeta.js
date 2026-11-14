/** Présentation des actions du journal : libellé, icône Bootstrap et teinte. */
export const ACTION_META = {
  'document.created': { label: 'Création', icon: 'bi-file-earmark-plus', tone: 'blue' },
  'document.updated': { label: 'Modification', icon: 'bi-pencil', tone: 'gray' },
  'document.deleted': { label: 'Suppression', icon: 'bi-trash', tone: 'red' },
  'document.archived': { label: 'Archivage', icon: 'bi-archive', tone: 'blue' },
  'document.restored': { label: 'Restauration', icon: 'bi-arrow-counterclockwise', tone: 'gray' },
  'document.validated': { label: 'Validation', icon: 'bi-check-circle', tone: 'green' },
  'document.rejected': { label: 'Rejet', icon: 'bi-x-circle', tone: 'red' },
  'document.reopened': { label: 'Réouverture', icon: 'bi-arrow-repeat', tone: 'yellow' },
  'version.added': { label: 'Nouvelle version', icon: 'bi-layers', tone: 'blue' },
  'version.downloaded': { label: 'Téléchargement', icon: 'bi-download', tone: 'gray' },
  'share.granted': { label: 'Partage', icon: 'bi-share', tone: 'blue' },
  'share.revoked': { label: 'Partage retiré', icon: 'bi-person-dash', tone: 'gray' },
  'account.password_changed': { label: 'Mot de passe', icon: 'bi-key', tone: 'yellow' },
  'account.profile_updated': { label: 'Profil', icon: 'bi-person-gear', tone: 'gray' },
}

export const ACTION_OPTIONS = Object.entries(ACTION_META).map(([value, m]) => ({ value, label: m.label }))

export function actionMeta(action) {
  return ACTION_META[action] ?? { label: action, icon: 'bi-dot', tone: 'gray' }
}
