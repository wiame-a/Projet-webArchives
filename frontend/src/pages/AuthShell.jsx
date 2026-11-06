import BrandLogo from '../components/BrandLogo'

export default function AuthShell({ title, subtitle, children }) {
  return (
    <div className="auth-wrap">
      <aside className="auth-aside">
        <BrandLogo size={56} />
        <h1>
          Archivium <span className="brand-version">v1.0</span>
        </h1>
        <p className="mb-0" style={{ color: 'rgba(255,255,255,.72)', fontSize: '1.05rem' }}>
          Classez, retrouvez et conservez vos documents en toute sécurité.
        </p>
        <ul className="list-unstyled mb-0 mt-2">
          <li><i className="bi bi-folder2-open" />Classement par catégories et mots-clés</li>
          <li><i className="bi bi-clock-history" />Historique complet des versions</li>
          <li><i className="bi bi-people" />Partage en lecture ou en modification</li>
          <li><i className="bi bi-archive" />Archivage et restauration</li>
        </ul>
      </aside>
      <main className="auth-main">
        <div className="auth-card">
          <div className="auth-brand-mobile">
            <BrandLogo size={32} />
            <span className="brand-name">Archivium</span>
          </div>
          <h2 className="h3 fw-bold" style={{ color: 'var(--ink)' }}>{title}</h2>
          <p className="text-muted mb-4">{subtitle}</p>
          {children}
        </div>
      </main>
    </div>
  )
}
