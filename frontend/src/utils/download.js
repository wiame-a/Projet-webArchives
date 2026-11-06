import { documentsApi } from '../api/services'

/**
 * Télécharge une version. Le fichier est protégé par l'API : on le récupère en
 * blob avec le token (une simple balise <a href> n'enverrait pas l'en-tête
 * Authorization), puis on déclenche l'enregistrement dans le navigateur.
 */
export async function downloadVersion(documentId, version) {
  const response = await documentsApi.downloadVersion(documentId, version.id)
  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = version.nom_original
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
