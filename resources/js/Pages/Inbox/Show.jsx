import { Link } from '@inertiajs/react'
import { useState } from 'react'
import ComposeBox from '../../Components/ComposeBox'
import ConversationSidebar from '../../Components/ConversationSidebar'
import LabelPicker from '../../Components/LabelPicker'
import MessageThread from '../../Components/MessageThread'
import NotesPanel from '../../Components/NotesPanel'
import { useSharedInboxChannel } from '../../hooks/useSharedInboxChannel'

export default function Show({ conversation, sidebar, team_members: teamMembers }) {
  const [tab, setTab] = useState('thread')

  useSharedInboxChannel(`conversation.${conversation.id}`, ['MessageReceived', 'MessageSent', 'ConversationUpdated'], {
    only: ['conversation', 'sidebar'],
  })

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-4 p-6">
      <Link href="/shared-inbox" className="text-sm text-indigo-600 hover:underline">
        &larr; Back to inbox
      </Link>

      <header className="flex flex-col gap-2">
        <h1 className="text-lg font-semibold text-gray-900">{conversation.subject ?? '(no subject)'}</h1>
        <p className="text-sm text-gray-500">
          {conversation.contact?.display_name ?? 'Unknown contact'} &middot; {conversation.status}
          {conversation.sla_due_at && (
            <>
              {' '}
              &middot; SLA due {new Date(conversation.sla_due_at).toLocaleString()}
            </>
          )}
        </p>
        <LabelPicker conversationId={conversation.id} labels={conversation.labels} />
      </header>

      <ConversationSidebar sidebar={sidebar} />

      <div className="flex gap-1 border-b border-gray-200">
        {['thread', 'notes'].map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            className={
              'px-3 py-2 text-sm font-medium capitalize ' +
              (tab === key ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700')
            }
          >
            {key === 'notes' ? `Notes (${conversation.notes.length})` : 'Thread'}
          </button>
        ))}
      </div>

      {tab === 'thread' ? (
        <>
          <MessageThread messages={conversation.messages} />
          <ComposeBox conversationId={conversation.id} pendingDraft={conversation.pending_draft} />
        </>
      ) : (
        <NotesPanel conversationId={conversation.id} notes={conversation.notes} teamMembers={teamMembers ?? []} />
      )}
    </div>
  )
}
