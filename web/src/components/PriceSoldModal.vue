<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AppModal from './AppModal.vue'
import { get, post, errorMessage } from '../api'
import { euros, parseNum, num, vehicleTitle, days, STATUS } from '../labels'
import { toast } from '../session'
import type { Vehicle } from '../types'

const props = defineProps<{ vehicle?: Vehicle | null }>()
const emit = defineEmits<{ close: []; changed: [v: Vehicle] }>()

const plate = ref(props.vehicle?.plate ?? '')
const results = ref<Vehicle[]>([])
const searching = ref(false)
const picked = ref<Vehicle | null>(props.vehicle ?? null)
const price = ref(props.vehicle?.price ? num(props.vehicle.price) : '')
const busy = ref(false)
const error = ref('')
const done = ref<'' | 'price' | 'sold'>('')

let timer: ReturnType<typeof setTimeout> | undefined
let seq = 0
watch(plate, (v) => {
  if (picked.value && v === picked.value.plate) return
  picked.value = null
  done.value = ''
  clearTimeout(timer)
  const q = v.trim()
  if (q.length < 2) {
    results.value = []
    return
  }
  timer = setTimeout(async () => {
    const my = ++seq
    searching.value = true
    try {
      const r = await get<{ data: Vehicle[] }>(`/vehicles?q=${encodeURIComponent(q)}`)
      if (my !== seq) return
      results.value = r.data.filter((x) => x.status !== 'archived').slice(0, 6)
      if (results.value.length === 1) pick(results.value[0])
    } catch (e) {
      error.value = errorMessage(e)
    } finally {
      if (my === seq) searching.value = false
    }
  }, 250)
})

function pick(v: Vehicle) {
  picked.value = v
  plate.value = v.plate
  price.value = v.price ? num(v.price) : ''
  results.value = []
  error.value = ''
}

const sub = computed(() => {
  const v = picked.value
  if (!v) return ''
  const parts = [STATUS[v.status].label]
  if (v.status === 'online') parts[0] = `En ligne depuis ${days(v.days_in_stock)}`
  else parts.push(`en stock depuis ${days(v.days_in_stock)}`)
  parts.push(`${v.leads_count} ${v.leads_count > 1 ? 'demandes' : 'demande'}`)
  return parts.join(' · ')
})

const oldPrice = ref<number | null>(null)

async function savePrice() {
  if (!picked.value) return
  const p = parseNum(price.value)
  if (p === null) {
    error.value = 'Indiquez un prix en euros.'
    return
  }
  busy.value = true
  error.value = ''
  oldPrice.value = picked.value.price
  try {
    const v = await post<Vehicle>(`/vehicles/${picked.value.id}/price`, { price: p })
    picked.value = { ...picked.value, ...v }
    done.value = 'price'
    emit('changed', picked.value)
    toast(`Nouveau prix enregistré : ${euros(p)}`)
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
    done.value = 'sold'
    emit('changed', picked.value)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <AppModal title="Changer un prix ou marquer vendue" @close="emit('close')">
    <label class="lbl">
      Plaque
      <input v-model="plate" class="f f-plate" autocomplete="off" placeholder="AB-123-CD" aria-describedby="ps-help" />
    </label>
    <span v-if="!picked && !results.length" id="ps-help" class="muted" style="font-size: 13px; margin-top: -8px">
      <template v-if="searching">Recherche…</template>
      <template v-else-if="plate.trim().length >= 2">Aucune voiture trouvée avec cette plaque.</template>
      <template v-else>Tapez la plaque ou le modèle.</template>
    </span>

    <div v-if="results.length && !picked" class="col" style="gap: 6px" role="listbox" aria-label="Voitures trouvées">
      <button v-for="r in results" :key="r.id" type="button" role="option" :aria-selected="false" class="hit" @click="pick(r)">
        <span class="plate">{{ r.plate }}</span>
        <span class="grow" style="text-align: left">{{ vehicleTitle(r, false) }}</span>
        <span class="muted">{{ euros(r.price) }}</span>
      </button>
    </div>

    <template v-if="picked">
      <div class="car">
        <img v-if="picked.cover_url" :src="picked.cover_url" alt="" class="car-img" />
        <div v-else class="car-img" />
        <div class="col" style="gap: 2px; min-width: 0">
          <span style="font-weight: 700">{{ vehicleTitle(picked) }}</span>
          <span class="muted" style="font-size: 13px">{{ sub }}</span>
        </div>
      </div>

      <div v-if="done === 'sold'" class="alert alert-ok" role="status">
        Vendue. L'annonce est retirée de votre site. Pensez à la retirer de Leboncoin et La Centrale.
      </div>
      <template v-else-if="picked.status === 'sold'">
        <div class="alert alert-info">Cette voiture est déjà marquée vendue.</div>
      </template>
      <template v-else>
        <label class="lbl">
          Nouveau prix
          <input v-model="price" class="f" inputmode="numeric" placeholder="Ex. 16 490" @keydown.enter="savePrice" />
        </label>
        <span class="muted" style="font-size: 13px; margin-top: -8px">
          <template v-if="done === 'price'">Enregistré. Ancien prix : {{ euros(oldPrice) || 'aucun' }}.</template>
          <template v-else>Prix actuel : {{ euros(picked.price) || 'pas encore de prix' }}. Votre site est mis à jour tout de suite.</template>
        </span>
        <div class="row">
          <button type="button" class="btn btn-dark btn-lg grow" :disabled="busy" @click="savePrice">Enregistrer le prix</button>
          <button type="button" class="btn btn-lg btn-danger" :disabled="busy" @click="markSold">Marquer vendue</button>
        </div>
      </template>
    </template>

    <p v-if="error" class="alert alert-error" role="alert" style="margin: 0">{{ error }}</p>
    <button v-if="done" type="button" class="btn btn-dark btn-lg" @click="emit('close')">Fermer</button>
  </AppModal>
</template>

<style scoped>
.hit {
  display: flex;
  gap: 12px;
  align-items: center;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 8px 12px;
  min-height: 48px;
  color: var(--ink);
}
.hit:hover {
  border-color: var(--accent);
}
.car {
  display: flex;
  gap: 14px;
  align-items: center;
  background: var(--ground);
  border-radius: 12px;
  padding: 12px;
}
.car-img {
  width: 72px;
  height: 54px;
  border-radius: 8px;
  background: #dce3f2;
  flex-shrink: 0;
  object-fit: cover;
}
</style>
