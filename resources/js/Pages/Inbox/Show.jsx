import { Link } from '@inertiajs/react'
import ComposeBox from '../../Components/ComposeBox'
import ConversationSidebar from '../../Components/ConversationSidebar'
import MessageThread from '../../Components/MessageThread'
import { useSharedInboxChannel } from '../../hooks/useSharedInboxChannel'

export default function Show({ conversation, sidebar }) {
  useSharedInboxChannel(`conversation.${conversation.id}`, ['MessageReceived', 'MessageSent', 'ConversationUpdated'], {
    only: ['conversation', 'sidebar'],
  })

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-4 p-6">
      <Link href="/shared-inbox" className="text-sm text-indigo-600 hover:underline">
        &larr; Back to inbox
      </Link>

      <header>
        <h1 className="text-lg font-semibold text-gray-900">{conversation.subject ?? '(no subject)'}</h1>
        <p className="text-sm text-gray-500">
          {conversation.contact?.display_name ?? 'Unknown contact'} &middot; {conversation.status}
        </p>
      </header>

      <ConversationSidebar sidebar={sidebar} />
      <MessageThread messages={conversation.messages} />
      <ComposeBox conversationId={conversation.id} pendingDraft={conversation.pending_draft} />
    </div>
  )
}
