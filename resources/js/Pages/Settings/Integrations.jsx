function csrfToken() {
  if (typeof document === 'undefined') return ''
  return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

const LABELS = {
  linear: 'Linear',
  hubspot: 'HubSpot',
  betterstack: 'Betterstack',
}

function ToggleForm({ integrationKey, enabled }) {
  return (
    <form
      method="POST"
      action={`/shared-inbox/settings/integrations/${integrationKey}`}
      className="flex items-center gap-2"
    >
      <input type="hidden" name="_token" value={csrfToken()} />
      <input type="hidden" name="_method" value="PATCH" />
      <input type="hidden" name="enabled" value={enabled ? '0' : '1'} />
      <button
        type="submit"
        className={
          'rounded-md px-3 py-1.5 text-sm font-medium ' +
          (enabled ? 'border border-gray-300 text-gray-700 hover:bg-gray-50' : 'bg-indigo-600 text-white')
        }
      >
        {enabled ? 'Disable' : 'Enable'}
      </button>
    </form>
  )
}

export default function Integrations({ integrations }) {
  return (
    <div className="mx-auto max-w-2xl p-6">
      <h1 className="mb-1 text-xl font-semibold text-gray-900">Integrations</h1>
      <p className="mb-6 text-sm text-gray-500">
        Optional — none of these are required for the shared inbox to work.
      </p>

      <ul className="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
        {integrations.map((integration) => (
          <li key={integration.key} className="flex items-center justify-between p-4">
            <div>
              <p className="font-medium text-gray-900">{LABELS[integration.key] ?? integration.key}</p>
              {!integration.available && (
                <p className="text-xs text-gray-400">Not available — check config('shared-inbox.integrations').</p>
              )}
            </div>
            {integration.available && (
              <ToggleForm integrationKey={integration.key} enabled={integration.enabled} />
            )}
          </li>
        ))}
      </ul>
    </div>
  )
}
