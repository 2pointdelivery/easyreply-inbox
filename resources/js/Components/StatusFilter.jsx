import { router } from '@inertiajs/react'

const STATUSES = ['open', 'pending', 'closed', 'snoozed']

export default function StatusFilter({ value }) {
  return (
    <select
      className="rounded-md border-gray-300 text-sm"
      value={value ?? ''}
      onChange={(event) => {
        router.get(
          '/shared-inbox',
          { status: event.target.value || undefined },
          { preserveState: true, replace: true },
        )
      }}
    >
      <option value="">All statuses</option>
      {STATUSES.map((status) => (
        <option key={status} value={status}>
          {status}
        </option>
      ))}
    </select>
  )
}
