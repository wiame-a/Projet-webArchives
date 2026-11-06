import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import Spinner from './Spinner'

/**
 * Protège un groupe de routes :
 *  - non connecté  -> redirection vers /login (on mémorise la page demandée)
 *  - rôle non autorisé -> écran "Accès refusé"
 */
export default function ProtectedRoute({ roles }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) return <Spinner label="Vérification de la session…" />
  if (!user) return <Navigate to="/login" replace state={{ from: location }} />

  if (roles && !roles.includes(user.role)) {
    return (
      <div className="container page">
        <div className="panel panel-body text-center py-5">
          <i className="bi bi-shield-lock display-5 text-muted" />
          <h2 className="h4 mt-3">Accès refusé</h2>
          <p className="text-muted mb-0">Cette section est réservée aux administrateurs.</p>
        </div>
      </div>
    )
  }

  return <Outlet />
}
