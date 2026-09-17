import { router } from '@inertiajs/react'
import { useMemo, useState } from 'react'
import { api } from '../lib/api'

/**
 * Internal notes on a conversation — never sent to the customer (see
 * NoteController). Typing "@" opens a simple filtered list of team
 * members; picking one inserts their name and records their id in
 * mentioned_user_ids for the server to store (no notification dispatch
 * yet — a documented extension point, see BUILD_PROMPT.md §3.2).
 */
export default function NotesPanel({ conversationId, notes, teamMembers }) {
  const [body, setBody] = useState('')
  const [mentionedIds, setMentionedIds] = useState([])
  const [mentionQuery, setMentionQuery] = useState(null)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState(null)

  const mentionMatches = useMemo(() => {
    if (mentionQuery === null) return []
    const query = mentionQuery.toLowerCase()
    return teamMembers.filter((member) => (member.name ?? '').toLowerCase().includes(query)).slice(0, 5)
  }, [mentionQuery, teamMembers])

  function handleChange(event) {
    const value = event.target.value
    setBody(value)

    const cursor = event.target.selectionStart ?? value.length
    const upToCursor = value.slice(0, cursor)
    const match = upToCursor.match(/@([\w .]*)$/)
    setMentionQuery(match ? match[1] : null)
  }

  function pickMention(member) {
    setBody((current) => current.replace(/@([\w .]*)$/, `@${member.name} `))
    setMentionedIds((current) => (current.includes(member.id) ? current : [...current, member.id]))
    setMentionQuery(null)
  }

  async function submit(event) {
    event.preventDefault()
    if (body.trim() === '') return

    setSubmitting(true)
    setError(null)

    try {
      await api.post(`/shared-inbox/conversations/${conversationId}/notes`, {
        body,
        mentioned_user_ids: mentionedIds,
      })
      setBody('')
      setMentionedIds([])
      router.reload({ only: ['conversation'] })
    } catch {
      setError('Could not save that note. Try again.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex flex-col gap-3">
      <ol className="flex flex-col gap-2">
        {notes.length === 0 && <p className="text-sm text-gray-500">No internal notes yet.</p>}
        {notes.map((note) => (
          <li key={note.id} className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
            <p className="mb-1 text-xs font-medium text-amber-800">{note.author ?? 'Someone'}</p>
            <p className="whitespace-pre-wrap text-gray-900">{note.body}</p>
          </li>
        ))}
      </ol>

      <form onSubmit={submit} className="relative flex flex-col gap-2 rounded-lg border border-gray-200 p-3">
        {error && <p className="text-sm text-red-600">{error}</p>}
        <textarea
          className="min-h-[72px] w-full resize-y rounded-md border-gray-300 text-sm"
          placeholder="Leave an internal note — type @ to mention a teammate"
          value={body}
          onChange={handleChange}
        />
        {mentionMatches.length > 0 && (
          <ul className="absolute bottom-14 left-3 z-10 w-48 rounded-md border border-gray-200 bg-white shadow-lg">
            {mentionMatches.map((member) => (
              <li key={member.id}>
                <button
                  type="button"
                  onClick={() => pickMention(member)}
                  className="block w-full px-3 py-1.5 text-left text-sm hover:bg-gray-50"
                >
                  {member.name}
                </button>
              </li>
            ))}
          </ul>
        )}
        <button
          type="submit"
          disabled={submitting || body.trim() === ''}
          className="w-fit rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
          Add note
        </button>
      </form>
    </div>
  )
}
