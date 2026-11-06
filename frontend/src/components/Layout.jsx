import { useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import BrandLogo from './BrandLogo'

const NAV = [
  { to: '/', label: 'Tableau de bord', icon: 'bi-speedometer2', end: true },
  { to: '/documents', label: 'Documents', icon: 'bi-folder2-open' },
  { to: '/search', label: 'Recherche', icon: 'bi-search' },
  { to: '/archives', label: 'Archives', icon: 'bi-archive' },
]
const ADMIN_NAV = [
  { to: '/categories', label: 'Catégories', icon: 'bi-tags' },
  { to: '/users', label: 'Utilisateurs', icon: 'bi-people' },
]

export default function Layout() {
  const { user, isAdmin, logout } = useAuth()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)

  const items = isAdmin ? [...NAV, ...ADMIN_NAV] : NAV
  const initials = (user?.name ?? '?')
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()

  const onLogout = async () => {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <>
      <nav className="navbar navbar-expand-xl app-nav sticky-top">
        <div className="container-fluid px-xl-4">
          <Link className="navbar-brand" to="/" onClick={() => setOpen(false)}>
            <BrandLogo size={34} />
            <span className="brand-name">Archivium</span>
            <span className="brand-version">v1.0</span>
          </Link>
          <button
            type="button"
            className="nav-toggle d-xl-none"
            aria-label="Afficher le menu"
            aria-expanded={open}
            onClick={() => setOpen((v) => !v)}
          >
            <i className={`bi ${open ? 'bi-x-lg' : 'bi-list'}`} />
          </button>

          <div className={`collapse navbar-collapse ${open ? 'show' : ''}`}>
            <ul className="navbar-nav me-auto mb-2 mb-xl-0 ms-xl-3 gap-xl-1">
              {items.map((item) => (
                <li className="nav-item" key={item.to}>
                  <NavLink
                    to={item.to}
                    end={item.end}
                    className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}
                    onClick={() => setOpen(false)}
                  >
                    <i className={`bi ${item.icon} me-2`} />
                    {item.label}
                  </NavLink>
                </li>
              ))}
            </ul>

            <div className="d-flex align-items-center gap-3 pb-2 pb-xl-0">
              <div className="user-chip">
                <span className="user-avatar">{initials}</span>
                <span className="d-flex flex-column lh-sm">
                  <span className="user-name">{user?.name}</span>
                  <small className="user-role">{isAdmin ? 'Administrateur' : 'Utilisateur'}</small>
                </span>
              </div>
              <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onLogout}>
                <i className="bi bi-box-arrow-right me-1" />
                Déconnexion
              </button>
            </div>
          </div>
        </div>
      </nav>

      <main className="container page">
        <Outlet />
      </main>
    </>
  )
}
