// Types tirés de docs/API.md (contrat v1).

export type Role = 'super_admin' | 'manager' | 'photographer'
export type VehicleStatus = 'to_photograph' | 'processing' | 'to_review' | 'ready' | 'online' | 'sold' | 'archived'
export type Fuel = 'essence' | 'diesel' | 'hybride' | 'hybride_rechargeable' | 'electrique' | 'gpl' | 'autre'
export type Gearbox = 'manuelle' | 'automatique'
export type ShotKey = 'front' | 'front_left' | 'front_right' | 'left' | 'right' | 'rear' | 'rear_left' | 'rear_right'
export type PhotoKind = 'exterior' | 'interior' | 'detail'
export type RenderStatus = 'pending' | 'processing' | 'to_review' | 'approved' | 'failed' | 'kept_original'
export type RenderRating = 'publishable' | 'redo' | 'unusable'
export type LeadType = 'vehicle' | 'trade_in' | 'other'
export type LeadStatus = 'new' | 'done'
export type AiMethod = 'A' | 'B'
export type AiProvider = 'fake' | 'openai' | 'gemini' | 'fal'
export type AiQuality = '1K' | '2K'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  garage_id: number | null
  invited?: boolean
}

export interface GarageSummary {
  id: number
  name: string
  slug: string
  status: string
  initials: string
  color: string
  todo_count: number
  is_demo?: boolean
}

export interface GarageSettings {
  hide_plate: boolean
  add_logo: boolean
  review_before_publish: boolean
  export_size: 'marketplace' | 'large' | string
}

export interface Garage {
  id: number
  name: string
  slug: string
  status: string
  city: string
  phone: string
  email: string
  address: string
  hours: string
  about: string
  brand_color: string
  logo_url: string | null
  domain: string | null
  vehicle_quota: number
  site_url: string
  settings: GarageSettings
  ai_config_id: number | null
}

export interface EquipmentGroup {
  group: string
  items: string[]
}

export interface Photo {
  id: number
  vehicle_id: number
  shot_key: ShotKey | null
  kind: PhotoKind
  label: string | null
  display_label: string
  /** 'ai' : rangée par l'IA, 'user' : corrigée à la main, null : non rangée. */
  classified: 'ai' | 'user' | null
  url: string
  thumb_url: string
  position: number
}

export interface Render {
  id: number
  photo_id: number
  vehicle_id: number
  shot_key: ShotKey | null
  status: RenderStatus
  url: string | null
  provider: string
  model: string
  method: AiMethod
  cost_usd: string
  duration_ms: number | null
  error: string | null
  rating: RenderRating | null
  attempt: number
  ai_config_id: number | null
  comparison_id: number | null
}

export interface Vehicle {
  id: number
  plate: string
  vin: string | null
  make: string | null
  model: string | null
  version: string | null
  first_registration: string | null
  fuel: Fuel | null
  gearbox: Gearbox | null
  power_hp: number | null
  fiscal_hp: number | null
  color: string | null
  doors: number | null
  seats: number | null
  mileage: number | null
  price: number | null
  description: string | null
  equipment: EquipmentGroup[]
  history_url: string | null
  status: VehicleStatus
  photographer_id?: number | null
  days_in_stock: number
  leads_count: number
  photos_count?: number
  cover_url: string | null
  published_at: string | null
  sold_at: string | null
  created_at: string
  photos?: Photo[]
  renders?: Render[]
}

export type VehicleInput = Partial<Omit<Vehicle, 'id' | 'status' | 'days_in_stock' | 'leads_count' | 'cover_url' | 'photos' | 'renders' | 'created_at' | 'published_at' | 'sold_at'>> & {
  photographer_id?: number | null
}

export interface PlateLookup {
  found: boolean
  plate?: string
  message?: string
  demo?: boolean
  data: {
    make: string | null
    model: string | null
    version: string | null
    first_registration: string | null
    fuel: Fuel | null
    gearbox: Gearbox | null
    power_hp: number | null
    fiscal_hp: number | null
    color: string | null
    doors: number | null
    seats: number | null
    vin: string | null
  } | Record<string, never> | null
}

export interface Placement {
  /** Hauteur de la voiture, en % de la hauteur de la photo du showroom. */
  height: number
  bottom: number
  offset_x: number
}

export interface Shot {
  id: number
  showroom_id: number
  key: ShotKey
  label: string
  position: number
  image_url: string | null
  placement: Placement
  configured: boolean
  overlay_url: string
}

export interface Showroom {
  id: number
  name: string
  shots: Shot[]
}

export interface Lead {
  id: number
  type: LeadType
  name: string
  phone: string
  email: string | null
  message: string | null
  vehicle: {
    id: number
    label: string
    price: number | null
    plate?: string
    mileage?: number | null
    year?: number | null
    fuel?: string | null
    status?: VehicleStatus
    cover_url?: string | null
    published_at?: string | null
  } | null
  trade_in: { plate: string; mileage: number | null } | null
  status: LeadStatus
  note: string | null
  source: string
  created_at: string
}

export interface AiConfig {
  id: number
  name: string
  provider: AiProvider | string
  model: string
  method: AiMethod
  quality: AiQuality | string
  prompt: string
  unit_cost_usd: string
  active: boolean
}

export type TodoAction = 'review' | 'publish' | 'request_photos' | 'unpublish' | 'open_lead'

export interface TodoItem {
  type: 'vehicle' | 'lead'
  id: number
  plate: string | null
  title: string
  detail: string
  status: string
  action: TodoAction
}

export interface Dashboard {
  counts: {
    to_photograph: number
    processing: number
    to_review: number
    ready: number
    online: number
    sold_month: number
    online_without_leads: number
    sold_still_online: number
  }
  todo: TodoItem[]
  usage: { vehicles_month: number; quota: number }
}

export interface MeResponse {
  user: User
  garage: Garage | null
  garages: GarageSummary[]
}

export interface Usage {
  month: string
  vehicles: number
  quota: number
  renders: number
  cost_usd: string
  extras: { label?: string; quantity?: number; amount?: number | string; [k: string]: unknown }[]
}

export interface AdminGarageRow extends GarageSummary {
  vehicles_month: number
  quota: number | null
  cost_usd: string
  cost_per_vehicle_usd: string | null
  redo_rate: number | null
  ai_config_id: number | null
  show_usage: boolean
  site_theme: string
}

export interface ThemeOption {
  key: string
  label: string
  type: 'text' | 'textarea' | 'number' | 'toggle'
  default?: string | number | boolean
  help?: string
}
export interface SiteTheme {
  key: string
  name: string
  description: string
  extends: string | null
  reserved: boolean
  options: ThemeOption[]
  values: Record<string, string | number | boolean | null>
  preview_url: string
}
export interface SiteThemes {
  current: string
  site_url: string
  themes: SiteTheme[]
}

export interface AdminError {
  at: string
  level: string
  message: string
  ref: string | null
}

export interface AdminOverview {
  garages_active: number
  vehicles_month: number
  cost_usd_month: string
  cost_per_vehicle_usd: string | null
  redo_rate: number | null
  garages: AdminGarageRow[]
  errors: AdminError[]
}

export interface ComparisonListItem {
  id: number
  name: string
  status: string
  garage_id?: number
  created_at: string
  ai_config_ids: number[]
  vehicle_ids: number[]
}

export interface ComparisonSummaryRow {
  ai_config_id: number
  rated: number
  publishable: number
  redo: number
  unusable: number
  publishable_pct: number | null
  cost_per_vehicle_usd: string | null
  avg_duration_ms: number | null
}

export interface ComparisonCell {
  label?: string
  source_url: string | null
  renders: Record<string, Render | null>
}

export interface Comparison {
  id: number
  name: string
  status: string
  garage_id?: number
  configs: AiConfig[]
  vehicles: { id: number; plate: string; label: string }[]
  summary: ComparisonSummaryRow[]
  estimate_usd: string
  grid: Record<string, Record<string, ComparisonCell>>
}
