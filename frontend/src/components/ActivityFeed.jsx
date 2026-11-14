import { Link } from 'react-router-dom'
import { formatDate } from '../utils/format'
import { actionMeta } from './activityMeta'

/** Liste chronologique d'événements (« Marie a validé le document… »). */
export default function ActivityFeed({ items, showDocument = false }) {
  return (
    <ul className="activity-feed list-unstyled mb-0">
      {items.map((item) => {
        const meta = actionMeta(item.action)
        return (
          <li key={item.id} className="activity-item">
            <span className={`activity-icon tone-${meta.tone}`}><i className={`bi ${meta.icon}`} /></span>
            <div className="flex-grow-1">
              <div>
                <strong>{item.user?.name ?? 'Utilisateur supprimé'}</strong> {item.description}
              </div>
              <small className="text-muted">
                {formatDate(item.created_at, true)}
                {showDocument && item.document && (
                  <> · <Link to={`/documents/${item.document.id}`}>{item.document.code}</Link></>
                )}
              </small>
            </div>
          </li>
        )
      })}
    </ul>
  )
}
