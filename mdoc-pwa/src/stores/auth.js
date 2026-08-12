/**
 * Pinia auth store — holds the citizen token + profile.
 * Token is persisted to localStorage so the session survives reloads.
 */

import { defineStore } from 'pinia'
import api, { ApiError, setTokenProvider } from '../api/client'

const TOKEN_KEY = 'mdoc_token'
const CITIZEN_KEY = 'mdoc_citizen'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(TOKEN_KEY) || null,
    citizen: JSON.parse(localStorage.getItem(CITIZEN_KEY) || 'null'),
    loading: false,
  }),

  getters: {
    isAuthenticated: (s) => !!s.token,
    fullName: (s) => s.citizen?.full_name || '',
  },

  actions: {
    async login(phone, password) {
      this.loading = true
      try {
        const data = await api('auth/login', { method: 'POST', body: { phone, password } })
        this.setSession(data.token, data.citizen)
        return data
      } finally {
        this.loading = false
      }
    },

    async register(payload) {
      this.loading = true
      try {
        const data = await api('auth/register', { method: 'POST', body: payload })
        this.setSession(data.token, data.citizen)
        return data
      } finally {
        this.loading = false
      }
    },

    async fetchMe() {
      if (!this.token) return null
      try {
        const citizen = await api('auth/me')
        this.citizen = citizen
        this.persist()
        return citizen
      } catch (e) {
        // 401 → token expired/invalid → force logout
        if (e instanceof ApiError && e.status === 401) {
          this.logout()
        }
        throw e
      }
    },

    setSession(token, citizen) {
      this.token = token
      this.citizen = citizen
      this.persist()
    },

    persist() {
      if (this.token) localStorage.setItem(TOKEN_KEY, this.token)
      else localStorage.removeItem(TOKEN_KEY)
      if (this.citizen) localStorage.setItem(CITIZEN_KEY, JSON.stringify(this.citizen))
      else localStorage.removeItem(CITIZEN_KEY)
    },

    logout() {
      this.token = null
      this.citizen = null
      this.persist()
    },
  },
})

// Wire the token provider used by the API client (called lazily to avoid
// circular import issues at module load time)
setTokenProvider(() => {
  // Access store state without importing the hook at module scope:
  // read directly from localStorage to stay dependency-free here
  return localStorage.getItem(TOKEN_KEY)
})
