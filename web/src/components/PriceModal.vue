<script setup lang="ts">
import { onThousandsInput } from '../inputs'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import AppModal from './AppModal.vue'
import { get, post, errorMessage } from '../api'
import { euros, parseNum, num, vehicleTitle, days, STATUS } from '../labels'
import { refreshSidebar } from '../session'
import { stockChanged } from '../modals'
import type { Vehicle } from '../types'

const props = defineProps<{ vehicle?: Vehicle | null }>()
const emit = defineEmits<{ close: []; changed: [v: Vehicle] }>()

const STEPS = ['La voiture', 'Que faire', 'Confirmer']
const step = ref(props.vehicle ? 2 : 1)

// ---------- 1. la voiture ----------
const q = ref('')
const results = ref<Vehicle[]>([])
const searching = ref(false)
const searched = ref(false)
const picked = ref<Vehicle | null>(props.vehicle ?? null)

let timer: ReturnType<typeof setTimeout> | undefined
let seq = 0
watch(q, (v) => {
  clearTimeout(timer)
  error.value = ''
  const s = v.trim()
  if (s.length < 2) {
    results.value = []
    searched.value = false
    return
  }
  timer = setTimeout(async () => {
    const my = ++seq
    searching.value = true
    try {
      const r = await get<{ data: Vehicle[] }>(`/vehicles?q=${encodeURIComponent(s)}`)
      if (my !== seq) return
      results.value = r.data.filter((x) => x.status !== 'archived').slice(0, 6)
      searched.value = true
    } catch (e) {
      error.value = errorMessage(e)
    } finally {
      if (my === seq) searching.value = false
    }
  }, 250)
})
onBeforeUnmount(() => clearTimeout(timer))

function daysSince(iso: string | null): number | null {
  if (!iso) return null
  return Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 86400000))
}

function sub(v: Vehicle): string {
  const parts: string[] = []
  const online = daysSince(v.published_at)
  if (v.status === 'online' && online !== null) parts.push(`en ligne depuis ${days(online)}`)
  else if (v.status === 'sold') parts.push('vendue')
  else parts.push(`${STATUS[v.status].label.toLowerCase()}, en stock depuis ${days(v.days_in_stock)}`)
  parts.push(euros(v.price) || 'sans prix')
  return parts.join(' · ')
}

function pick(v: Vehicle) {
  picked.value = v
  price.value = v.price ? num(v.price) : ''
  action.value = 'price'
  error.value = ''
  step.value = 2
}

// ---------- 2. que faire ----------
const action = ref<'price' | 'sold'>('price')
const isSold = computed(() => picked.value?.status === 'sold')

// ---------- 3. confirmer ----------
const price = ref(props.vehicle?.price ? num(props.vehicle.price) : '')
const priceErr = ref('')
const busy = ref(false)
const error = ref('')
const oldPrice = ref<number | null>(null)
const newPrice = ref<number | null>(null)

const name = computed(() => (picked.value ? vehicleTitle(picked.value, false) : ''))

async function savePrice() {
  if (!picked.value) return
  priceErr.value = ''
  const p = parseNum(price.value)
  if (p === null || p <= 0) {
    priceErr.value = 'Indiquez un prix en euros.'
    return
  }
  busy.value = true
  error.value = ''
  oldPrice.value = picked.value.price
  try {
    const v = await post<Vehicle>(`/vehicles/${picked.value.id}/price`, { price: p })
    picked.value = { ...picked.value, ...v }
    newPrice.value = p
    step.value = 4
    emit('changed', picked.value)
    stockChanged()
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

async function markSold() {
  if (!picked.value) return
  busy.value = true
  error.value = ''
  try {
    const v = await post<Vehicle>(`/vehicles/${picked.value.id}/sold`)
    picked.value = { ...picked.value, ...v }
    step.value = 4
    emit('changed', picked.value)
    stockChanged()
    refreshSidebar()
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}

function next() {
  if (busy.value) return
  if (step.value === 1) {
    if (results.value.length === 1) pick(results.value[0])
    return
  }
  if (step.value === 2) {
    if (isSold.value) action.value = 'price'
    error.value = ''
    step.value = 3
    return
  }
  if (step.value === 3) {
    if (action.value === 'price') savePrice()
    else markSold()
  }
}

function back() {
  error.value = ''
  priceErr.value = ''
  step.value = Math.max(1, step.value - 1)
}

function close() {
  if (busy.value) return
  emit('close')
}

const nextLabel = computed(() => {
  if (step.value === 2) return 'Continuer'
  return action.value === 'sold' ? 'Oui, elle est vendue' : 'Enregistrer le prix'
})

const doneText = computed(() => {
  if (action.value === 'sold') return `${name.value} est vendue, son annonce est retirée de votre site`
  return `Nouveau prix enregistré : ${euros(newPrice.value)}`
})
</script>

<template>
  <AppModal title="Changer un prix ou marquer vendue" :steps="STEPS" :step="step" @close="close">
    <form id="price-step" class="col" style="gap: 16px" novalidate @submit.prevent="next">
      <!-- 1. La voiture -->
      <template v-if="step === 1">
        <label class="lbl big-lbl">
          Plaque ou modèle
          <input v-model="q" class="f big-in" type="search" autocomplete="off" placeholder="Ex. FT-208-AZ ou Duster" aria-describedby="ps-help" />
        </label>
        <span v-if="!results.length" id="ps-help" class="help" role="status">
          <template v-if="searching">Recherche…</template>
          <template v-else-if="searched">Aucune voiture trouvée avec « {{ q.trim() }} ».</template>
          <template v-else>Tapez au moins deux lettres ou chiffres.</template>
        </span>
        <div v-else class="col" style="gap: 8px" role="list" aria-label="Voitures trouvées">
          <button v-for="r in results" :key="r.id" type="button" role="listitem" class="hit" @click="pick(r)">
            <img v-if="r.cover_url" :src="r.cover_url" alt="" class="hit-img" />
            <span v-else class="hit-img" />
            <span class="col grow" style="gap: 3px; min-width: 0">
              <span class="row" style="gap: 8px; flex-wrap: nowrap">
                <span class="hit-t">{{ vehicleTitle(r, false) }}</span>
                <span class="plate">{{ r.plate }}</span>
              </span>
              <span class="hit-x">{{ sub(r) }}</span>
            </span>
          </button>
        </div>
      </template>

      <!-- 2. Que faire -->
      <template v-else-if="step === 2 && picked">
        <div class="row" style="gap: 8px">
          <span class="ctx">{{ name }}</span><span class="plate">{{ picked.plate }}</span>
        </div>
        <div role="radiogroup" aria-label="Que faire" class="col" style="gap: 10px">
          <button type="button" role="radio" class="act" :aria-checked="action === 'price'" @click="action = 'price'">
            <span class="act-t"><span class="dot" style="background: var(--accent)" />Changer le prix</span>
            <span class="act-x">{{ picked.price ? `Actuellement ${euros(picked.price)}` : 'Pas encore de prix' }}</span>
          </button>
          <button type="button" role="radio" class="act" :aria-checked="action === 'sold'" :disabled="isSold" @click="action = 'sold'">
            <span class="act-t"><span class="dot" style="background: #d9534f" />Marquer vendue</span>
            <span class="act-x">{{ isSold ? 'Elle est déjà marquée vendue' : "L'annonce est retirée de votre site" }}</span>
          </button>
        </div>
      </template>

      <!-- 3. Confirmer -->
      <template v-else-if="step === 3 && picked">
        <template v-if="action === 'price'">
          <label class="lbl big-lbl">
            Nouveau prix
            <span class="unit-f" :class="{ bad: !!priceErr }">
              <input :value="price" inputmode="numeric" placeholder="Ex. 16 490" @input="price = onThousandsInput($event)" :aria-invalid="!!priceErr" />
              <span>€ TTC</span>
            </span>
            <span v-if="priceErr" class="err" role="alert">{{ priceErr }}</span>
          </label>
          <span class="help">Prix actuel : {{ euros(picked.price) || 'pas encore de prix' }}. Votre site affiche le nouveau prix dès l'enregistrement.</span>
        </template>
        <template v-else>
          <span class="sold-t">{{ name }} est vendue ?</span>
          <span class="help" style="font-size: 15px">L'annonce disparaît de votre site. Pensez à la retirer aussi de Leboncoin et La Centrale.</span>
        </template>
      </template>

      <!-- Écran final -->
      <div v-else-if="step === 4" class="done">
        <span class="done-ico" aria-hidden="true">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1E7A50" stroke-width="2.5"><path d="M5 12l5 5 9-10" /></svg>
        </span>
        <span class="done-t" role="status">{{ doneText }}</span>
        <span v-if="action === 'price'" class="help">Ancien prix : {{ euros(oldPrice) || 'aucun' }}.</span>
        <span v-else class="help">Pensez à la retirer aussi de Leboncoin et La Centrale.</span>
      </div>

      <p v-if="error" class="alert alert-error" role="alert" style="margin: 0">{{ error }}</p>
    </form>

    <template #footer>
      <button v-if="step > 1 && step < 4 && !(step === 2 && props.vehicle)" type="button" class="btn btn-ghost btn-lg" :disabled="busy" @click="back">Retour</button>
      <button
        v-if="step === 2 || step === 3"
        type="submit"
        form="price-step"
        class="btn btn-lg"
        :class="step === 3 && action === 'sold' ? 'btn-danger-solid' : 'btn-primary'"
        style="margin-left: auto"
        :disabled="busy"
      >
        <span v-if="busy" class="spinner" aria-hidden="true" />
        {{ nextLabel }}
      </button>
      <button v-if="step === 4" type="button" class="btn btn-primary btn-lg" style="margin-left: auto" @click="emit('close')">Fermer</button>
    </template>
  </AppModal>
</template>

<style scoped>
.big-lbl {
  font-size: 14px;
  font-weight: 400;
  color: var(--muted);
  gap: 10px;
}
.big-in {
  font-size: 18px;
  min-height: 56px;
  padding: 0 16px;
}
.help {
  font-size: 14px;
  color: var(--muted-2);
  line-height: 1.5;
}
.hit {
  text-align: left;
  border: 1px solid var(--line);
  background: #fff;
  border-radius: 14px;
  padding: 12px 14px;
  display: flex;
  align-items: center;
  gap: 14px;
  color: var(--ink);
}
.hit:hover,
.hit:focus-visible {
  border-color: var(--accent-line);
  background: var(--accent-tint);
}
.hit-img {
  width: 56px;
  height: 42px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--accent-soft), var(--accent-band));
  flex-shrink: 0;
  object-fit: cover;
}
.hit-t {
  font-size: 15px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.hit-x {
  font-size: 13px;
  color: var(--muted-2);
}
.ctx {
  font-size: 14px;
  color: var(--muted);
}
.act {
  text-align: left;
  border: 1px solid var(--line);
  background: #fff;
  border-radius: 14px;
  padding: 14px 18px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  color: var(--ink);
}
.act:hover:not(:disabled) {
  border-color: var(--accent-line);
}
.act[aria-checked='true'] {
  border-color: var(--accent);
  background: var(--accent-tint);
}
.act-t {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 15px;
  font-weight: 500;
}
.act-t .dot {
  width: 10px;
  height: 10px;
  border-radius: 5px;
}
.act-x {
  font-size: 14px;
  color: var(--muted-2);
  padding-left: 20px;
}
.unit-f {
  display: flex;
  align-items: center;
  border: 1px solid var(--field);
  background: var(--field-bg);
  border-radius: 12px;
  padding: 0 16px;
  height: 60px;
  gap: 8px;
}
.unit-f:focus-within {
  border-color: var(--accent);
  background: #fff;
  box-shadow: 0 0 0 3px var(--accent-glow);
}
.unit-f.bad {
  border-color: var(--red-ink);
}
.unit-f input {
  font: inherit;
  font-size: 24px;
  font-weight: 500;
  border: 0;
  outline: 0;
  flex: 1;
  min-width: 0;
  color: var(--ink);
  background: transparent;
}
.unit-f span {
  color: var(--muted-3);
  white-space: nowrap;
}
.sold-t {
  font-size: 17px;
  font-weight: 500;
}
.done {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 12px;
  padding: 12px 0 4px;
}
.done-ico {
  width: 56px;
  height: 56px;
  border-radius: 28px;
  background: var(--green-tint);
  display: flex;
  align-items: center;
  justify-content: center;
}
.done-t {
  font-size: 20px;
  font-weight: 500;
  max-width: 440px;
  line-height: 1.35;
}
</style>
