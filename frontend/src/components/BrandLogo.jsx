/**
 * Logo Archivium : un dossier verrouillé (coffre d'archives) sur fond bleu.
 * Dessiné en SVG inline : aucune image à charger, couleurs pilotées par le CSS.
 */
export default function BrandLogo({ size = 32 }) {
  return (
    <svg
      className="brand-logo"
      width={size}
      height={size}
      viewBox="0 0 32 32"
      role="img"
      aria-label="Archivium"
    >
      <defs>
        <linearGradient id="archivium-grad" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#3b82f6" />
          <stop offset="1" stopColor="#1d4ed8" />
        </linearGradient>
      </defs>
      <rect width="32" height="32" rx="9" fill="url(#archivium-grad)" />
      <path
        d="M7 12.5a2 2 0 0 1 2-2h4.2l2 2.3H23a2 2 0 0 1 2 2V22a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2z"
        fill="#fff"
      />
      <circle cx="16" cy="17.6" r="1.9" fill="#2563eb" />
      <rect x="15.3" y="18.2" width="1.4" height="3.2" rx=".7" fill="#2563eb" />
    </svg>
  )
}
