import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { completeTask, createTask, listTasks } from '../api/endpoints'
import { EmptyState, ErrorBanner, Loading, Modal } from '../components/ui'
import type { Task } from '../types'
import { formatDate, getErrorMessage } from '../utils/format'

const emptyForm = { title: '', description: '', due_date: '' }

function isDone(task: Task): boolean {
  return !!task.completed || task.status === 'completed' || task.status === 'done'
}

export default function TasksPage() {
  const [tasks, setTasks] = useState<Task[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [showForm, setShowForm] = useState(false)
  const [form, setForm] = useState(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [togglingId, setTogglingId] = useState<number | null>(null)
  const [filter, setFilter] = useState<'all' | 'pending' | 'completed'>('all')

  async function load() {
    setLoading(true)
    setError(null)
    try {
      const res = await listTasks()
      setTasks(res.data.data ?? [])
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to load tasks.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  async function handleCreate(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await createTask(form)
      setForm(emptyForm)
      setShowForm(false)
      await load()
    } catch (err) {
      setFormError(getErrorMessage(err, 'Unable to create task.'))
    } finally {
      setSaving(false)
    }
  }

  async function handleComplete(task: Task) {
    if (isDone(task)) return
    setTogglingId(task.id)
    try {
      await completeTask(task.id)
      await load()
    } catch (err) {
      setError(getErrorMessage(err, 'Unable to update task.'))
    } finally {
      setTogglingId(null)
    }
  }

  const filtered = tasks.filter((t) => {
    if (filter === 'pending') return !isDone(t)
    if (filter === 'completed') return isDone(t)
    return true
  })

  return (
    <div className="page">
      <div className="page-header">
        <h1>Tasks</h1>
        <div className="header-actions">
          <div className="view-toggle">
            <button
              type="button"
              className={filter === 'all' ? 'active' : ''}
              onClick={() => setFilter('all')}
            >
              All
            </button>
            <button
              type="button"
              className={filter === 'pending' ? 'active' : ''}
              onClick={() => setFilter('pending')}
            >
              Pending
            </button>
            <button
              type="button"
              className={filter === 'completed' ? 'active' : ''}
              onClick={() => setFilter('completed')}
            >
              Completed
            </button>
          </div>
          <button type="button" className="btn btn-primary" onClick={() => setShowForm(true)}>
            + New Task
          </button>
        </div>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : filtered.length === 0 ? (
        <EmptyState message="No tasks found." />
      ) : (
        <ul className="task-list">
          {filtered.map((task) => (
            <li className={'task-row' + (isDone(task) ? ' done' : '')} key={task.id}>
              <label className="task-check">
                <input
                  type="checkbox"
                  checked={isDone(task)}
                  disabled={isDone(task) || togglingId === task.id}
                  onChange={() => handleComplete(task)}
                />
              </label>
              <div className="task-body">
                <div className="task-title">{task.title}</div>
                {task.description ? <div className="task-desc">{task.description}</div> : null}
              </div>
              <div className="task-due">{formatDate(task.due_date)}</div>
            </li>
          ))}
        </ul>
      )}

      {showForm ? (
        <Modal title="New Task" onClose={() => setShowForm(false)}>
          <form onSubmit={handleCreate} className="stacked-form">
            {formError ? <div className="state-block error">{formError}</div> : null}
            <label className="field">
              <span>Title</span>
              <input
                required
                value={form.title}
                onChange={(e) => setForm({ ...form, title: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Description</span>
              <textarea
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Due Date</span>
              <input
                type="date"
                value={form.due_date}
                onChange={(e) => setForm({ ...form, due_date: e.target.value })}
              />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowForm(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving...' : 'Create Task'}
              </button>
            </div>
          </form>
        </Modal>
      ) : null}
    </div>
  )
}
