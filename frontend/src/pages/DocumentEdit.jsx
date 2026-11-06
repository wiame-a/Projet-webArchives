import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { documentsApi } from '../api/services'
import { parseApiError } from '../utils/errors'
import DocumentForm from '../components/DocumentForm'
import ErrorAlert from '../components/ErrorAlert'
import Spinner from '../components/Spinner'

export default function DocumentEdit() {
  const { id } = useParams()
  const [doc, setDoc] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let active = true
    documentsApi
      .get(id)
      .then((res) => active && setDoc(res.data.data))
      .catch((err) => active && setError(parseApiError(err).message))
    return () => {
      active = false
    }
  }, [id])

  if (error) {
    return (
      <>
        <ErrorAlert message={error} />
        <Link to="/documents">← Retour aux documents</Link>
      </>
    )
  }
  if (!doc) return <Spinner />

  if (!doc.can.update) {
    return (
      <div className="panel panel-body">
        <p className="mb-2">
          <i className="bi bi-lock me-2" />
          {doc.statut === 'archive'
            ? 'Ce document est archivé : il est en lecture seule. Restaurez-le pour le modifier.'
            : "Vous n'avez pas le droit de modifier ce document."}
        </p>
        <Link to={`/documents/${doc.id}`}>← Retour au document</Link>
      </div>
    )
  }

  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Modifier le document</h1>
          <p className="page-sub">Pour remplacer le fichier, ajoutez une nouvelle version depuis la page du document.</p>
        </div>
      </div>
      <DocumentForm document={doc} />
    </>
  )
}
