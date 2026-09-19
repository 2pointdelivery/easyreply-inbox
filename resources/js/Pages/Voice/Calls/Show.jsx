import { router } from '@inertiajs/react'
import { useSharedInboxChannel } from '../../../hooks/useSharedInboxChannel.js'

export default function Show({ call }) {
  useSharedInboxChannel(call?.conversation_id ? `conversation.${call.conversation_id}` : null, ['CallUpdated'])

  const retry = () => router.post(`/shared-inbox/voice/calls/${call.id}/actions/retry`)

  return (
    <div className="p-6 max-w-3xl">
      <h1 className="text-xl font-semibold mb-1">
        Call {call.direction} · {call.status}
      </h1>
      <p className="text-sm text-gray-500 mb-4">
        {call.from_e164} → {call.to_e164} · {call.provider} · Audio not retained by policy.
      </p>

      {call.summary && (
        <section className="mb-4">
          <h2 className="font-medium">Summary</h2>
          <p className="text-sm">{call.summary}</p>
        </section>
      )}

      {call.transcript && (
        <section className="mb-4">
          <h2 className="font-medium">Transcript</h2>
          <p className="text-sm whitespace-pre-wrap">{call.transcript}</p>
        </section>
      )}

      <section className="mb-4 text-sm text-gray-600">
        Sentiment: {call.sentiment ?? '—'} · Suggested priority: {call.priority_suggestion ?? '—'} ·
        Transfer: {call.transfer_outcome ?? '—'}
      </section>

      <section className="mb-4">
        <h2 className="font-medium">Post-call actions</h2>
        <ul className="text-sm divide-y divide-gray-200">
          {(call.actions ?? []).map((a) => (
            <li key={a.id} className="py-1">
              {a.action_type} · {a.status} {a.error ? `· ${a.error}` : ''}
            </li>
          ))}
        </ul>
        <button onClick={retry} className="mt-2 text-sm text-blue-600 underline">
          Retry failed actions
        </button>
      </section>
    </div>
  )
}
