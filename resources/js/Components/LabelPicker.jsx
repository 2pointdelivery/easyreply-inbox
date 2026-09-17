import { router } from '@inertiajs/react'
import { useState } from 'react'

/**
 * Attaches/detaches team-scoped labels on a conversation (labels themselves
 * are managed at /shared-inbox/settings/labels by a team admin — see
 * LabelController). This component only shows/removes labels already on
 * the conversation; picking from the team's full label list is a
 * documented extension point.
 */
export default function LabelPicker({ conversationId, labels }) {
  const [removing, setRemoving] = useState(null)

  async function handleDetach(label) {
    setRemoving(label.id)
    try {
      await fetch(`/shared-inbox/conversations/${conversationId}/labels/${label.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
      router.reload({ only: ['conversation'] })
    } finally {
      setRemoving(null)
    }
  }

  if (!labels || labels.length === 0) {
    return null
  }

  return (
    <ul className="flex flex-wrap gap-1.5">
      {labels.map((label) => (
        <li
          key={label.id}
          className="flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium text-white"
          style={{ backgroundColor: label.color }}
        >
          {label.name}
          <button
            type="button"
            onClick={() => handleDetach(label)}
            disabled={removing === label.id}
            className="opacity-80 hover:opacity-100"
            aria-label={`Remove ${label.name}`}
          >
            ×
          </button>
        </li>
      ))}
    </ul>
  )
}
