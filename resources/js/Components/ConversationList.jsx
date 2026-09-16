import ConversationListItem from './ConversationListItem'
import Pagination from './Pagination'

export default function ConversationList({ conversations }) {
  return (
    <>
      <ul className="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
        {conversations.data.length === 0 && (
          <li className="p-6 text-center text-sm text-gray-500">No conversations yet.</li>
        )}
        {conversations.data.map((conversation) => (
          <ConversationListItem key={conversation.id} conversation={conversation} />
        ))}
      </ul>
      {conversations.links && <Pagination links={conversations.links} />}
    </>
  )
}
