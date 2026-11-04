// État partagé de la session : utilisateur, garage courant, compteurs de la barre latérale.
import { reactive, computed } from 'vue'
import { auth, get, post } from './api'
import type { Garage, GarageSummary, MeResponse, User } from './types'

interface SessionState {
  user: User | null
  /** Garage complet de l'utilisateur, ou garage choisi par un super-admin. */
  garage: Garage | null
  garages: GarageSummary[]
  loaded: boolean
  /** Garage choisi par un super-admin (copie réactive de auth.garageId). */
  garageId: number | null
  /** Incrémenté à chaque changement de garage pour recharger la vue. */
  viewKey: number
  newLeads: number
  usage: { vehicles_month: number; quota: number } | null
  toast: { text: string; kind: 'ok' | 'error'; id: number } | null
}

export const session = reactive<SessionState>({
  user: null,
  garage: null,
  garages: [],
  loaded: false,
  garageId: auth.garageId,
  viewKey: 0,
  newLeads: 0,
  usage: null,
  toast: null,
})

export const isSuper = computed(() => session.user?.role === 'super_admin')
export const isManager = computed(() => session.user?.role === 'manager' || session.user?.role === 'super_admin')
export const isPhotographer = computed(() => session.user?.role === 'photographer')

/** Garage courant (résumé) pour le sélecteur. */
export const currentGarage = computed<GarageSummary | null>(() => {
  const id = isSuper.value ? session.garageId : session.user?.garage_id
  if (!id) return null
  return session.garages.find((g) => g.id === id) ?? null
})

export const hasGarage = computed(() => (isSuper.value ? !!session.garageId && !!currentGarage.value : !!session.user?.garage_id))

export async function loadMe(): Promise<void> {
  const me = await get<MeResponse>('/me')
  session.user = me.user
  session.garages = me.garages
  if (me.user.role === 'super_admin') {
    // Garage choisi précédemment : on vérifie qu'il existe encore.
    if (auth.garageId && !me.garages.some((g) => g.id === auth.garageId)) auth.setGarageId(null)
    session.garageId = auth.garageId
    session.garage = null
    if (auth.garageId) await loadGarage()
  } else {
    session.garage = me.garage
  }
  session.loaded = true
  refreshSidebar()
}

/** Recharge la fiche complète du garage courant (super-admin). */
export async function loadGarage(): Promise<void> {
  try {
    session.garage = await get<Garage>('/garage')
  } catch {
    session.garage = null
  }
}

/** Compteurs de la barre latérale (demandes nouvelles, voitures du mois). */
export async function refreshSidebar(): Promise<void> {
  if (!session.user || !hasGarage.value) return
  try {
    const d = await get<{ usage: { vehicles_month: number; quota: number } }>('/dashboard')
    session.usage = d.usage
  } catch {
    /* sans importance */
  }
  if (isManager.value) {
    try {
      const l = await get<{ new_count: number }>('/leads?status=new')
      session.newLeads = l.new_count
    } catch {
      /* sans importance */
    }
  }
}

export async function refreshGarages(): Promise<void> {
  try {
    const me = await get<MeResponse>('/me')
    session.garages = me.garages
  } catch {
    /* garde l'ancienne liste */
  }
}

export async function switchGarage(id: number | null): Promise<void> {
  auth.setGarageId(id)
  session.garageId = id
  session.garage = null
  session.usage = null
  session.newLeads = 0
  if (id) {
    await loadGarage()
    refreshSidebar()
  }
  session.viewKey++
}

export async function login(email: string, password: string): Promise<void> {
  const r = await post<{ token: string; user: User }>('/auth/login', { email, password, device_name: 'web' })
  auth.setToken(r.token)
  await loadMe()
}

/** Ferme la session côté serveur ; l'appelant navigue vers /login puis appelle clearSession(). */
export async function logout(): Promise<void> {
  try {
    await post('/auth/logout')
  } catch {
    /* le jeton est peut-être déjà invalide */
  }
  auth.setToken(null)
}

export function clearSession() {
  auth.setToken(null)
  session.user = null
  session.garage = null
  session.garages = []
  session.loaded = false
}

let toastTimer: ReturnType<typeof setTimeout> | undefined
export function toast(text: string, kind: 'ok' | 'error' = 'ok') {
  session.toast = { text, kind, id: Date.now() }
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => (session.toast = null), kind === 'error' ? 6000 : 3500)
}
