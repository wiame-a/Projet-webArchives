/**
 * Transforme une erreur Axios en { message, fields, status }.
 *  - message : texte à afficher
 *  - fields  : erreurs de validation 422 par champ ({ titre: 'Le titre est obligatoire.' })
 */
export function parseApiError(error) {
  const response = error?.response

  if (!response) {
    return {
      message: "Impossible de joindre le serveur. Vérifiez que l'API Laravel est démarrée.",
      fields: {},
      status: 0,
    }
  }

  // Les téléchargements sont demandés en blob : le corps d'erreur est alors un Blob.
  if (response.data instanceof Blob) {
    const byStatus = {
      403: "Vous n'avez pas accès à ce fichier.",
      404: 'Fichier introuvable sur le serveur.',
    }
    return { message: byStatus[response.status] ?? 'Le téléchargement a échoué.', fields: {}, status: response.status }
  }

  const data = response.data ?? {}
  const fields = {}
  if (data.errors && typeof data.errors === 'object') {
    for (const [name, messages] of Object.entries(data.errors)) {
      fields[name] = Array.isArray(messages) ? messages[0] : String(messages)
    }
  }

  let message = data.message
  if (response.status === 422 && Object.keys(fields).length > 0) {
    message = 'Veuillez corriger les erreurs signalées.'
  } else if (!message) {
    message = {
      401: 'Session expirée. Veuillez vous reconnecter.',
      403: "Vous n'avez pas le droit d'effectuer cette action.",
      404: 'Élément introuvable.',
      413: 'Le fichier est trop volumineux.',
      429: 'Trop de tentatives. Réessayez dans une minute.',
    }[response.status] ?? 'Une erreur est survenue.'
  }

  return { message, fields, status: response.status }
}
