/**
 * API client for the mDoc PWA.
 * Wraps fetch with:
 *  - base URL injection (__API_BASE__ set by Vite define)
 *  - automatic Bearer token attachment from the auth store
 *  - unified error handling (ApiError with code/message/status)
 *  - optional offline queue hook for mutations (filled in later)
 */

export class ApiError extends Error {
  constructor(message, { code = 'UNKNOWN', status = 0, details = null } = {}) {
    super(message)
    this.name = 'ApiError'
    this.code = code
    this.status = status
    this.details = details
  }
}

// Set by the auth store once loaded (avoids circular import)
let tokenProvider = () => null
export function setTokenProvider(fn) {
  tokenProvider = fn
}

export async function api(path, { method = 'GET', body = null, headers = {} } = {}) {
  const token = tokenProvider()
  const isFormData = body instanceof FormData

  const opts = {
    method,
    headers: {
      Accept: 'application/json',
      ...(body && !isFormData ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
  }
  if (body) {
    opts.body = isFormData ? body : JSON.stringify(body)
  }

  let res
  try {
    res = await fetch(`${__API_BASE__}/${path.replace(/^\//, '')}`, opts)
  } catch (e) {
    // Network failure — PWA is offline. Surface a friendly error.
    throw new ApiError('ບໍ່ສາມາດເຊື່ອມຕໍ່ກັບເຊີບເວີໄດ້ (offline)', { code: 'OFFLINE', status: 0 })
  }

  if (!res.ok) {
    let payload = null
    try {
      payload = await res.json()
    } catch {
      /* non-JSON error body */
    }
    const err = payload?.error

    // ─── 401 Interceptor ───────────────────────────────────
    // ถ้า server บอกว่า "token ไม่ถูกต้อง" หรือ "หมดอายุ"
    // → ลบ token ออกจากเครื่อง แล้วส่งผู้ใช้ไปหน้า login
    // เช็ค pathname เพื่อไม่ให้ redirect วนลูป (ถ้าอยู่ /login แล้วก็ไม่ต้อง redirect อีก)
    if (res.status === 401) {
      localStorage.removeItem('mdoc_token')
      localStorage.removeItem('mdoc_citizen')
      if (!window.location.pathname.startsWith('/login')) {
        window.location.href = '/login'
      }
    }
    // ────────────────────────────────────────────────────────

    throw new ApiError(
      err?.message || `Request failed (HTTP ${res.status})`,
      { code: err?.code || 'HTTP_ERROR', status: res.status, details: err?.details || null }
    )
  }

  // 204 or empty body
  if (res.status === 204) return null
  const ct = res.headers.get('content-type') || ''
  return ct.includes('application/json') ? res.json() : res.text()
}

export default api
