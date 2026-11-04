// Mise en forme pendant la frappe : tirets des plaques, espaces des milliers.
// Les fonctions renvoient la valeur affichée et replacent le curseur au bon endroit.

const THIN = ' ' // même espace que Intl.NumberFormat('fr-FR')

/**
 * Plaque : jusqu'à 7 caractères, découpage automatique en 2-3-2 avec tirets (AB-123-CD), quoi qu'on tape.
 * Au-delà de 7 caractères, c'est un numéro de série : on retire les tirets.
 */
export function formatPlate(raw: string): string {
  const c = raw.toUpperCase().replace(/[^A-Z0-9]/g, '')
  if (c.length > 7) return c.slice(0, 17)
  if (c.length <= 2) return c
  if (c.length <= 5) return `${c.slice(0, 2)}-${c.slice(2)}`
  return `${c.slice(0, 2)}-${c.slice(2, 5)}-${c.slice(5)}`
}

/** Vrai si la plaque a la forme AB-123-CD. */
export function isFullPlate(v: string): boolean {
  return /^[A-Z]{2}-\d{3}-[A-Z]{2}$/.test(v)
}

/** Nombre entier avec espaces des milliers (19 300). */
export function formatThousands(raw: string): string {
  const d = raw.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 9)
  return d.replace(/\B(?=(\d{3})+(?!\d))/g, THIN)
}

function apply(e: Event, format: (s: string) => string, significant: RegExp): string {
  const el = e.target as HTMLInputElement
  const raw = el.value
  const caret = el.selectionStart ?? raw.length
  // Nombre de caractères utiles avant le curseur, pour le replacer après la mise en forme.
  const before = (raw.slice(0, caret).match(new RegExp(significant.source, 'g')) ?? []).length
  const out = format(raw)
  if (out !== raw) {
    el.value = out
    let pos = 0
    let seen = 0
    while (pos < out.length && seen < before) {
      if (significant.test(out[pos])) seen++
      pos++
    }
    try {
      el.setSelectionRange(pos, pos)
    } catch {
      /* champ sans sélection (type number) */
    }
  }
  return out
}

/** @input d'un champ plaque : `@input="plate = onPlateInput($event)"` */
export function onPlateInput(e: Event): string {
  return apply(e, formatPlate, /[A-Za-z0-9]/)
}

/** @input d'un champ prix ou kilométrage : `@input="form.price = onThousandsInput($event)"` */
export function onThousandsInput(e: Event): string {
  return apply(e, formatThousands, /\d/)
}
