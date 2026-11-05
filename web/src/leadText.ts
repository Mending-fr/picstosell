// Libellés communs à la liste des demandes et à la page d'une demande.
import { LEAD_TYPES } from './labels'
import type { Lead } from './types'

export const LEAD_KIND: Record<string, string> = { vehicle: 'Une voiture', trade_in: 'Reprise', other: 'Autre demande' }

export function leadSubject(l: Lead): string {
  if (l.vehicle) return l.vehicle.label
  if (l.type === 'trade_in') return l.trade_in?.plate ? `Reprise ${l.trade_in.plate}` : 'Demande de reprise'
  return LEAD_TYPES[l.type] ?? 'Demande'
}

export function leadSource(l: Lead): string {
  if (l.source === 'fiche' && l.vehicle) return `la fiche ${l.vehicle.label}`
  if (l.source === 'contact') return 'le formulaire de contact'
  if (l.source === 'reprise') return 'le formulaire de reprise'
  return l.source ? `« ${l.source} »` : 'votre site'
}

export function telHref(p: string): string {
  return `tel:${p.replace(/[^\d+]/g, '')}`
}
