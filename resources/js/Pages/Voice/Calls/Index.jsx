import { Link } from '@inertiajs/react'
import { useSharedInboxChannel } from '../../../hooks/useSharedInboxChannel.js'

export default function Index({ calls, team_id }) {
  useSharedInboxChannel(team_id ? `team.${team_id}` : null, ['CallLogged', 'CallUpdated'])

  return (
    <div className="p-6">
      <h1 className="text-xl font-semibold mb-1">Call log</h1>
      <p className="text-sm text-gray-500 mb-4">Audio not retained by policy — transcripts and summaries only.</p>
      <ul className="divide-y divide-gray-200">
        {(calls?.data ?? []).map((call) => (
          <li key={call.id} className="py-3 flex items-center justify-between">
            <div>
              <div className="font-medium">
                {call.direction} · {call.from_e164} → {call.to_e164}
              </div>
              <div className="text-sm text-gray-500">
                {call.status} · {call.provider} · {call.sentiment ?? 'no sentiment'} · {call.duration_seconds ?? '?'}s
              </div>
              {call.summary && <div className="text-sm mt-1">{call.summary}</div>}
            </div>
            <Link href={`/shared-inbox/voice/calls/${call.id}`} className="text-sm text-blue-600 underline">
              View
            </Link>
          </li>
        ))}
      </ul>
    </div>
  )
}
