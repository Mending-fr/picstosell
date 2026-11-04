// Recherches de prix en arrière-plan : elles continuent quand la fenêtre change d'étape ou se ferme.
import { reactive } from 'vue'
import { post, errorMessage } from './api'
import { toast } from './session'
import { euros } from './labels'

export interface Comparable { title: string; url: string; price: number; year: number | null; mileage: number | null; site: string }
export interface PriceSource {
  provider: 'web' | 'autoways'
  label: string
  low?: number
  high?: number
  suggested?: number
  price?: number
  confidence?: 'low' | 'medium' | 'high'
  comment?: string
  comparables?: Comparable[]
}
export interface Advice { available: boolean; low: number | null; high: number | null; suggested: number | null; sources: PriceSource[]; notes: string[] }

export interface PriceJob {
  status: 'running' | 'done' | 'error'
  advice: Advice | null
  error: string
  label: string
  /** Nombre d'écrans qui affichent cette recherche : sans écran, on prévient par un message. */
  watchers: number
}

export const priceJobs = reactive(new Map<string, PriceJob>())

/** Clé stable d'une voiture décrite (mêmes champs = même recherche). */
export function carKey(car: Record<string, unknown>): string {
  const keys = ['plate', 'make', 'model', 'version', 'first_registration', 'mileage', 'fuel', 'gearbox', 'power_hp']
  return JSON.stringify(keys.map((k) => car[k] ?? null))
}

export function startPriceJob(car: Record<string, unknown>): string {
  const key = carKey(car)
  const existing = priceJobs.get(key)
  if (existing && existing.status !== 'error') return key
  const label = [car.make, car.model].filter(Boolean).join(' ') || 'la voiture'
  priceJobs.set(key, { status: 'running', advice: null, error: '', label, watchers: existing?.watchers ?? 0 })
  post<Advice>('/pricing/advice', car)
    .then((a) => {
      const job = priceJobs.get(key)
      if (!job) return
      job.advice = a
      job.status = 'done'
      if (job.watchers === 0) {
        toast(a.available ? `Prix estimé pour ${label} : ${euros(a.low)} à ${euros(a.high)}. Retrouvez-le sur sa fiche.` : `Pas assez d'annonces trouvées pour ${label}.`)
      }
    })
    .catch((e) => {
      const job = priceJobs.get(key)
      if (!job) return
      job.status = 'error'
      job.error = errorMessage(e)
      if (job.watchers === 0) toast(`La recherche de prix pour ${label} n'a pas abouti.`, 'error')
    })
  return key
}
