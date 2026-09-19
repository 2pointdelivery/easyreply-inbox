import { useForm } from '@inertiajs/react'

export default function Voice({ team, agents, drivers, global }) {
  const { data, setData, patch, processing } = useForm({
    voice_driver: team?.voice_driver ?? '',
    voice_prompt: team?.voice_prompt ?? '',
    voice_number_override: team?.voice_number_override ?? '',
    transfer_target: team?.transfer_target ?? '',
  })

  const submit = (e) => {
    e.preventDefault()
    patch('/shared-inbox/settings/voice')
  }

  return (
    <div className="p-6 max-w-2xl">
      <h1 className="text-xl font-semibold mb-1">Voice reception agent</h1>
      <p className="text-sm text-gray-500 mb-4">
        Global default: {global?.driver} · {global?.shared_number_e164 ?? 'no shared number configured'}.
        Team overrides apply on top of the global default. No audio is ever stored.
      </p>
      <form onSubmit={submit} className="space-y-3">
        <label className="block text-sm">
          Driver
          <select value={data.voice_driver} onChange={(e) => setData('voice_driver', e.target.value)} className="mt-1 block w-full border rounded p-2">
            <option value="">Use global default</option>
            {drivers.map((d) => (
              <option key={d} value={d}>{d}</option>
            ))}
          </select>
        </label>
        <label className="block text-sm">
          Agent prompt
          <textarea value={data.voice_prompt} onChange={(e) => setData('voice_prompt', e.target.value)} className="mt-1 block w-full border rounded p-2" rows={4} />
        </label>
        <label className="block text-sm">
          Number override (E.164, optional)
          <input value={data.voice_number_override} onChange={(e) => setData('voice_number_override', e.target.value)} className="mt-1 block w-full border rounded p-2" />
        </label>
        <label className="block text-sm">
          Live-transfer target (E.164)
          <input value={data.transfer_target} onChange={(e) => setData('transfer_target', e.target.value)} className="mt-1 block w-full border rounded p-2" />
        </label>
        <button disabled={processing} className="px-4 py-2 bg-blue-600 text-white rounded text-sm">
          Save voice settings
        </button>
      </form>
      <h2 className="font-medium mt-6 mb-2">Agents</h2>
      <ul className="text-sm divide-y divide-gray-200">
        {agents.map((a) => (
          <li key={a.id} className="py-1">{a.name} · {a.driver} · {a.is_active ? 'active' : 'inactive'}</li>
        ))}
      </ul>
    </div>
  )
}
