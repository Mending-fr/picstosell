// Libellés français et mises en forme partagées.
import type { Fuel, Gearbox, LeadType, RenderStatus, ShotKey, Vehicle, VehicleStatus } from './types'

export const FUELS: { value: Fuel; label: string }[] = [
  { value: 'essence', label: 'Essence' },
  { value: 'diesel', label: 'Diesel' },
  { value: 'hybride', label: 'Hybride' },
  { value: 'hybride_rechargeable', label: 'Hybride rechargeable' },
  { value: 'electrique', label: 'Électrique' },
  { value: 'gpl', label: 'GPL' },
  { value: 'autre', label: 'Autre' },
]

export const GEARBOXES: { value: Gearbox; label: string }[] = [
  { value: 'manuelle', label: 'Manuelle' },
  { value: 'automatique', label: 'Automatique' },
]

export const SHOTS: { key: ShotKey; label: string }[] = [
  { key: 'front', label: 'De face' },
  { key: 'front_left', label: 'Avant gauche' },
  { key: 'front_right', label: 'Avant droit' },
  { key: 'left', label: 'Côté gauche' },
  { key: 'right', label: 'Côté droit' },
  { key: 'rear', label: 'Arrière' },
  { key: 'rear_left', label: 'Arrière gauche' },
  { key: 'rear_right', label: 'Arrière droit' },
]

/** Ordre conseillé pour les photos importées depuis l'ordinateur. */
export const UPLOAD_ORDER: ShotKey[] = ['front_left', 'front', 'front_right', 'right', 'rear', 'left']

export function shotLabel(key: string | null | undefined): string {
  if (!key) return 'Photo'
  return SHOTS.find((s) => s.key === key)?.label ?? key
}

export function fuelLabel(f: string | null | undefined) {
  return FUELS.find((x) => x.value === f)?.label ?? ''
}
export function gearboxLabel(g: string | null | undefined) {
  return GEARBOXES.find((x) => x.value === g)?.label ?? ''
}

export interface Pill {
  label: string
  fg: string
  bg: string
}

const P = {
  check: { fg: '#8A4307', bg: '#FFF1DE' },
  ok: { fg: '#0B5E39', bg: '#E3F4EA' },
  grey: { fg: '#3D414A', bg: '#EEEEEA' },
  red: { fg: '#9A1C1C', bg: '#FDECEC' },
  blue: { fg: '#163A9E', bg: '#E4EBFF' },
}

export const STATUS: Record<VehicleStatus, Pill & { cta: string }> = {
  to_photograph: { label: 'À photographier', ...P.grey, cta: 'Ajouter les photos' },
  processing: { label: 'Photos en préparation', ...P.blue, cta: 'Voir' },
  to_review: { label: 'À vérifier', ...P.check, cta: 'Vérifier' },
  ready: { label: 'Prête', ...P.ok, cta: 'Mettre en ligne' },
  online: { label: 'En ligne', ...P.ok, cta: 'Modifier' },
  sold: { label: 'Vendue', ...P.red, cta: 'Voir' },
  archived: { label: 'Archivée', ...P.grey, cta: 'Voir' },
}

export const PILL = P

export const RENDER_STATE: Record<RenderStatus | 'none' | 'asis', { label: string; color: string }> = {
  none: { label: 'Pas encore préparée', color: '#5B606B' },
  pending: { label: 'En préparation', color: '#1F4FD8' },
  processing: { label: 'En préparation', color: '#1F4FD8' },
  to_review: { label: 'À regarder', color: '#8A4307' },
  approved: { label: 'OK', color: '#0B5E39' },
  failed: { label: 'Raté, à refaire', color: '#9A1C1C' },
  kept_original: { label: 'Photo du parking', color: '#5B606B' },
  asis: { label: 'Telle quelle', color: '#5B606B' },
}

export const LEAD_TYPES: Record<LeadType, string> = {
  vehicle: 'Une voiture',
  trade_in: 'Reprise',
  other: 'Autre demande',
}

export const GARAGE_STATUSES: { value: string; label: string }[] = [
  { value: 'pilot', label: 'Pilote' },
  { value: 'onboarding', label: 'En installation' },
  { value: 'demo', label: 'Démo prévue' },
  { value: 'active', label: 'Actif' },
  { value: 'paused', label: 'En pause' },
]
export function garageStatusLabel(s: string) {
  return GARAGE_STATUSES.find((x) => x.value === s)?.label ?? s
}

export const PROVIDERS = [
  { value: 'fake', label: 'Simulation (tests)' },
  { value: 'openai', label: 'OpenAI' },
  { value: 'gemini', label: 'Google Gemini' },
  { value: 'fal', label: 'fal.ai' },
]
export const METHODS = [
  { value: 'A', label: 'A : génération complète' },
  { value: 'B', label: 'B : détourage puis décor' },
]

// ---------- mises en forme ----------

const nf = new Intl.NumberFormat('fr-FR')
export function num(n: number | null | undefined): string {
  return n === null || n === undefined ? '' : nf.format(n)
}
export function euros(n: number | null | undefined): string {
  return n === null || n === undefined ? '' : `${nf.format(n)} €`
}
export function km(n: number | null | undefined): string {
  return n === null || n === undefined ? '' : `${nf.format(n)} km`
}
export function usd(v: string | number | null | undefined, digits = 2): string {
  if (v === null || v === undefined || v === '') return '–'
  const n = typeof v === 'number' ? v : Number(v)
  return `${n.toLocaleString('fr-FR', { minimumFractionDigits: digits, maximumFractionDigits: digits })} $`
}
export function pct(v: number | null | undefined): string {
  if (v === null || v === undefined) return '–'
  return `${Math.round(v * 100)} %`
}
/** Parse un nombre saisi avec espaces, « € » ou « km ». */
export function parseNum(s: string | number | null | undefined): number | null {
  if (s === null || s === undefined) return null
  if (typeof s === 'number') return s
  const clean = s.replace(/[^\d]/g, '')
  return clean ? Number(clean) : null
}

export function days(n: number): string {
  return `${n} ${n > 1 ? 'jours' : 'jour'}`
}

export function vehicleTitle(v: Pick<Vehicle, 'make' | 'model' | 'version'>, withVersion = true): string {
  const t = [v.make, v.model, withVersion ? v.version : null].filter(Boolean).join(' ')
  return t || 'Voiture sans nom'
}

export function relative(iso: string): string {
  const d = new Date(iso)
  const diff = (Date.now() - d.getTime()) / 1000
  if (diff < 60) return "À l'instant"
  if (diff < 3600) return `Il y a ${Math.floor(diff / 60)} min`
  if (diff < 86400 && new Date().getDate() === d.getDate()) return `Il y a ${Math.floor(diff / 3600)} h`
  const y = new Date()
  y.setDate(y.getDate() - 1)
  if (d.toDateString() === y.toDateString()) return 'Hier'
  if (diff < 6 * 86400) return d.toLocaleDateString('fr-FR', { weekday: 'long' }).replace(/^./, (c) => c.toUpperCase())
  return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}

export function shortDateTime(iso: string): string {
  const d = new Date(iso)
  return `${d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })} ${d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`
}

export function todayLabel(): string {
  const s = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
  return s.charAt(0).toUpperCase() + s.slice(1)
}

export function monthName(): string {
  return new Date().toLocaleDateString('fr-FR', { month: 'long' })
}

export function plateNormalize(s: string): string {
  const c = s.toUpperCase().replace(/[^A-Z0-9]/g, '')
  if (/^[A-Z]{2}\d{3}[A-Z]{2}$/.test(c)) return `${c.slice(0, 2)}-${c.slice(2, 5)}-${c.slice(5)}`
  return s.toUpperCase().trim()
}

export function duration(ms: number | null | undefined): string {
  if (ms === null || ms === undefined) return '–'
  return ms < 1000 ? `${ms} ms` : `${(ms / 1000).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} s`
}

export async function copyText(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    const ta = document.createElement('textarea')
    ta.value = text
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.select()
    let ok = false
    try {
      ok = document.execCommand('copy')
    } catch {
      ok = false
    }
    ta.remove()
    return ok
  }
}

/** Texte d'annonce prêt à coller sur Leboncoin ou La Centrale. */
export function listingText(v: Vehicle): string {
  const lines = [vehicleTitle(v)]
  const facts = [
    v.first_registration ? `1re mise en circulation : ${new Date(v.first_registration).toLocaleDateString('fr-FR', { month: '2-digit', year: 'numeric' })}` : '',
    v.mileage !== null ? `Kilométrage : ${km(v.mileage)}` : '',
    v.fuel ? `Énergie : ${fuelLabel(v.fuel)}` : '',
    v.gearbox ? `Boîte : ${gearboxLabel(v.gearbox)}` : '',
    v.power_hp ? `Puissance : ${v.power_hp} ch${v.fiscal_hp ? ` (${v.fiscal_hp} CV)` : ''}` : '',
    v.color ? `Couleur : ${v.color}` : '',
    v.price ? `Prix : ${euros(v.price)} TTC` : '',
  ].filter(Boolean)
  lines.push('', ...facts)
  if (v.description) lines.push('', v.description)
  if (v.equipment?.length) {
    lines.push('', 'Équipements :')
    for (const g of v.equipment) if (g.items.length) lines.push(`${g.group} : ${g.items.join(', ')}`)
  }
  return lines.join('\n')
}
