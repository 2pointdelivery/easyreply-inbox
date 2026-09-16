import { router } from '@inertiajs/react'
import { useEffect, useState } from 'react'
import { api } from '../lib/api'

export default function ComposeBox({ conversationId, pendingDraft }) {
  const [body, setBody] = useState(pendingDraft?.body ?? '')
  const [draftId, setDraftId] = useState(pendingDraft?.id ?? null)
  const [drafting, setDrafting] = useState(false)
  const [sending, setSending] = useState(false)
  const [draftUnavailable, setDraftUnavailable] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    setBody(pendingDraft?.body ?? '')
    setDraftId(pendingDraft?.id ?? null)
  }, [pendingDraft?.id])

  async function requestAiDraft() {
    setDrafting(true)
    setDraftUnavailable(false)
    setError(null)

    try {
      const { draft } = await api.post(`/shared-inbox/conversations/${conversationId}/ai-draft`)

      if (draft) {
        setBody(draft.body)
        setDraftId(draft.id)
      } else {
        setDraftUnavailable(true)
      }
    } catch {
      setError('Could not generate a draft. Try again.')
    } finally {
      setDrafting(false)
    }
  }

  async function submit(event) {
    event.preventDefault()
    if (body.trim() === '') return

    setSending(true)
    setError(null)

    try {
      if (draftId) {
        await api.patch(`/shared-inbox/messages/${draftId}/send`, { body })
      } else {
        await api.post(`/shared-inbox/conversations/${conversationId}/messages`, { body })
      }

      setBody('')
      setDraftId(null)
      router.reload({ only: ['conversation'] })
    } catch {
      setError('Could not send that message. Try again.')
    } finally {
      setSending(false)
    }
  }

  return (
    <form onSubmit={submit} className="flex flex-col gap-2 rounded-lg border border-gray-200 p-3">
      {draftId && (
        <span className="w-fit rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
          AI draft &mdash; review before sending
        </span>
      )}
      {draftUnavailable && (
        <p className="text-sm text-gray-500">AI drafting isn&apos;t configured for this inbox.</p>
      )}
      {error && <p className="text-sm text-red-600">{error}</p>}
      <textarea
        className="min-h-[96px] w-full resize-y rounded-md border-gray-300 text-sm"
        placeholder="Write a reply..."
        value={body}
        onChange={(event) => setBody(event.target.value)}
      />
      <div className="flex items-center justify-between">
        <button
          type="button"
          onClick={requestAiDraft}
          disabled={drafting}
          className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          {drafting ? 'Drafting…' : 'AI draft'}
        </button>
        <button
          type="submit"
          disabled={sending || body.trim() === ''}
          className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
          {draftId ? 'Send draft' : 'Send'}
        </button>
      </div>
    </form>
  )
}
