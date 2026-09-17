import { router } from '@inertiajs/react'
import { useState } from 'react'
import { api } from '../../lib/api'

const PRIORITIES = ['low', 'normal', 'high', 'urgent']

export default function SlaPolicies({ policies }) {
  const [priority, setPriority] = useState('normal')
  const [firstResponseMinutes, setFirstResponseMinutes] = useState(60)
  const [resolutionMinutes, setResolutionMinutes] = useState(1440)
  const [error, setError] = useState(null)

  async function submit(event) {
    event.preventDefault()
    setError(null)

    try {
      await api.post('/shared-inbox/settings/sla-policies', {
        priority,
        first_response_minutes: Number(firstResponseMinutes),
        resolution_minutes: Number(resolutionMinutes),
      })
      router.reload({ only: ['policies'] })
    } catch (err) {
      setError(err.status === 403 ? 'Only team owners/admins can manage SLA policies.' : 'Could not save that policy.')
    }
  }

  async function destroy(policy) {
    try {
      await fetch(`/shared-inbox/settings/sla-policies/${policy.id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
      router.reload({ only: ['policies'] })
    } catch {
      setError('Could not remove that policy.')
    }
  }

  return (
    <div className="mx-auto max-w-2xl p-6">
      <h1 className="mb-1 text-xl font-semibold text-gray-900">SLA Policies</h1>
      <p className="mb-6 text-sm text-gray-500">
        One policy per priority. A conversation gets an SLA due date from the policy matching its priority. Owners/admins only.
      </p>

      {error && <p className="mb-4 text-sm text-red-600">{error}</p>}

      <form onSubmit={submit} className="mb-6 flex items-end gap-2">
        <div>
          <label className="mb-1 block text-xs font-medium text-gray-700">Priority</label>
          <select
            value={priority}
            onChange={(event) => setPriority(event.target.value)}
            className="rounded-md border-gray-300 text-sm"
          >
            {PRIORITIES.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-gray-700">First response (min)</label>
          <input
            type="number"
            min="1"
            value={firstResponseMinutes}
            onChange={(event) => setFirstResponseMinutes(event.target.value)}
            className="w-28 rounded-md border-gray-300 text-sm"
          />
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-gray-700">Resolution (min)</label>
          <input
            type="number"
            min="1"
            value={resolutionMinutes}
            onChange={(event) => setResolutionMinutes(event.target.value)}
            className="w-28 rounded-md border-gray-300 text-sm"
          />
        </div>
        <button type="submit" className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">
          Save
        </button>
      </form>

      <ul className="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
        {policies.length === 0 && <li className="p-4 text-sm text-gray-500">No SLA policies yet.</li>}
        {policies.map((policy) => (
          <li key={policy.id} className="flex items-center justify-between p-4">
            <span className="text-sm text-gray-900">
              <span className="font-medium capitalize">{policy.priority}</span> &middot; first response in{' '}
              {policy.first_response_minutes}m &middot; resolve in {policy.resolution_minutes}m
            </span>
            <button type="button" onClick={() => destroy(policy)} className="text-sm text-red-600 hover:underline">
              Remove
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}
