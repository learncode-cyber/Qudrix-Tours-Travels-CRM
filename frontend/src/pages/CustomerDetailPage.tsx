import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { getCustomer, getCustomer360 } from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading, NotAvailable } from '../components/ui'
import type { Customer, Customer360Response } from '../types'
import { formatCurrency, formatDate, getErrorMessage, titleCase } from '../utils/format'

type ViewData =
  | { mode: 'full'; data: Customer360Response }
  | { mode: 'basic'; data: Customer }

export default function CustomerDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [view, setView] = useState<ViewData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!id) return
    let cancelled = false

    async function load() {
      setLoading(true)
      setError(null)
      try {
        const res = await getCustomer360(id!)
        if (!cancelled) setView({ mode: 'full', data: res.data.data })
      } catch {
        try {
          const res = await getCustomer(id!)
          if (!cancelled) setView({ mode: 'basic', data: res.data.data })
        } catch (err) {
          if (!cancelled) setError(getErrorMessage(err, 'Unable to load this customer.'))
        }
      } finally {
        if (!cancelled) setLoading(false)
      }
    }

    load()
    return () => {
      cancelled = true
    }
  }, [id])

  if (loading) return <Loading label="Loading customer..." />
  if (error) return <ErrorBanner message={error} />
  if (!view) return <ErrorBanner message="Customer not found." />

  const customer = view.mode === 'full' ? view.data.customer : view.data

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <Link to="/customers" className="back-link">
            ← Back to customers
          </Link>
          <h1>{customer.name}</h1>
        </div>
      </div>

      <section className="panel">
        <h2>Profile</h2>
        <div className="detail-grid">
          <div>
            <span className="detail-label">Email</span>
            <span>{customer.email ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Phone</span>
            <span>{customer.phone ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Type</span>
            <span>{customer.type ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Status</span>
            <span>{customer.status ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Company</span>
            <span>{customer.company ?? '—'}</span>
          </div>
          <div>
            <span className="detail-label">Address</span>
            <span>{customer.address ?? '—'}</span>
          </div>
        </div>
      </section>

      {view.mode === 'basic' ? (
        <section className="panel">
          <NotAvailable label="Full customer 360 view (leads, deals, bookings, communications, notes, tags, timeline)" />
        </section>
      ) : (
        <Customer360Sections data={view.data} />
      )}
    </div>
  )
}

function Customer360Sections({ data }: { data: Customer360Response }) {
  return (
    <>
      <section className="panel">
        <h2>Leads ({data.leads?.length ?? 0})</h2>
        {!data.leads || data.leads.length === 0 ? (
          <EmptyState message="No leads for this customer." />
        ) : (
          <ul className="simple-list">
            {data.leads.map((l) => (
              <li key={l.id}>
                <strong>{l.name}</strong> — {titleCase(l.status)}
                {l.estimated_value ? ` · ${formatCurrency(l.estimated_value)}` : ''}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <h2>Deals ({data.deals?.length ?? 0})</h2>
        {!data.deals || data.deals.length === 0 ? (
          <EmptyState message="No deals for this customer." />
        ) : (
          <ul className="simple-list">
            {data.deals.map((d) => (
              <li key={d.id}>
                <strong>{d.title}</strong> — {titleCase(d.stage)}
                {d.amount ? ` · ${formatCurrency(d.amount)}` : ''}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <h2>Bookings ({data.bookings?.length ?? 0})</h2>
        {!data.bookings || data.bookings.length === 0 ? (
          <EmptyState message="No bookings for this customer." />
        ) : (
          <ul className="simple-list">
            {data.bookings.map((b) => (
              <li key={b.id}>{JSON.stringify(b)}</li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <h2>Quotations ({data.quotations?.length ?? 0})</h2>
        {!data.quotations || data.quotations.length === 0 ? (
          <EmptyState message="No quotations for this customer." />
        ) : (
          <ul className="simple-list">
            {data.quotations.map((q) => (
              <li key={q.id}>{JSON.stringify(q)}</li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <h2>Communications ({data.communications?.length ?? 0})</h2>
        {!data.communications || data.communications.length === 0 ? (
          <EmptyState message="No communications logged." />
        ) : (
          <ul className="simple-list">
            {data.communications.map((c) => (
              <li key={c.id}>{JSON.stringify(c)}</li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <h2>Notes ({data.notes?.length ?? 0})</h2>
        {!data.notes || data.notes.length === 0 ? (
          <EmptyState message="No notes for this customer." />
        ) : (
          <ul className="simple-list">
            {data.notes.map((n) => (
              <li key={n.id}>{JSON.stringify(n)}</li>
            ))}
          </ul>
        )}
      </section>

      <section className="panel">
        <h2>Tags</h2>
        {!data.tags || data.tags.length === 0 ? (
          <EmptyState message="No tags." />
        ) : (
          <div className="tag-list">
            {data.tags.map((t, i) => (
              <span className="badge badge-default" key={i}>
                {typeof t === 'string' ? t : t.name}
              </span>
            ))}
          </div>
        )}
      </section>

      <section className="panel">
        <h2>Timeline</h2>
        {!data.timeline || data.timeline.length === 0 ? (
          <EmptyState message="No timeline events yet." />
        ) : (
          <ul className="timeline-list">
            {data.timeline.map((t, i) => (
              <li key={t.id ?? i}>
                <span className="timeline-date">{formatDate(t.date ?? t.created_at)}</span>
                <span>{t.title ?? t.type ?? t.description ?? 'Event'}</span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </>
  )
}
