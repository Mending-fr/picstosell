// Rôles et petits textes communs à la gestion des utilisateurs (garage et Mending).
import type { Role } from './types'

export const ROLE_LABEL: Record<Role, string> = {
  super_admin: 'Admin Mending',
  manager: 'Gérant',
  photographer: 'Photographe',
}

export const ROLE_HELP: Record<Role, string> = {
  super_admin: "Toute l'espace Mending et tous les garages.",
  manager: 'Tout faire dans le garage : voitures, prix, site, demandes, équipe.',
  photographer: "Ajouter des voitures et prendre les photos avec l'appli.",
}

export function since(iso: string | null | undefined): string {
  if (!iso) return 'Jamais connecté'
  const d = new Date(iso)
  const days = Math.floor((Date.now() - d.getTime()) / 86400000)
  if (days <= 0) return "Aujourd'hui"
  if (days === 1) return 'Hier'
  if (days < 30) return `Il y a ${days} jours`
  return d.toLocaleDateString('fr-FR')
}

export function until(iso: string): string {
  const days = Math.ceil((new Date(iso).getTime() - Date.now()) / 86400000)
  if (days <= 0) return 'Expirée'
  return days === 1 ? 'Expire demain' : `Expire dans ${days} jours`
}
