/**
 * Small fetch wrapper for this package's JSON endpoints (sending a message,
 * requesting an AI draft, etc.) — these are plain JSON API routes, not
 * Inertia responses, so they're called with fetch() rather than Inertia's
 * router.post/patch (which expects an Inertia page response back). Callers
 * follow up a successful call with router.reload({ only: [...] }) to
 * refresh the Inertia page props.
 */
function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

async function request(method, url, body) {
  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  return handleResponse(url, response)
}

/**
 * Multipart submission (message attachments) — no Content-Type header, the
 * browser sets it (with the multipart boundary) from the FormData body.
 */
async function requestForm(url, formData) {
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: formData,
  })

  return handleResponse(url, response)
}

async function handleResponse(url, response) {
  const data = await response.json().catch(() => null)

  if (!response.ok) {
    const error = new Error(`Request to ${url} failed with ${response.status}`)
    error.status = response.status
    error.data = data
    throw error
  }

  return data
}

export const api = {
  post: (url, body) => request('POST', url, body),
  patch: (url, body) => request('PATCH', url, body),
  postForm: (url, formData) => requestForm(url, formData),
}
