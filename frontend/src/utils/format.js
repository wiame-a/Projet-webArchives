export function formatBytes(bytes) {
  if (bytes === null || bytes === undefined) return '—'
  const units = ['o', 'Ko', 'Mo', 'Go']
  let value = Number(bytes)
  let i = 0
  while (value >= 1024 && i < units.length - 1) {
    value /= 1024
    i++
  }
  return `${i === 0 ? value : value.toFixed(1).replace('.', ',')} ${units[i]}`
}

export function formatDate(value, withTime = false) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString(
    'fr-FR',
    withTime ? { dateStyle: 'medium', timeStyle: 'short' } : { dateStyle: 'medium' },
  )
}

export function splitKeywords(value) {
  return (value ?? '')
    .split(',')
    .map((word) => word.trim())
    .filter(Boolean)
}

/** Icône Bootstrap Icons selon l'extension du fichier. */
export function fileIcon(name = '') {
  const ext = name.split('.').pop()?.toLowerCase()
  if (ext === 'pdf') return 'bi-file-earmark-pdf'
  if (ext === 'docx') return 'bi-file-earmark-word'
  if (ext === 'xlsx') return 'bi-file-earmark-excel'
  if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'bi-file-earmark-image'
  return 'bi-file-earmark'
}

export const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp']
export const MAX_FILE_BYTES = 10 * 1024 * 1024

/** Contrôle rapide côté navigateur (le serveur revalide toujours). Retourne un message ou null. */
export function validateFile(file) {
  if (!file) return 'Choisissez un fichier.'
  const ext = file.name.split('.').pop()?.toLowerCase()
  if (!ALLOWED_EXTENSIONS.includes(ext)) {
    return 'Formats autorisés : PDF, DOCX, XLSX et images (JPG, PNG, GIF, WEBP).'
  }
  if (file.size > MAX_FILE_BYTES) return 'Le fichier ne doit pas dépasser 10 Mo.'
  return null
}
