export default function ErrorAlert({ message, onRetry }) {
  if (!message) return null
  return (
    <div className="alert alert-danger d-flex align-items-center justify-content-between gap-3" role="alert">
      <span>
        <i className="bi bi-exclamation-triangle-fill me-2" />
        {message}
      </span>
      {onRetry && (
        <button type="button" className="btn btn-sm btn-outline-danger" onClick={onRetry}>
          Réessayer
        </button>
      )}
    </div>
  )
}
