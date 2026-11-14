import { Link } from 'react-router-dom'

export default function NotFound() {
  return (
    <div className="page">
      <div className="panel panel-body text-center py-5">
        <i className="bi bi-compass display-5 text-muted" />
        <h1 className="h3 mt-3">Page introuvable</h1>
        <p className="text-muted">L'adresse demandée n'existe pas.</p>
        <Link to="/" className="btn btn-primary">Retour à l'accueil</Link>
      </div>
    </div>
  )
}
