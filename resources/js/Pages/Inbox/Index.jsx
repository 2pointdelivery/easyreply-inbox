import { Link } from '@inertiajs/react'
import ConversationList from '../../Components/ConversationList'
import StatusFilter from '../../Components/StatusFilter'
import { useSharedInboxChannel } from '../../hooks/useSharedInboxChannel'

const SETTINGS_LINKS = [
  ['/shared-inbox/voice/calls', 'Calls'],
  ['/shared-inbox/settings/labels', 'Labels'],
  ['/shared-inbox/settings/sla-policies', 'SLA'],
  ['/shared-inbox/settings/ai', 'AI'],
  ['/shared-inbox/settings/voice', 'Voice'],
  ['/shared-inbox/settings/widget', 'Widget'],
  ['/shared-inbox/settings/integrations', 'Integrations'],
  ['/shared-inbox/settings/mcp', 'MCP'],
]

export default function Index({ conversations, filters, team_id: teamId }) {
  useSharedInboxChannel(teamId ? `team.${teamId}` : null, ['MessageReceived', 'ConversationUpdated', 'ConversationAssigned'], {
    only: ['conversations'],
  })

  return (
    <div className="mx-auto max-w-3xl p-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Inbox</h1>
        <nav className="flex gap-3 text-sm text-gray-500">
          {SETTINGS_LINKS.map(([href, label]) => (
            <Link key={href} href={href} className="hover:text-indigo-600 hover:underline">
              {label}
            </Link>
          ))}
        </nav>
      </div>

      <div className="mb-4">
        <StatusFilter value={filters.status} />
      </div>

      <ConversationList conversations={conversations} />
    </div>
  )
}
