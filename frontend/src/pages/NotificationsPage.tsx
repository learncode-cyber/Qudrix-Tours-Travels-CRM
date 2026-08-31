import { useEffect, useState } from 'react'
import { getUnreadNotificationCount, listNotifications, markAllNotificationsRead, markNotificationRead } from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading, NotAvailable } from '../components/ui'
import type { Notification } from '../types'
import { formatDate, getErrorMessage, titleCase } from '../utils/format'

export default function NotificationsPage() {
  const [notifications, setNotifications] = useState<Notification[]>([])
  const [unreadCount, setUnreadCount] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notAvailable, setNotAvailable] = useState(false)
  const [unreadOnly, setUnreadOnly] = useState(false)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [markingAll, setMarkingAll] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  async function load() {
    setLoading(true)
    setError(null)
    setNotAvailable(false)
    try {
      const [listRes, countRes] = await Promise.all([
        listNotifications(unreadOnly),
        getUnreadNotificationCount(),
      ])
      setNotifications(listRes.data.data ?? [])
      setUnreadCount(countRes.data.data.unread_count)
    } catch (err) {
      const anyErr = err as { response?: { status?: number } }
      if (anyErr.response?.status === 404) {
        setNotAvailable(true)
      } else {
        setError(getErrorMessage(err, 'Unable to load notifications.'))
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [unreadOnly])

  async function handleMarkRead(n: Notification) {
    setBusyId(n.id)
    setActionError(null)
    try {
      await markNotificationRead(n.id)
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to mark this notification read.'))
    } finally {
      setBusyId(null)
    }
  }

  async function handleMarkAllRead() {
    setMarkingAll(true)
    setActionError(null)
    try {
      await markAllNotificationsRead()
      await load()
    } catch (err) {
      setActionError(getErrorMessage(err, 'Unable to mark all notifications read.'))
    } finally {
      setMarkingAll(false)
    }
  }

  if (notAvailable) return <NotAvailable label="Notifications" />

  return (
    <div className="page">
      <div className="page-header">
        <h1>Notifications{unreadCount !== null ? ` (${unreadCount} unread)` : ''}</h1>
        <div className="header-actions">
          <button type="button" className="btn btn-ghost" onClick={handleMarkAllRead} disabled={markingAll}>
            {markingAll ? 'Marking...' : 'Mark All Read'}
          </button>
        </div>
      </div>
      <div className="view-toggle">
        <button type="button" className={!unreadOnly ? 'active' : ''} onClick={() => setUnreadOnly(false)}>
          All
        </button>
        <button type="button" className={unreadOnly ? 'active' : ''} onClick={() => setUnreadOnly(true)}>
          Unread Only
        </button>
      </div>

      {actionError ? <ErrorBanner message={actionError} /> : null}

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : notifications.length === 0 ? (
        <EmptyState message="No notifications." />
      ) : (
        <ul className="simple-list">
          {notifications.map((n) => (
            <li key={n.id} className={n.read_at ? '' : 'unread'}>
              <div>
                <strong>{n.title}</strong> <span className="field-hint">({titleCase(n.type)})</span>
                <p>{n.message}</p>
                <span className="field-hint">{formatDate(n.created_at)}</span>
              </div>
              {!n.read_at ? (
                <button
                  type="button"
                  className="btn btn-ghost btn-sm"
                  disabled={busyId === n.id}
                  onClick={() => handleMarkRead(n)}
                >
                  Mark Read
                </button>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
