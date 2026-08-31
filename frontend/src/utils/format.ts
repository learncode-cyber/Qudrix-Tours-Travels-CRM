export function formatCurrency(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—'
  const num = typeof value === 'string' ? Number(value) : value
  if (Number.isNaN(num)) return String(value)
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
  }).format(num)
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

export function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—'
  return `${Math.round(value * (value <= 1 ? 100 : 1))}%`
}

export function titleCase(value: string | null | undefined): string {
  if (!value) return '—'
  return value
    .split(/[_\s]+/)
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ')
}

// Maps a status string (quotation/proposal/invoice states, etc.) to a
// Badge `tone` prop value. Unknown statuses fall back to the default tone.
export function statusTone(status: string | null | undefined): string {
  const positive = ['accepted', 'approved', 'sent', 'signed', 'paid', 'won']
  const warning = ['pending_approval', 'draft', 'partially_paid']
  const danger = ['rejected', 'overdue', 'lost']
  const s = (status ?? '').toLowerCase()
  if (positive.includes(s)) return 'success'
  if (warning.includes(s)) return 'warning'
  if (danger.includes(s)) return 'danger'
  return 'default'
}

export function getErrorMessage(err: unknown, fallback = 'Something went wrong.'): string {
  if (err && typeof err === 'object') {
    const anyErr = err as { response?: { data?: { message?: string; error?: string } }; message?: string }
    return anyErr.response?.data?.message ?? anyErr.response?.data?.error ?? anyErr.message ?? fallback
  }
  return fallback
}
