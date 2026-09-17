export default function MessageThread({ messages }) {
  if (messages.length === 0) {
    return <p className="text-sm text-gray-500">No messages yet.</p>
  }

  return (
    <ol className="flex flex-col gap-3">
      {messages.map((message) => (
        <li
          key={message.id}
          className={
            'max-w-[75%] rounded-lg p-3 text-sm ' +
            (message.direction === 'outbound'
              ? 'ml-auto bg-indigo-600 text-white'
              : 'bg-gray-100 text-gray-900')
          }
        >
          {message.ai_generated && (
            <span className="mb-1 block text-xs font-semibold uppercase tracking-wide opacity-70">
              AI draft
            </span>
          )}
          <p className="whitespace-pre-wrap">{message.body}</p>
          {message.attachments?.length > 0 && (
            <ul className="mt-2 flex flex-col gap-1 border-t border-white/20 pt-2">
              {message.attachments.map((attachment) => (
                <li key={attachment.id}>
                  <a
                    href={attachment.url}
                    target="_blank"
                    rel="noreferrer"
                    className="text-xs underline underline-offset-2 opacity-90 hover:opacity-100"
                  >
                    📎 {attachment.filename}
                  </a>
                </li>
              ))}
            </ul>
          )}
        </li>
      ))}
    </ol>
  )
}
