import { useState } from 'react'
import { api } from '../../lib/api'
import { router } from '@inertiajs/react'

export default function Labels({ labels }) {
  const [name, setName] = useState('')
  const [color, setColor] = useState('#6366f1')
  const [error, setError] = useState(null)

  async function submit(event) {
    event.preventDefault()
    setError(null)

    try {
      await api.post('/shared-inbox/settings/labels', { name, color })
      setName('')
      router.reload({ only: ['labels'] })
    } catch (err) {
      setError(err.status === 403 ? 'Only team owners/admins can manage labels.' : 'Could not create that label.')
    }
  }

  async function destroy(label) {
    try {
      await fetch(`/shared-inbox/settings/labels/${label.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
      router.reload({ only: ['labels'] })
    } catch {
      setError('Could not remove that label.')
    }
  }

  return (
    <div className="mx-auto max-w-2xl p-6">
      <h1 className="mb-1 text-xl font-semibold text-gray-900">Labels</h1>
      <p className="mb-6 text-sm text-gray-500">Team-wide labels agents can attach to conversations. Owners/admins only.</p>

      {error && <p className="mb-4 text-sm text-red-600">{error}</p>}

      <form onSubmit={submit} className="mb-6 flex items-end gap-2">
        <div className="flex-1">
          <label className="mb-1 block text-xs font-medium text-gray-700">Name</label>
          <input
            type="text"
            value={name}
            onChange={(event) => setName(event.target.value)}
            required
            className="w-full rounded-md border-gray-300 text-sm"
          />
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-gray-700">Color</label>
          <input
            type="color"
            value={color}
            onChange={(event) => setColor(event.target.value)}
            className="h-9 w-14 rounded-md border-gray-300"
          />
        </div>
        <button type="submit" className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
          Add
        </button>
      </form>

      <ul className="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
        {labels.length === 0 && <li className="p-4 text-sm text-gray-500">No labels yet.</li>}
        {labels.map((label) => (
          <li key={label.id} className="flex items-center justify-between p-4">
            <span className="flex items-center gap-2">
              <span className="h-3 w-3 rounded-full" style={{ backgroundColor: label.color }} />
              {label.name}
            </span>
            <button
              type="button"
              onClick={() => destroy(label)}
              className="text-sm text-red-600 hover:underline"
            >
              Remove
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}
