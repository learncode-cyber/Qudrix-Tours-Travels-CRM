import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Button, Input, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus, Search } from 'lucide-react'

interface Customer {
  id: number
  name: string
  email: string | null
  phone: string | null
  customer_type: string
  status: string
  country: string | null
}

export default function CustomersList() {
  const [customers, setCustomers] = useState<Customer[]>([])
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setLoading(true)
    const timeout = setTimeout(() => {
      api
        .get('/customers', { params: { search: search || undefined, page } })
        .then((res) => {
          setCustomers(res.data.data)
          setLastPage(res.data.pagination.last_page)
          setError(null)
        })
        .catch(() => setError('Could not load customers.'))
        .finally(() => setLoading(false))
    }, 300)

    return () => clearTimeout(timeout)
  }, [search, page])

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">Customers</h1>
        <Link to="/customers/new">
          <Button>
            <Plus size={16} /> New customer
          </Button>
        </Link>
      </div>

      <div className="mb-4 relative max-w-sm">
        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink-soft" />
        <Input
          placeholder="Search by name, email, or phone…"
          className="pl-9"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value)
            setPage(1)
          }}
        />
      </div>

      <Card>
        {loading ? (
          <Spinner />
        ) : error ? (
          <p className="p-6 text-sm text-danger">{error}</p>
        ) : customers.length === 0 ? (
          <EmptyState
            title={search ? 'No customers match your search' : 'No customers yet'}
            description={search ? 'Try a different name, email, or phone number.' : 'Customers you add will show up here.'}
          />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Contact</th>
                <th className="px-4 py-3 font-medium">Type</th>
                <th className="px-4 py-3 font-medium">Country</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {customers.map((c) => (
                <tr key={c.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3">
                    <Link to={`/customers/${c.id}`} className="font-medium text-ink hover:text-teal">
                      {c.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-ink-soft">{c.email || c.phone || '—'}</td>
                  <td className="px-4 py-3 text-ink-soft capitalize">{c.customer_type}</td>
                  <td className="px-4 py-3 text-ink-soft">{c.country || '—'}</td>
                  <td className="px-4 py-3">
                    <Badge tone={c.status === 'active' ? 'success' : 'default'}>{c.status}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      {lastPage > 1 && (
        <div className="flex justify-end gap-2 mt-4">
          <Button variant="secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Previous
          </Button>
          <span className="flex items-center px-2 text-sm text-ink-soft">
            Page {page} of {lastPage}
          </span>
          <Button variant="secondary" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
            Next
          </Button>
        </div>
      )}
    </div>
  )
}
