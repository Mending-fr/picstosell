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

/** Angles extérieurs, dans l'ordre du tour de la voiture (celui de l'annonce). */
export const SHOTS: { key: ShotKey; label: string }[] = [
  { key: 'front_left', label: 'Avant gauche' },
  { key: 'front', label: 'De face' },
  { key: 'front_right', label: 'Avant droit' },
  { key: 'right', label: 'Côté droit' },
  { key: 'rear_right', label: 'Arrière droit' },
  { key: 'rear', label: 'Arrière' },
  { key: 'rear_left', label: 'Arrière gauche' },
  { key: 'left', label: 'Côté gauche' },
]

/** Libellés proposés pour l'intérieur et les détails (mêmes listes que le serveur). */
export const INTERIOR_LABELS = ['Tableau de bord', 'Compteur', 'Places avant', 'Places arrière', 'Coffre', 'Écran', 'Intérieur']
export const DETAIL_LABELS = ['Jante', 'Pneu', 'Phare', 'Défaut', 'Équipement', 'Document', 'Détail']

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

// Teintes sémantiques (mêmes valeurs que les variables de styles.css).
const P = {
  check: { fg: '#A55A0B', bg: '#FCF1E3' },
  ok: { fg: '#1E7A50', bg: '#E9F5EE' },
  grey: { fg: '#4A5263', bg: '#F1F3F6' },
  red: { fg: '#C0392B', bg: '#FBECEB' },
  blue: { fg: '#234670', bg: '#EEF3F9' },
  violet: { fg: '#5B3FC4', bg: '#EFEBFD' },
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
  none: { label: 'Pas encore préparée', color: '#5A6172' },
  pending: { label: 'En préparation', color: '#234670' },
  processing: { label: 'En préparation', color: '#234670' },
  to_review: { label: 'À regarder', color: '#A55A0B' },
  approved: { label: 'OK', color: '#1E7A50' },
  failed: { label: 'Raté, à refaire', color: '#C0392B' },
  kept_original: { label: 'Photo du parking', color: '#5A6172' },
  asis: { label: 'Telle quelle', color: '#5A6172' },
}

export const LEAD_TYPES: Record<LeadType, string> = {
  vehicle: 'Une voiture',
  trade_in: 'Reprise',
  other: 'Autre demande',
}

export const GARAGE_STATUSES: { value: string; label: string }[] = [
  { value: 'pilot', label: 'Pilote' },
  { value: 'onboarding', label: 'En installation' },
  { value: 'demo', label: 'Démo (données fictives)' },
  { value: 'active', label: 'Actif' },
  { value: 'paused', label: 'En pause' },
]
/** Libellé court pour les petits espaces (sélecteur de garage). */
export function garageStatusShort(s: string): string {
  return s === 'demo' ? 'Démo' : garageStatusLabel(s)
}

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
