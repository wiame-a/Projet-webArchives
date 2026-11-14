import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
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
const STAFF_NAV = [{ to: '/activity', label: "Journal d'activité", icon: 'bi-clock-history' }]
const ROLE_LABELS = { admin: 'Administrateur', archiviste: 'Archiviste' }

/** Titre de la section courante, affiché dans la barre supérieure. */
function sectionTitle(pathname, items) {
  if (pathname === '/') return 'Tableau de bord'
  const match = items.find((i) => !i.end && pathname.startsWith(i.to))
  return match?.label ?? 'Archivium'
}

function NavSection({ title, items, onNavigate }) {
  return (
    <>
      {title && <div className="sidebar-section">{title}</div>}
      <ul className="sidebar-nav">
        {items.map((item) => (
          <li key={item.to}>
            <NavLink
              to={item.to}
              end={item.end}
              className={({ isActive }) => `sidebar-link ${isActive ? 'active' : ''}`}
              onClick={onNavigate}
            >
              <i className={`bi ${item.icon}`} />
              <span>{item.label}</span>
            </NavLink>
          </li>
        ))}
      </ul>
    </>
  )
}

export default function Layout() {
  const { user, isAdmin, isStaff, logout } = useAuth()
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const [open, setOpen] = useState(false)

  // Le menu mobile se referme quand on change de page.
  useEffect(() => setOpen(false), [pathname])

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
    <div className="app-shell">
      <aside className={`sidebar ${open ? 'open' : ''}`} aria-label="Navigation principale">
        <Link className="sidebar-brand" to="/">
          <BrandLogo size={32} />
          <span className="brand-name">Archivium</span>
        </Link>

        <nav>
          <NavSection title="Navigation" items={NAV} />
          {isStaff && <NavSection title="Administration" items={[...STAFF_NAV, ...(isAdmin ? ADMIN_NAV : [])]} />}
        </nav>

        <div className="sidebar-footer">
          <span className="brand-version">v1.0</span>
          <span>Gestion électronique des documents</span>
        </div>
      </aside>
      {open && <div className="sidebar-backdrop" onClick={() => setOpen(false)} aria-hidden="true" />}

      <div className="app-main">
        <header className="topbar">
          <button type="button" className="nav-toggle d-lg-none" aria-label="Afficher le menu" aria-expanded={open} onClick={() => setOpen((v) => !v)}>
            <i className={`bi ${open ? 'bi-x-lg' : 'bi-list'}`} />
          </button>
          <div className="topbar-title">{sectionTitle(pathname, [...NAV, ...STAFF_NAV, ...ADMIN_NAV, { to: '/profile', label: 'Mon profil' }])}</div>

          <div className="topbar-user">
            <Link className="user-chip" to="/profile" title="Mon profil">
              <span className="user-avatar">{initials}</span>
              <span className="d-none d-sm-flex flex-column lh-sm">
                <span className="user-name">{user?.name}</span>
                <small className="user-role">{ROLE_LABELS[user?.role] ?? 'Utilisateur'}</small>
              </span>
            </Link>
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onLogout}>
              <i className="bi bi-box-arrow-right me-1" />
              <span className="d-none d-sm-inline">Déconnexion</span>
            </button>
          </div>
        </header>

        <main className="page">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
