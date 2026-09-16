import { Link } from '@inertiajs/react'

export default function Pagination({ links }) {
  if (links.length <= 3) {
    return null
  }

  return (
    <div className="mt-4 flex flex-wrap gap-1">
      {links.map((link, index) => (
        <Link
          key={index}
          href={link.url ?? '#'}
          preserveState
          className={
            'rounded px-2 py-1 text-sm ' +
            (link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100') +
            (!link.url ? ' pointer-events-none opacity-40' : '')
          }
          dangerouslySetInnerHTML={{ __html: link.label }}
        />
      ))}
    </div>
  )
}
