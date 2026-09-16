export default function ConversationSidebar({ sidebar }) {
  const hasContext = sidebar?.customer_context && Object.keys(sidebar.customer_context).length > 0
  const hasIncidents = sidebar?.incidents && sidebar.incidents.length > 0

  if (!hasContext && !hasIncidents) {
    return null
  }

  return (
    <aside className="flex flex-col gap-3 rounded-lg border border-gray-200 p-3 text-sm">
      {hasIncidents && (
        <div className="rounded bg-red-50 p-2 text-red-800">
          <p className="font-medium">Active incident{sidebar.incidents.length > 1 ? 's' : ''}</p>
          <ul className="list-disc pl-4">
            {sidebar.incidents.map((incident, index) => (
              <li key={incident.id ?? index}>
                {incident.attributes?.name ?? incident.name ?? 'Ongoing incident'}
              </li>
            ))}
          </ul>
        </div>
      )}
      {hasContext && (
        <div>
          <p className="mb-1 font-medium text-gray-900">Customer context</p>
          <dl className="grid grid-cols-[auto,1fr] gap-x-2 gap-y-1 text-gray-600">
            {Object.entries(sidebar.customer_context).map(([field, value]) => (
              <div key={field} className="contents">
                <dt className="text-gray-400">{field}</dt>
                <dd>{String(value ?? '')}</dd>
              </div>
            ))}
          </dl>
        </div>
      )}
    </aside>
  )
}
