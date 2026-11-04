// Modales globales de l'espace garage : « Ajouter une voiture » et « Changer un prix ou marquer vendue ».
// Ouvertes depuis l'accueil, Mon parc ou les raccourcis N et S ; montées une seule fois dans App.vue.
import { reactive } from 'vue'

export const modals = reactive({
  add: false,
  price: false,
  /** Incrémenté quand une modale a modifié le stock : les vues abonnées se rechargent. */
  version: 0,
})

export function openAdd() {
  modals.price = false
  modals.add = true
}

export function openPrice() {
  modals.add = false
  modals.price = true
}

export function stockChanged() {
  modals.version++
}

/** Vrai si la touche vient d'un champ de saisie : on ne déclenche alors aucun raccourci. */
export function typingIn(e: KeyboardEvent): boolean {
  const t = e.target as HTMLElement | null
  return !!t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)
}
