import ConversationList from '../../Components/ConversationList'
import StatusFilter from '../../Components/StatusFilter'
import { useSharedInboxChannel } from '../../hooks/useSharedInboxChannel'

export default function Index({ conversations, filters, team_id: teamId }) {
  useSharedInboxChannel(teamId ? `team.${teamId}` : null, ['MessageReceived', 'ConversationUpdated', 'ConversationAssigned'], {
    only: ['conversations'],
  })

  return (
    <div className="mx-auto max-w-3xl p-6">
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900">Inbox</h1>
        <StatusFilter value={filters.status} />
      </div>

      <ConversationList conversations={conversations} />
    </div>
  )
}
