import { useState } from 'react'
import type { FormEvent } from 'react'
import { aiQualifyLead, aiSuggestReply, aiSummarizeLead } from '../api/endpoints'
import { Badge, ErrorBanner, Loading, Modal } from './ui'
import type { AiLeadQualification, AiLeadSummary, AiSuggestedReply, Lead } from '../types'
import { getErrorMessage, statusTone, titleCase } from '../utils/format'

type Tab = 'qualify' | 'summarize' | 'reply'

export default function AiLeadAssistantModal({ lead, onClose }: { lead: Lead; onClose: () => void }) {
  const [tab, setTab] = useState<Tab>('qualify')

  const [qualification, setQualification] = useState<AiLeadQualification | null>(null)
  const [qualifyBusy, setQualifyBusy] = useState(false)
  const [qualifyError, setQualifyError] = useState<string | null>(null)

  const [summary, setSummary] = useState<AiLeadSummary | null>(null)
  const [summaryBusy, setSummaryBusy] = useState(false)
  const [summaryError, setSummaryError] = useState<string | null>(null)

  const [repIntent, setRepIntent] = useState('')
  const [reply, setReply] = useState<AiSuggestedReply | null>(null)
  const [replyBusy, setReplyBusy] = useState(false)
  const [replyError, setReplyError] = useState<string | null>(null)

  async function runQualify() {
    setQualifyBusy(true)
    setQualifyError(null)
    try {
      const res = await aiQualifyLead(lead.id)
      setQualification(res.data.data)
    } catch (err) {
      setQualifyError(getErrorMessage(err, 'Unable to qualify this lead right now.'))
    } finally {
      setQualifyBusy(false)
    }
  }

  async function runSummarize() {
    setSummaryBusy(true)
    setSummaryError(null)
    try {
      const res = await aiSummarizeLead(lead.id)
      setSummary(res.data.data)
    } catch (err) {
      setSummaryError(getErrorMessage(err, 'Unable to summarize this lead right now.'))
    } finally {
      setSummaryBusy(false)
    }
  }

  async function runSuggestReply(e: FormEvent) {
    e.preventDefault()
    setReplyBusy(true)
    setReplyError(null)
    try {
      const res = await aiSuggestReply(lead.id, repIntent || undefined)
      setReply(res.data.data)
    } catch (err) {
      setReplyError(getErrorMessage(err, 'Unable to draft a reply right now.'))
    } finally {
      setReplyBusy(false)
    }
  }

  return (
    <Modal title={`AI Sales Assistant — ${lead.name}`} onClose={onClose}>
      <p className="field-hint">
        Every result here is a suggestion or draft for a human to review — nothing is sent, scored
        permanently, or applied automatically.
      </p>
      <div className="view-toggle">
        <button type="button" className={tab === 'qualify' ? 'active' : ''} onClick={() => setTab('qualify')}>
          Qualify
        </button>
        <button type="button" className={tab === 'summarize' ? 'active' : ''} onClick={() => setTab('summarize')}>
          Summarize
        </button>
        <button type="button" className={tab === 'reply' ? 'active' : ''} onClick={() => setTab('reply')}>
          Suggest Reply
        </button>
      </div>

      {tab === 'qualify' ? (
        <div className="stacked-form">
          {qualifyError ? <ErrorBanner message={qualifyError} /> : null}
          <button type="button" className="btn btn-primary" onClick={runQualify} disabled={qualifyBusy}>
            {qualifyBusy ? 'Analyzing...' : 'Run Qualification'}
          </button>
          {qualifyBusy ? <Loading /> : null}
          {qualification ? (
            <div className="table-wrap">
              <p>
                Score <strong>{qualification.score ?? '—'}</strong> &middot; Buying intent{' '}
                <Badge text={titleCase(qualification.buying_intent)} tone={statusTone(qualification.buying_intent)} />
              </p>
              <p>{qualification.reasoning}</p>
              {qualification.recommended_next_action ? (
                <p>
                  <strong>Recommended next action:</strong> {qualification.recommended_next_action}
                </p>
              ) : null}
              {qualification.objections_detected && qualification.objections_detected.length > 0 ? (
                <p>
                  <strong>Objections detected:</strong> {qualification.objections_detected.join(', ')}
                </p>
              ) : null}
              {qualification.missing_information && qualification.missing_information.length > 0 ? (
                <p>
                  <strong>Missing information:</strong> {qualification.missing_information.join(', ')}
                </p>
              ) : null}
            </div>
          ) : null}
        </div>
      ) : null}

      {tab === 'summarize' ? (
        <div className="stacked-form">
          {summaryError ? <ErrorBanner message={summaryError} /> : null}
          <button type="button" className="btn btn-primary" onClick={runSummarize} disabled={summaryBusy}>
            {summaryBusy ? 'Summarizing...' : 'Summarize Conversation'}
          </button>
          {summaryBusy ? <Loading /> : null}
          {summary ? (
            summary.message && !summary.summary ? (
              <p className="field-hint">{summary.message}</p>
            ) : (
              <div className="table-wrap">
                <p>{summary.summary}</p>
                {summary.sentiment ? (
                  <p>
                    <strong>Sentiment:</strong> <Badge text={titleCase(summary.sentiment)} tone={statusTone(summary.sentiment)} />
                  </p>
                ) : null}
                {summary.open_questions && summary.open_questions.length > 0 ? (
                  <p>
                    <strong>Open questions:</strong> {summary.open_questions.join(', ')}
                  </p>
                ) : null}
                {summary.commitments_made && summary.commitments_made.length > 0 ? (
                  <p>
                    <strong>Commitments made:</strong> {summary.commitments_made.join(', ')}
                  </p>
                ) : null}
              </div>
            )
          ) : null}
        </div>
      ) : null}

      {tab === 'reply' ? (
        <form onSubmit={runSuggestReply} className="stacked-form">
          {replyError ? <ErrorBanner message={replyError} /> : null}
          <label className="field">
            <span>What should the reply accomplish? (optional)</span>
            <input value={repIntent} onChange={(e) => setRepIntent(e.target.value)} />
          </label>
          <button type="submit" className="btn btn-primary" disabled={replyBusy}>
            {replyBusy ? 'Drafting...' : 'Draft Reply'}
          </button>
          {replyBusy ? <Loading /> : null}
          {reply ? (
            <div className="table-wrap">
              <p>{reply.draft}</p>
              {reply.facts_to_verify_before_sending && reply.facts_to_verify_before_sending.length > 0 ? (
                <p className="field-hint">
                  Verify before sending: {reply.facts_to_verify_before_sending.join(', ')}
                </p>
              ) : null}
              <p className="field-hint">This is a draft — nothing has been sent.</p>
            </div>
          ) : null}
        </form>
      ) : null}
    </Modal>
  )
}
