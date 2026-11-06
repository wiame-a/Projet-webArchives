import { useEffect } from 'react'

/** Titre de l'onglet : « Archivium | <page> ». */
export default function usePageTitle(page) {
  useEffect(() => {
    document.title = page ? `Archivium | ${page}` : 'Archivium | Gestion Sécurisée des Archives'
  }, [page])
}
