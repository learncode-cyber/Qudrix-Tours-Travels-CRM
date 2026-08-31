import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { getProfile, updateProfile } from '../api/endpoints'
import { ErrorBanner, Loading } from '../components/ui'
import { getErrorMessage } from '../utils/format'

export default function ProfilePage() {
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [telegramChatId, setTelegramChatId] = useState('')
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)

  useEffect(() => {
    getProfile()
      .then((res) => {
        const user = res.data.user
        setName(String(user.name ?? ''))
        setPhone(String(user.phone ?? ''))
        setTelegramChatId(String(user.telegram_chat_id ?? ''))
      })
      .catch((err) => setError(getErrorMessage(err, 'Unable to load your profile.')))
      .finally(() => setLoading(false))
  }, [])

  async function handleSave(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setSaveError(null)
    setSaved(false)
    try {
      await updateProfile({
        name,
        phone: phone || undefined,
        telegram_chat_id: telegramChatId || undefined,
      })
      setSaved(true)
    } catch (err) {
      setSaveError(getErrorMessage(err, 'Unable to save your profile.'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Profile</h1>
      </div>

      {loading ? (
        <Loading />
      ) : error ? (
        <ErrorBanner message={error} />
      ) : (
        <form onSubmit={handleSave} className="stacked-form">
          {saveError ? <div className="state-block error">{saveError}</div> : null}
          {saved ? <div className="state-block success">Profile saved.</div> : null}
          <label className="field">
            <span>Name</span>
            <input required value={name} onChange={(e) => setName(e.target.value)} />
          </label>
          <label className="field">
            <span>Phone</span>
            <input value={phone} onChange={(e) => setPhone(e.target.value)} />
          </label>
          <label className="field">
            <span>Telegram Chat ID</span>
            <input value={telegramChatId} onChange={(e) => setTelegramChatId(e.target.value)} />
            <p className="field-hint">
              Notifications sent with the Telegram channel are delivered here. Find your chat id by
              messaging the configured bot and checking its update log, or by asking your
              administrator.
            </p>
          </label>
          <div className="modal-actions">
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? 'Saving...' : 'Save Profile'}
            </button>
          </div>
        </form>
      )}
    </div>
  )
}
