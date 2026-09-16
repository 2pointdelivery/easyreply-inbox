function csrfToken() {
  if (typeof document === 'undefined') return ''
  return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

/**
 * Connecting/disconnecting an MCP app leaves the SPA entirely (Composio's
 * hosted OAuth flow) or is a destructive action — both submit as plain HTML
 * forms (full browser navigation), not Inertia visits or fetch calls.
 */
function ConnectForm({ appSlug }) {
  return (
    <form method="POST" action={`/shared-inbox/settings/mcp/${appSlug}/connect`}>
      <input type="hidden" name="_token" value={csrfToken()} />
      <button
        type="submit"
        className="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white"
      >
        Connect
      </button>
    </form>
  )
}

function DisconnectForm({ connectionId }) {
  return (
    <form method="POST" action={`/shared-inbox/settings/mcp/${connectionId}`}>
      <input type="hidden" name="_token" value={csrfToken()} />
      <input type="hidden" name="_method" value="DELETE" />
      <button type="submit" className="text-sm font-medium text-red-600 hover:underline">
        Disconnect
      </button>
    </form>
  )
}

const STATUS_STYLES = {
  active: 'bg-green-100 text-green-800',
  pending: 'bg-amber-100 text-amber-800',
  failed: 'bg-red-100 text-red-800',
  revoked: 'bg-gray-100 text-gray-600',
}

export default function Mcp({ apps, connections }) {
  const connectionsByApp = Object.fromEntries(connections.map((c) => [c.app_slug, c]))

  return (
    <div className="mx-auto max-w-2xl p-6">
      <h1 className="mb-1 text-xl font-semibold text-gray-900">MCP Tool Connections</h1>
      <p className="mb-6 text-sm text-gray-500">
        Connect external tools via Composio so AI drafts and agents can securely access your team's
        data.
      </p>

      <ul className="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
        {apps.map((app) => {
          const connection = connectionsByApp[app.key ?? app.name]
          return (
            <li key={app.key ?? app.name} className="flex items-center justify-between p-4">
              <div>
                <p className="font-medium text-gray-900">{app.name}</p>
                {connection && (
                  <span
                    className={
                      'mt-1 inline-block rounded px-2 py-0.5 text-xs font-medium ' +
                      (STATUS_STYLES[connection.status] ?? 'bg-gray-100 text-gray-600')
                    }
                  >
                    {connection.status}
                  </span>
                )}
              </div>
              {connection && connection.status !== 'revoked' ? (
                <DisconnectForm connectionId={connection.id} />
              ) : (
                <ConnectForm appSlug={app.key ?? app.name} />
              )}
            </li>
          )
        })}
        {apps.length === 0 && (
          <li className="p-6 text-center text-sm text-gray-500">
            No apps available — check your Composio API key configuration.
          </li>
        )}
      </ul>
    </div>
  )
}
