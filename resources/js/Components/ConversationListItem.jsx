import { Link } from '@inertiajs/react'

export default function ConversationListItem({ conversation }) {
  return (
    <li>
      <Link
        href={`/shared-inbox/conversations/${conversation.id}`}
        className="flex flex-col gap-1 p-4 hover:bg-gray-50"
      >
        <div className="flex items-center justify-between">
          <span className="font-medium text-gray-900">
            {conversation.contact?.display_name ?? 'Unknown contact'}
          </span>
          <span className="text-xs uppercase tracking-wide text-gray-400">{conversation.status}</span>
        </div>
        <span className="text-sm text-gray-700">{conversation.subject ?? '(no subject)'}</span>
        {conversation.preview && (
          <span className="truncate text-sm text-gray-500">{conversation.preview}</span>
        )}
      </Link>
    </li>
  )
}
