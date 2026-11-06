import { useEffect, useRef, useState } from 'react'
import { formatBytes, validateFile } from '../utils/format'

export const ACCEPT = '.pdf,.docx,.xlsx,.jpg,.jpeg,.png,.gif,.webp'

/**
 * Zone de dépôt de fichier : glisser-déposer ou clic, aperçu du fichier choisi
 * (nom, taille, validité) et barre de progression pendant l'envoi.
 * La validation reste dans le parent (onFile reçoit le fichier ou null).
 */
export default function FileDropzone({ id, file, onFile, error, disabled = false, progress = null, compact = false }) {
  const input = useRef(null)
  const [over, setOver] = useState(false)

  // Quand le parent réinitialise le fichier, on vide aussi le champ natif.
  useEffect(() => {
    if (!file && input.current) input.current.value = ''
  }, [file])

  const valid = file && !validateFile(file)
  const pick = () => !disabled && input.current?.click()

  const onDrop = (e) => {
    e.preventDefault()
    setOver(false)
    if (disabled) return
    const dropped = e.dataTransfer?.files?.[0]
    if (dropped) onFile(dropped)
  }

  return (
    <div>
      <div
        className={`dropzone ${compact ? 'dropzone-compact' : ''} ${over ? 'is-over' : ''} ${error ? 'has-error' : ''} ${file ? 'has-file' : ''}`}
        role="button"
        tabIndex={disabled ? -1 : 0}
        aria-disabled={disabled}
        onClick={pick}
        onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && (e.preventDefault(), pick())}
        onDragOver={(e) => {
          e.preventDefault()
          if (!disabled) setOver(true)
        }}
        onDragLeave={() => setOver(false)}
        onDrop={onDrop}
      >
        <input
          id={id}
          ref={input}
          type="file"
          className="visually-hidden"
          accept={ACCEPT}
          disabled={disabled}
          tabIndex={-1}
          onClick={(e) => e.stopPropagation()}
          onChange={(e) => onFile(e.target.files?.[0] ?? null)}
        />

        {file ? (
          <div className="dz-file">
            <span className={`dz-file-icon ${valid ? '' : 'bad'}`}>
              <i className={`bi ${valid ? 'bi-file-earmark-check' : 'bi-file-earmark-x'}`} />
            </span>
            <div className="dz-file-info">
              <div className="dz-file-name">{file.name}</div>
              <div className="dz-file-size">{formatBytes(file.size)}</div>
            </div>
            {!disabled && (
              <button
                type="button"
                className="btn btn-sm btn-light"
                title="Retirer le fichier"
                aria-label="Retirer le fichier"
                onClick={(e) => {
                  e.stopPropagation()
                  onFile(null)
                }}
              >
                <i className="bi bi-x-lg" />
              </button>
            )}
          </div>
        ) : (
          <div className="dz-empty">
            <span className="dz-cloud"><i className="bi bi-cloud-arrow-up" /></span>
            <div>
              <strong>Glissez un fichier ici</strong> ou <span className="dz-link">parcourez</span>
              <div className="dz-hint">PDF, DOCX, XLSX ou image, 10 Mo maximum</div>
            </div>
          </div>
        )}

        {progress !== null && (
          <div className="dz-progress" role="progressbar" aria-valuenow={progress} aria-valuemin="0" aria-valuemax="100">
            <div className="dz-progress-bar" style={{ width: `${progress}%` }} />
            <span className="dz-progress-text">{progress}%</span>
          </div>
        )}
      </div>
      {error && <div className="text-danger small mt-1">{error}</div>}
    </div>
  )
}
