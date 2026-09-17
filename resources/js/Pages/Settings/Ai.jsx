function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

const LABELS = {
  null: 'Use global default',
  openai: 'OpenAI',
  anthropic: 'Anthropic',
}

export default function Ai({ drivers, default_driver: defaultDriver, team_driver: teamDriver }) {
  return (
    <div className="mx-auto max-w-2xl p-6">
      <h1 className="mb-1 text-xl font-semibold text-gray-900">AI drafting</h1>
      <p className="mb-6 text-sm text-gray-500">
        Override which AI provider this team's drafts use. Leave on the global default
        (currently <code className="rounded bg-gray-100 px-1">{defaultDriver}</code>) unless this team needs a different one.
      </p>

      <form method="POST" action="/shared-inbox/settings/ai" className="flex flex-col gap-3">
        <input type="hidden" name="_token" value={csrfToken()} />
        <input type="hidden" name="_method" value="PATCH" />

        <label className="flex items-center gap-2 text-sm text-gray-900">
          <input type="radio" name="ai_driver" value="" defaultChecked={!teamDriver} />
          {LABELS.null}
        </label>
        {drivers
          .filter((driver) => driver !== 'null')
          .map((driver) => (
            <label key={driver} className="flex items-center gap-2 text-sm text-gray-900">
              <input type="radio" name="ai_driver" value={driver} defaultChecked={teamDriver === driver} />
              {LABELS[driver] ?? driver}
            </label>
          ))}

        <button type="submit" className="mt-2 w-fit rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
          Save
        </button>
      </form>
    </div>
  )
}
