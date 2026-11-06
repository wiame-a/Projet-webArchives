export default function Spinner({ label = 'Chargement…', small = false }) {
  return (
    <div className={`d-flex align-items-center justify-content-center gap-2 text-muted ${small ? 'py-2' : 'py-5'}`}>
      <div className="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
      <span>{label}</span>
    </div>
  )
}
