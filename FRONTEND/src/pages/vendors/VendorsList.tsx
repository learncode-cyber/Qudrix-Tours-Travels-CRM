import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../../lib/api'
import { Button, Card, Badge, EmptyState, Spinner } from '../../components/ui'
import { Plus } from 'lucide-react'

interface Vendor {
  id: number
  name: string
  category: string
  email: string | null
  phone: string | null
  status: string
}

export default function VendorsList() {
  const [vendors, setVendors] = useState<Vendor[]>([])
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setLoading(true)
    api
      .get('/vendors', { params: { page } })
      .then((res) => {
        setVendors(res.data.data)
        setLastPage(res.data.pagination.last_page)
        setError(null)
      })
      .catch(() => setError('Could not load vendors.'))
      .finally(() => setLoading(false))
  }, [page])

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold text-ink">Vendors</h1>
        <Link to="/vendors/new">
          <Button>
            <Plus size={16} /> New vendor
          </Button>
        </Link>
      </div>

      <Card>
        {loading ? (
          <Spinner />
        ) : error ? (
          <p className="p-6 text-sm text-danger">{error}</p>
        ) : vendors.length === 0 ? (
          <EmptyState title="No vendors yet" description="Non-travel operational vendors (printing, marketing, software…) will show up here." />
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-ink-soft">
                <th className="px-4 py-3 font-medium">Name</th>
                <th className="px-4 py-3 font-medium">Category</th>
                <th className="px-4 py-3 font-medium">Contact</th>
                <th className="px-4 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {vendors.map((v) => (
                <tr key={v.id} className="border-b border-line last:border-0 hover:bg-canvas/50">
                  <td className="px-4 py-3 font-medium text-ink">{v.name}</td>
                  <td className="px-4 py-3 text-ink-soft capitalize">{v.category.replace('_', ' ')}</td>
                  <td className="px-4 py-3 text-ink-soft">{v.email || v.phone || '—'}</td>
                  <td className="px-4 py-3">
                    <Badge tone={v.status === 'active' ? 'success' : 'default'}>{v.status}</Badge>
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
