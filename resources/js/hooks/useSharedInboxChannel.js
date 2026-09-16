import { router } from '@inertiajs/react'
import { useEffect } from 'react'

/**
 * Subscribes to a shared-inbox private channel (`shared-inbox.<channelName>`,
 * matching the channel names the events in src/Events broadcast on — see
 * BUILD_PROMPT.md §3.6) and reloads the given Inertia page props whenever any
 * of the given events fire.
 *
 * The package configures no broadcaster itself, so this only does anything
 * once the host app wires up Laravel Echo (window.Echo) — see README.md
 * "Real-time". Without it, this falls back to polling every
 * `pollIntervalMs` so the page still reflects new activity.
 *
 * @param {string|null} channelName e.g. `team.${teamId}` or `conversation.${conversationId}`
 * @param {string[]} eventNames e.g. ['MessageReceived', 'MessageSent']
 * @param {{ only?: string[], pollIntervalMs?: number }} [options]
 */
export function useSharedInboxChannel(channelName, eventNames, options = {}) {
  const { only, pollIntervalMs = 15000 } = options

  useEffect(() => {
    if (!channelName) {
      return undefined
    }

    const reload = () => router.reload({ only, preserveScroll: true, preserveState: true })

    if (typeof window !== 'undefined' && window.Echo) {
      const fullChannelName = `shared-inbox.${channelName}`
      const channel = window.Echo.private(fullChannelName)

      eventNames.forEach((event) => channel.listen(`.${event}`, reload))

      return () => {
        window.Echo.leave(fullChannelName)
      }
    }

    const interval = setInterval(reload, pollIntervalMs)
    return () => clearInterval(interval)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [channelName, eventNames.join(','), pollIntervalMs])
}
