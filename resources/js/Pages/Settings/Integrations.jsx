import { router } from '@inertiajs/react'
import { useState } from 'react'
import { api } from '../../lib/api'

function csrfToken() {
  if (typeof document === 'undefined') return ''
  return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

const LABELS = {
  linear: 'Linear',
  hubspot: 'HubSpot',
  betterstack: 'Betterstack',
}

// Which credential fields each integration's IntegrationSetting.config
// accepts — see README.md "Optional integrations" table.
const CONFIG_FIELDS = {
  linear: [
    { name: 'api_key', label: 'API key', type: 'password' },
    { name: 'team_id', label: 'Linear team id', type: 'text' },
  ],
  hubspot: [{ name: 'api_key', label: 'Private app token', type: 'password' }],
  betterstack: [{ name: 'api_key', label: 'API key', type: 'password' }],
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

// Credentials are write-only from the client's perspective (the server
// never sends back a stored secret, only `configured: true/false`), so
// this form always starts blank and only submits fields the admin typed.
function CredentialsForm({ integrationKey, enabled, configured }) {
  const fields = CONFIG_FIELDS[integrationKey] ?? []
  const [values, setValues] = useState({})
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)

  if (fields.length === 0) return null

  async function submit(event) {
    event.preventDefault()
    setSaving(true)
    setSaved(false)

    try {
      await api.patch(`/shared-inbox/settings/integrations/${integrationKey}`, {
        enabled,
        config: values,
      })
      setSaved(true)
      router.reload({ only: ['integrations'] })
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={submit} className="mt-3 flex flex-col gap-2 border-t border-gray-100 pt-3">
      {fields.map((field) => (
        <div key={field.name}>
          <label className="mb-1 block text-xs font-medium text-gray-700">{field.label}</label>
          <input
            type={field.type}
            value={values[field.name] ?? ''}
            placeholder={configured ? '••••••••' : undefined}
            onChange={(event) => setValues((current) => ({ ...current, [field.name]: event.target.value }))}
            className="w-full rounded-md border-gray-300 text-sm"
          />
        </div>
      ))}
      <div className="flex items-center gap-2">
        <button
          type="submit"
          disabled={saving}
          className="w-fit rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
        >
          {saving ? 'Saving…' : 'Save credentials'}
        </button>
        {saved && <span className="text-xs text-green-600">Saved</span>}
      </div>
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
          <li key={integration.key} className="p-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium text-gray-900">{LABELS[integration.key] ?? integration.key}</p>
                {!integration.available && (
                  <p className="text-xs text-gray-400">Not available — check config('shared-inbox.integrations').</p>
                )}
                {integration.available && (
                  <p className="text-xs text-gray-400">{integration.configured ? 'Credentials configured' : 'No credentials set'}</p>
                )}
              </div>
              {integration.available && (
                <ToggleForm integrationKey={integration.key} enabled={integration.enabled} />
              )}
            </div>
            {integration.available && (
              <CredentialsForm
                integrationKey={integration.key}
                enabled={integration.enabled}
                configured={integration.configured}
              />
            )}
          </li>
        ))}
      </ul>
    </div>
  )
}
