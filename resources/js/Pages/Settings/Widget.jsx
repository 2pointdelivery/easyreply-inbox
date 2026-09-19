import { useForm } from '@inertiajs/react'

export default function Widget({ inboxes }) {
  const createForm = useForm({ name: '' })
  const toggleForm = useForm({ widget_enabled: false })

  const create = (e) => {
    e.preventDefault()
    createForm.post('/shared-inbox/settings/widget')
  }

  const toggle = (inbox) => {
    toggleForm.setData('widget_enabled', !inbox.widget_enabled)
    toggleForm.patch(`/shared-inbox/settings/widget/${inbox.id}`)
  }

  const regenerate = (inbox) => {
    if (!confirm('Regenerate the widget token? Existing embeds will stop working until updated.')) return
    toggleForm.post(`/shared-inbox/settings/widget/${inbox.id}/regenerate`)
  }

  return (
    <div className="p-6 max-w-3xl">
      <h1 className="text-xl font-semibold mb-1">Website widget</h1>
      <p className="text-sm text-gray-500 mb-4">
        Floating chat + call-request bubble for your site. Paste the snippet before
        <code className="mx-1 rounded bg-gray-100 px-1">&lt;/body&gt;</code>
        on any page. The token is public by design (like an analytics key) — visitors
        still get their own session token and all endpoints are rate-limited.
      </p>

      <form onSubmit={create} className="flex gap-2 mb-6">
        <input
          value={createForm.data.name}
          onChange={(e) => createForm.setData('name', e.target.value)}
          placeholder="New widget inbox name, e.g. Website"
          className="flex-1 border rounded p-2 text-sm"
        />
        <button className="px-4 py-2 bg-blue-600 text-white rounded text-sm">Create</button>
      </form>

      <ul className="space-y-4">
        {inboxes.map((inbox) => (
          <li key={inbox.id} className="border rounded-lg p-4">
            <div className="flex items-center justify-between mb-2">
              <div className="font-medium">{inbox.name} <span className="text-xs text-gray-500">token {inbox.token_hint ?? '—'}</span></div>
              <div className="flex gap-2">
                <button onClick={() => toggle(inbox)} className="text-sm text-blue-600 underline">
                  {inbox.widget_enabled ? 'Disable' : 'Enable'}
                </button>
                <button onClick={() => regenerate(inbox)} className="text-sm text-blue-600 underline">
                  Regenerate token
                </button>
              </div>
            </div>
            {inbox.snippet ? (
              <pre className="text-xs bg-gray-50 border rounded p-2 overflow-x-auto">{inbox.snippet}</pre>
            ) : (
              <p className="text-sm text-gray-500">Save once to generate a token.</p>
            )}
          </li>
        ))}
      </ul>
      {inboxes.length === 0 && <p className="text-sm text-gray-500">No widget inboxes yet — create one above.</p>}
    </div>
  )
}
