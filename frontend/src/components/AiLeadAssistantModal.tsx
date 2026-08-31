import { useState } from 'react'
import type { FormEvent } from 'react'
import {
  aiCopilotAssist,
  aiExtractMemory,
  aiQualifyLead,
  aiSuggestReply,
  aiSummarizeLead,
  createCustomerMemory,
} from '../api/endpoints'
import { Badge, ErrorBanner, Loading, Modal } from './ui'
import type {
  AiCopilotAssistResult,
  AiLeadQualification,
  AiLeadSummary,
  AiMemoryCandidate,
  AiMemoryExtractionResult,
  AiSuggestedReply,
  Lead,
} from '../types'
import { getErrorMessage, statusTone, titleCase } from '../utils/format'

type Tab = 'qualify' | 'summarize' | 'reply' | 'copilot' | 'memory'

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

  const [latestMessage, setLatestMessage] = useState('')
  const [copilot, setCopilot] = useState<AiCopilotAssistResult | null>(null)
  const [copilotBusy, setCopilotBusy] = useState(false)
  const [copilotError, setCopilotError] = useState<string | null>(null)

  const [extraction, setExtraction] = useState<AiMemoryExtractionResult | null>(null)
  const [extractBusy, setExtractBusy] = useState(false)
  const [extractError, setExtractError] = useState<string | null>(null)
  const [savingCandidate, setSavingCandidate] = useState<number | null>(null)
  const [savedCandidates, setSavedCandidates] = useState<Set<number>>(new Set())

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

  async function runCopilot(e: FormEvent) {
    e.preventDefault()
    setCopilotBusy(true)
    setCopilotError(null)
    try {
      const res = await aiCopilotAssist(lead.id, latestMessage || undefined)
      setCopilot(res.data.data)
    } catch (err) {
      setCopilotError(getErrorMessage(err, 'Unable to get copilot suggestions right now.'))
    } finally {
      setCopilotBusy(false)
    }
  }

  async function runExtractMemory() {
    setExtractBusy(true)
    setExtractError(null)
    setSavedCandidates(new Set())
    try {
      const res = await aiExtractMemory(lead.id)
      setExtraction(res.data.data)
    } catch (err) {
      setExtractError(getErrorMessage(err, 'Unable to extract memory candidates right now.'))
    } finally {
      setExtractBusy(false)
    }
  }

  async function confirmCandidate(candidate: AiMemoryCandidate, index: number) {
    setSavingCandidate(index)
    try {
      await createCustomerMemory({
        lead_id: lead.id,
        category: candidate.category,
        key: candidate.key,
        value: candidate.value,
        source: 'ai_extracted',
        confidence: candidate.confidence,
        is_sensitive: candidate.possibly_sensitive,
      })
      setSavedCandidates((prev) => new Set(prev).add(index))
    } catch (err) {
      setExtractError(getErrorMessage(err, 'Unable to save this memory entry.'))
    } finally {
      setSavingCandidate(null)
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
        <button type="button" className={tab === 'copilot' ? 'active' : ''} onClick={() => setTab('copilot')}>
          Copilot
        </button>
        <button type="button" className={tab === 'memory' ? 'active' : ''} onClick={() => setTab('memory')}>
          Extract Memory
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

      {tab === 'copilot' ? (
        <form onSubmit={runCopilot} className="stacked-form">
          {copilotError ? <ErrorBanner message={copilotError} /> : null}
          <label className="field">
            <span>Latest customer message (optional)</span>
            <textarea value={latestMessage} onChange={(e) => setLatestMessage(e.target.value)} />
          </label>
          <button type="submit" className="btn btn-primary" disabled={copilotBusy}>
            {copilotBusy ? 'Thinking...' : 'Get Copilot Suggestions'}
          </button>
          {copilotBusy ? <Loading /> : null}
          {copilot ? (
            <div className="table-wrap">
              {copilot.strategy_used ? (
                <p className="field-hint">Strategy: {titleCase(copilot.strategy_used)}</p>
              ) : null}
              {copilot.suggested_next_question ? (
                <p>
                  <strong>Suggested next question:</strong> {copilot.suggested_next_question}
                </p>
              ) : null}
              {copilot.customer_sentiment ? (
                <p>
                  <strong>Sentiment:</strong>{' '}
                  <Badge text={titleCase(copilot.customer_sentiment)} tone={statusTone(copilot.customer_sentiment)} />
                </p>
              ) : null}
              {copilot.objection_handling && copilot.objection_handling.length > 0 ? (
                <ul className="simple-list">
                  {copilot.objection_handling.map((o, i) => (
                    <li key={i}>
                      <strong>{o.objection}:</strong> {o.suggested_response}
                    </li>
                  ))}
                </ul>
              ) : null}
              {copilot.recommended_products && copilot.recommended_products.length > 0 ? (
                <p>
                  <strong>Recommended products:</strong> {copilot.recommended_products.join(', ')}
                </p>
              ) : null}
              {copilot.facts_to_verify && copilot.facts_to_verify.length > 0 ? (
                <p className="field-hint">Verify: {copilot.facts_to_verify.join(', ')}</p>
              ) : null}
              <p className="field-hint">This is a suggestion — you remain in control of what you say.</p>
            </div>
          ) : null}
        </form>
      ) : null}

      {tab === 'memory' ? (
        <div className="stacked-form">
          {extractError ? <ErrorBanner message={extractError} /> : null}
          <button type="button" className="btn btn-primary" onClick={runExtractMemory} disabled={extractBusy}>
            {extractBusy ? 'Extracting...' : 'Extract Memory Candidates'}
          </button>
          {extractBusy ? <Loading /> : null}
          {extraction ? (
            extraction.candidates.length === 0 ? (
              <p className="field-hint">{extraction.message ?? 'No durable facts found to extract.'}</p>
            ) : (
              <ul className="simple-list">
                {extraction.candidates.map((c, i) => (
                  <li key={i}>
                    <div>
                      <strong>{titleCase(c.category)}</strong> — {c.key}: {c.value}
                      {c.possibly_sensitive ? <Badge text="Possibly Sensitive" tone="warning" /> : null}
                    </div>
                    <p className="field-hint">Evidence: {c.evidence}</p>
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      disabled={savingCandidate === i || savedCandidates.has(i)}
                      onClick={() => confirmCandidate(c, i)}
                    >
                      {savedCandidates.has(i) ? 'Saved' : savingCandidate === i ? 'Saving...' : 'Confirm & Save'}
                    </button>
                  </li>
                ))}
              </ul>
            )
          ) : null}
        </div>
      ) : null}
    </Modal>
  )
}
