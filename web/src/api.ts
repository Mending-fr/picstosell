// Petit client HTTP pour l'API picstosell (/api/v1).
// Ajoute le jeton Bearer et, pour un super-admin, l'en-tête X-Garage-Id du garage choisi.

const BASE = '/api/v1'
const TOKEN_KEY = 'pts.token'
const GARAGE_KEY = 'pts.garage'

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>
  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
  /** Premier message d'erreur d'un champ, pour l'afficher sous le champ. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0]
  }
}

function read(key: string): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}
function write(key: string, value: string | null) {
  try {
    if (value === null) localStorage.removeItem(key)
    else localStorage.setItem(key, value)
  } catch {
    /* stockage indisponible : on garde en mémoire seulement */
  }
}

let token: string | null = read(TOKEN_KEY)
let garageId: number | null = Number(read(GARAGE_KEY)) || null
let onUnauthorized: () => void = () => {}

export const auth = {
  get token() {
    return token
  },
  setToken(t: string | null) {
    token = t
    write(TOKEN_KEY, t)
  },
  get garageId() {
    return garageId
  },
  setGarageId(id: number | null) {
    garageId = id
    write(GARAGE_KEY, id === null ? null : String(id))
  },
  onUnauthorized(fn: () => void) {
    onUnauthorized = fn
  },
}

export interface RequestOptions {
  method?: string
  body?: unknown
  /** Remplace le garage courant pour cette requête (ex. comparatifs Mending). */
  garageId?: number | null
  signal?: AbortSignal
}

function headers(opts: RequestOptions, json: boolean): Headers {
  const h = new Headers({ Accept: 'application/json' })
  if (json) h.set('Content-Type', 'application/json')
  if (token) h.set('Authorization', `Bearer ${token}`)
  const g = opts.garageId !== undefined ? opts.garageId : garageId
  if (g) h.set('X-Garage-Id', String(g))
  return h
}

async function handle(res: Response): Promise<Response> {
  if (res.ok) return res
  let message = 'Une erreur est survenue. Réessayez dans un instant.'
  let errors: Record<string, string[]> = {}
  try {
    const data = await res.json()
    if (data?.message) message = data.message
    if (data?.errors) errors = data.errors
  } catch {
    /* réponse sans JSON */
  }
  if (res.status === 401) {
    message = 'Votre session a expiré, reconnectez-vous.'
    onUnauthorized()
  } else if (res.status === 403 && message === 'This action is unauthorized.') {
    message = "Vous n'avez pas accès à cette action."
  } else if (res.status >= 500) {
    message = 'Le serveur a rencontré un problème. Réessayez dans un instant.'
  }
  throw new ApiError(res.status, message, errors)
}

async function send(path: string, opts: RequestOptions = {}): Promise<Response> {
  const isForm = opts.body instanceof FormData
  const init: RequestInit = {
    method: opts.method ?? (opts.body === undefined ? 'GET' : 'POST'),
    headers: headers(opts, opts.body !== undefined && !isForm),
    signal: opts.signal,
  }
  if (opts.body !== undefined) init.body = isForm ? (opts.body as FormData) : JSON.stringify(opts.body)
  let res: Response
  try {
    res = await fetch(BASE + path, init)
  } catch (e) {
    if ((e as Error).name === 'AbortError') throw e
    throw new ApiError(0, 'Pas de connexion au serveur. Vérifiez votre connexion internet.')
  }
  return handle(res)
}

export async function api<T = unknown>(path: string, opts: RequestOptions = {}): Promise<T> {
  const res = await send(path, opts)
  if (res.status === 204) return undefined as T
  const text = await res.text()
  return (text ? JSON.parse(text) : undefined) as T
}

export const get = <T>(path: string, opts: Omit<RequestOptions, 'method' | 'body'> = {}) => api<T>(path, opts)
export const post = <T>(path: string, body: unknown = {}, opts: RequestOptions = {}) =>
  api<T>(path, { ...opts, method: 'POST', body })
export const patch = <T>(path: string, body: unknown, opts: RequestOptions = {}) =>
  api<T>(path, { ...opts, method: 'PATCH', body })
export const del = (path: string, opts: RequestOptions = {}) => api<void>(path, { ...opts, method: 'DELETE' })

/** Télécharge une réponse binaire authentifiée et propose le fichier à l'utilisateur. */
export async function download(path: string, fallbackName: string) {
  const res = await send(path)
  const blob = await res.blob()
  const cd = res.headers.get('Content-Disposition') ?? ''
  const m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(cd)
  const name = m ? decodeURIComponent(m[1]) : fallbackName
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = name
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 2000)
}

/** Message lisible pour n'importe quelle erreur. */
export function errorMessage(e: unknown): string {
  if (e instanceof ApiError) return e.message
  if (e instanceof Error && e.message) return e.message
  return 'Une erreur est survenue.'
}
