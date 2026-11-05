<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError, get, post, errorMessage } from '../../api'
import { STATUS, usd, vehicleTitle } from '../../labels'
import { session } from '../../session'
import type { AiConfig, Comparison, ComparisonListItem, Vehicle } from '../../types'

const router = useRouter()
const list = ref<ComparisonListItem[] | null>(null)
const configs = ref<AiConfig[]>([])
const error = ref('')
const creating = ref(false)

async function load() {
  error.value = ''
  try {
    const [l, c] = await Promise.all([get<{ data: ComparisonListItem[] }>('/admin/comparisons'), get<{ data: AiConfig[] }>('/admin/ai-configs')])
    list.value = l.data
    configs.value = c.data
    if (!l.data.length) startCreate()
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(load)

const form = reactive({ name: '', garage_id: (session.garageId ?? session.garages[0]?.id ?? null) as number | null, configs: [] as number[], vehicles: [] as number[] })
const vehicles = ref<Vehicle[] | null>(null)
const vehError = ref('')
const errors = ref<Record<string, string>>({})
const saving = ref(false)

async function loadVehicles() {
  vehicles.value = null
  vehError.value = ''
  form.vehicles = []
  if (!form.garage_id) return
  try {
    const r = await get<{ data: Vehicle[] }>('/vehicles', { garageId: form.garage_id })
    vehicles.value = r.data.filter((v) => (v.photos_count ?? 0) > 0)
    form.vehicles = vehicles.value.slice(0, 10).map((v) => v.id)
  } catch (e) {
    vehError.value = errorMessage(e)
  }
}
watch(
  () => form.garage_id,
  () => {
    loadVehicles()
    const g = session.garages.find((x) => x.id === form.garage_id)
    if (g && !form.name) form.name = `Test ${g.name}`
  },
)
function startCreate() {
  creating.value = true
  if (!form.configs.length) form.configs = configs.value.filter((x) => x.active).slice(0, 3).map((x) => x.id)
  if (!vehicles.value) loadVehicles()
  const g = session.garages.find((x) => x.id === form.garage_id)
  if (g && !form.name) form.name = `Test ${g.name}`
}

const estimate = computed(() => {
  const unit = configs.value.filter((c) => form.configs.includes(c.id)).reduce((t, c) => t + Number(c.unit_cost_usd), 0)
  const photos = (vehicles.value ?? []).filter((v) => form.vehicles.includes(v.id)).reduce((t, v) => t + Math.min(v.photos_count ?? 6, 8), 0)
  return { photos: photos * form.configs.length, cost: photos * unit }
})

function toggle(arr: number[], id: number) {
  const i = arr.indexOf(id)
  if (i >= 0) arr.splice(i, 1)
  else arr.push(id)
}

async function create() {
  errors.value = {}
  if (!form.name.trim()) errors.value.name = 'Donnez un nom au test.'
  if (!form.garage_id) errors.value.garage_id = 'Choisissez un garage.'
  if (!form.configs.length) errors.value.ai_config_ids = 'Choisissez au moins un réglage.'
  if (form.configs.length > 6) errors.value.ai_config_ids = 'Six réglages au maximum.'
  if (!form.vehicles.length) errors.value.vehicle_ids = 'Choisissez au moins une voiture.'
  if (form.vehicles.length > 30) errors.value.vehicle_ids = 'Trente voitures au maximum.'
  if (Object.keys(errors.value).length) return
  saving.value = true
  try {
    const c = await post<Comparison>('/admin/comparisons', { name: form.name.trim(), garage_id: form.garage_id, ai_config_ids: form.configs, vehicle_ids: form.vehicles })
    router.push(`/mending/comparisons/${c.id}`)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      for (const [k, m] of Object.entries(e.errors)) errors.value[k.split('.')[0]] = m[0]
      if (!Object.keys(e.errors).length) errors.value.form = e.message
    } else errors.value.form = errorMessage(e)
  } finally {
    saving.value = false
  }
}

const garageName = (id?: number) => session.garages.find((g) => g.id === id)?.name ?? ''
const statusLabel = (s: string) => (s === 'running' ? 'En cours' : s === 'done' ? 'Terminé' : s)
</script>

<template>
  <div class="page-head">
    <div class="col" style="gap: 4px">
      <h1>Comparer les modèles</h1>
      <p class="lead" style="margin: 0">Mêmes photos, plusieurs réglages, notés côte à côte avant de choisir celui d'un garage.</p>
    </div>
    <button v-if="!creating" type="button" class="btn btn-primary" @click="startCreate">Nouveau test</button>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

  <form v-if="creating" class="card" novalidate @submit.prevent="create">
    <div class="row between">
      <h2>Nouveau test</h2>
      <button v-if="list?.length" type="button" class="btn-link" @click="creating = false">Annuler</button>
    </div>
    <div class="grid-fields">
      <label class="lbl">Nom du test<input v-model="form.name" class="f" placeholder="Ex. Pilote Valognes, 10 voitures" :aria-invalid="!!errors.name" /><span v-if="errors.name" class="err">{{ errors.name }}</span></label>
      <label class="lbl">
        Garage
        <select v-model.number="form.garage_id" class="f" :aria-invalid="!!errors.garage_id">
          <option v-for="g in session.garages" :key="g.id" :value="g.id">{{ g.name }}</option>
        </select>
        <span v-if="errors.garage_id" class="err">{{ errors.garage_id }}</span>
      </label>
    </div>

    <fieldset class="fs">
      <legend class="lbl">Réglages à comparer (6 au maximum)</legend>
      <div class="opts">
        <label v-for="c in configs" :key="c.id" class="opt" :class="{ on: form.configs.includes(c.id), off: !c.active }">
          <input type="checkbox" :checked="form.configs.includes(c.id)" @change="toggle(form.configs, c.id)" />
          <span class="col" style="gap: 2px; min-width: 0">
            <span style="font-weight: 500">{{ c.name }}</span>
            <span class="muted" style="font-size: 12px">Méthode {{ c.method }} · {{ c.quality }} · {{ usd(c.unit_cost_usd, 3) }} / image{{ c.active ? '' : ' · inactif' }}</span>
          </span>
        </label>
      </div>
      <span v-if="errors.ai_config_ids" class="err">{{ errors.ai_config_ids }}</span>
    </fieldset>

    <fieldset class="fs">
      <legend class="lbl">Voitures de ce garage ({{ form.vehicles.length }} choisie{{ form.vehicles.length > 1 ? 's' : '' }})</legend>
      <p v-if="vehError" class="alert alert-error">{{ vehError }}</p>
      <div v-else-if="!vehicles" class="opts"><div v-for="i in 4" :key="i" class="skel" style="height: 56px" /></div>
      <p v-else-if="!vehicles.length" class="muted" style="margin: 0">Aucune voiture avec des photos dans ce garage.</p>
      <div v-else class="col" style="gap: 8px">
        <div class="row">
          <button type="button" class="btn btn-sm" @click="form.vehicles = vehicles.slice(0, 30).map((v) => v.id)">Tout choisir</button>
          <button type="button" class="btn btn-sm" @click="form.vehicles = []">Aucune</button>
        </div>
        <div class="opts">
          <label v-for="v in vehicles" :key="v.id" class="opt" :class="{ on: form.vehicles.includes(v.id) }">
            <input type="checkbox" :checked="form.vehicles.includes(v.id)" @change="toggle(form.vehicles, v.id)" />
            <img v-if="v.cover_url" :src="v.cover_url" alt="" class="thumb" style="width: 48px; height: 36px" />
            <span class="col" style="gap: 2px; min-width: 0">
              <span class="plate" style="align-self: flex-start; font-size: 12px">{{ v.plate }}</span>
              <span style="font-size: 13px">{{ vehicleTitle(v, false) }} · {{ v.photos_count }} photo{{ (v.photos_count ?? 0) > 1 ? 's' : '' }} · {{ STATUS[v.status].label }}</span>
            </span>
          </label>
        </div>
      </div>
      <span v-if="errors.vehicle_ids" class="err">{{ errors.vehicle_ids }}</span>
    </fieldset>

    <p v-if="errors.form" class="alert alert-error" role="alert" style="margin: 0">{{ errors.form }}</p>
    <div class="est">
      <span style="font-size: 14px">Environ <strong>{{ estimate.photos }} photos</strong> à générer, soit <strong>{{ usd(estimate.cost) }}</strong></span>
      <button type="submit" class="btn btn-primary" :disabled="saving || !vehicles"><span v-if="saving" class="spinner" aria-hidden="true" />Lancer le test</button>
    </div>
  </form>

  <section class="col" style="gap: 10px">
    <h2>Tests précédents</h2>
    <div class="table-wrap">
      <table class="t" style="min-width: 640px">
        <thead><tr><th>Test</th><th>Garage</th><th>Réglages</th><th>Voitures</th><th>État</th><th>Créé le</th></tr></thead>
        <tbody v-if="list">
          <tr v-for="c in list" :key="c.id" class="clickable" @click="router.push(`/mending/comparisons/${c.id}`)">
            <td><RouterLink :to="`/mending/comparisons/${c.id}`" style="font-weight: 500" @click.stop>{{ c.name }}</RouterLink></td>
            <td>{{ garageName(c.garage_id) }}</td>
            <td>{{ c.ai_config_ids.length }}</td>
            <td>{{ c.vehicle_ids.length }}</td>
            <td><span class="pill" :style="c.status === 'running' ? { background: '#E0F0FF', color: '#00527C' } : { background: '#E9F5EE', color: '#1E7A50' }">{{ statusLabel(c.status) }}</span></td>
            <td>{{ new Date(c.created_at).toLocaleDateString('fr-FR') }}</td>
          </tr>
        </tbody>
        <tbody v-else-if="!error">
          <tr v-for="i in 3" :key="i"><td colspan="6"><span class="skel" style="display: block; height: 20px" /></td></tr>
        </tbody>
      </table>
      <div v-if="list && !list.length" class="empty">Aucun test pour l'instant.</div>
    </div>
  </section>
</template>

<style scoped>
.fs {
  border: 0;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 0;
}
.fs legend {
  margin-bottom: 8px;
}
.opts {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 8px;
}
.opt {
  display: flex;
  gap: 10px;
  align-items: center;
  border: 1.5px solid var(--line);
  border-radius: 10px;
  padding: 8px 12px;
  min-height: 56px;
  cursor: pointer;
  background: #fff;
}
.opt.on {
  border-color: var(--accent);
  background: var(--accent-soft);
}
.opt.off {
  opacity: 0.7;
}
.opt input {
  width: 20px;
  height: 20px;
  margin: 0;
  flex-shrink: 0;
  accent-color: var(--accent);
}
.est {
  background: var(--ground);
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
}
</style>
