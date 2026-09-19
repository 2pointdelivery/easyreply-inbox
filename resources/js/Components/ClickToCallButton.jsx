import { router } from '@inertiajs/react'
import { useState } from 'react'

export default function ClickToCallButton({ conversationId, defaultNumber = '' }) {
  const [to, setTo] = useState(defaultNumber)
  const [busy, setBusy] = useState(false)

  const dial = () => {
    if (!to) return
    setBusy(true)
    router.post(
      `/shared-inbox/conversations/${conversationId}/click-to-call`,
      { to_e164: to },
      { onFinish: () => setBusy(false) },
    )
  }

  return (
    <div className="flex items-center gap-2">
      <input
        value={to}
        onChange={(e) => setTo(e.target.value)}
        placeholder="+15551234567"
        className="border rounded p-1 text-sm"
      />
      <button onClick={dial} disabled={busy || !to} className="px-3 py-1 bg-green-600 text-white rounded text-sm">
        {busy ? 'Dialing…' : 'Call'}
      </button>
    </div>
  )
}
