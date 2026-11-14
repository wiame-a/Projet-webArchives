import { useState } from 'react'
import Modal from './Modal'

const MIN = 5
const MAX = 500

/** Saisie du motif de rejet (obligatoire : l'auteur doit savoir quoi corriger). */
export default function RejectModal({ busy, error, onSubmit, onCancel }) {
  const [motif, setMotif] = useState('')
  const [touched, setTouched] = useState(false)

  const trimmed = motif.trim()
  const problem = trimmed.length === 0 ? 'Le motif du rejet est obligatoire.'
    : trimmed.length < MIN ? `Le motif doit contenir au moins ${MIN} caractères.` : ''

  const submit = (e) => {
    e.preventDefault()
    setTouched(true)
    if (!problem) onSubmit(trimmed)
  }

  return (
    <Modal
      title="Rejeter le document"
      onClose={busy ? () => {} : onCancel}
      footer={
        <>
          <button type="button" className="btn btn-light" onClick={onCancel} disabled={busy}>Annuler</button>
          <button type="submit" form="reject-form" className="btn btn-danger" disabled={busy}>
            {busy && <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />}
            Rejeter
          </button>
        </>
      }
    >
      <form id="reject-form" onSubmit={submit} noValidate>
        <label htmlFor="motif" className="form-label fw-semibold">Motif du rejet</label>
        <textarea
          id="motif"
          className={`form-control ${touched && (problem || error) ? 'is-invalid' : ''}`}
          rows={4}
          maxLength={MAX}
          value={motif}
          onChange={(e) => setMotif(e.target.value)}
          placeholder="Ex. Pièce illisible, merci de fournir une version corrigée."
          autoFocus
        />
        <div className="invalid-feedback">{problem || error}</div>
        <div className="form-text text-end">{trimmed.length} / {MAX}</div>
        <p className="small text-muted mb-0">Ce motif sera visible par l'auteur du document.</p>
      </form>
    </Modal>
  )
}
