import DocumentForm from '../components/DocumentForm'

export default function DocumentNew() {
  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Nouveau document</h1>
          <p className="page-sub">Le fichier envoyé devient la version 1 du document.</p>
        </div>
      </div>
      <DocumentForm />
    </>
  )
}
