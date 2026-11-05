<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, get, patch, post, errorMessage } from '../../api'
import { GARAGE_STATUSES, garageStatusLabel, monthName, pct, shortDateTime, usd } from '../../labels'
import { refreshGarages, switchGarage, toast } from '../../session'
import AppModal from '../../components/AppModal.vue'
import SiteThemeModal from '../../components/SiteThemeModal.vue'
import type { AdminGarageRow, AdminOverview, AiConfig, Garage } from '../../types'

const route = useRoute()
const router = useRouter()
const data = ref<AdminOverview | null>(null)
const configs = ref<AiConfig[]>([])
const error = ref('')

async function load() {
  error.value = ''
  try {
    const [o, c] = await Promise.all([get<AdminOverview>('/admin/overview'), get<{ data: AiConfig[] }>('/admin/ai-configs')])
    data.value = o
    configs.value = c.data
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(load)

function saveQuota(g: AdminGarageRow, input: HTMLInputElement) {
  const raw = input.value.trim()
  const n = raw === '' ? null : Math.max(0, Math.round(Number(raw)))
  if (raw !== '' && !Number.isFinite(n)) {
    input.value = g.quota == null ? '' : String(g.quota)
    return
  }
  if (n === g.quota) return
  update(g, 'vehicle_quota', n)
}

async function update(g: AdminGarageRow, field: 'status' | 'ai_config_id' | 'show_usage' | 'vehicle_quota', value: string | boolean | number | null) {
  // Le champ de l'API « vehicle_quota » s'appelle « quota » dans la ligne du tableau.
  const key = field === 'vehicle_quota' ? 'quota' : field
  const row = g as unknown as Record<string, unknown>
  const before = row[key]
  const v = field === 'ai_config_id' ? (value ? Number(value) : null) : value
  row[key] = v
  try {
    await patch<Garage>(`/admin/garages/${g.id}`, { [field]: v })
    toast(`${g.name} : réglage enregistré.`)
    if (field === 'status' || field === 'vehicle_quota') refreshGarages()
  } catch (e) {
    row[key] = before
    toast(errorMessage(e), 'error')
  }
}

async function open(g: AdminGarageRow) {
  await switchGarage(g.id)
  router.push('/')
}

// ---------- ajouter un garage ----------
const addOpen = ref(route.query.ajouter === '1')
const form = reactive({ name: '', city: '', status: 'onboarding', vehicle_quota: '40' })
const addErrors = ref<Record<string, string>>({})
const adding = ref(false)

function closeAdd() {
  addOpen.value = false
  if (route.query.ajouter) router.replace({ query: {} })
}

async function addGarage() {
  addErrors.value = {}
  if (!form.name.trim()) {
    addErrors.value.name = 'Indiquez le nom du garage.'
    return
  }
  adding.value = true
  try {
    const g = await post<Garage>('/admin/garages', {
      name: form.name.trim(),
      city: form.city.trim() || null,
      status: form.status,
      vehicle_quota: form.vehicle_quota ? Number(form.vehicle_quota) : null,
    })
    toast(`${g.name} est créé.`)
    form.name = ''
    form.city = ''
    closeAdd()
    await Promise.all([load(), refreshGarages()])
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) for (const [k, m] of Object.entries(e.errors)) addErrors.value[k] = m[0]
    else addErrors.value.name = errorMessage(e)
  } finally {
    adding.value = false
  }
}

const perCarOk = computed(() => {
  const v = data.value?.cost_per_vehicle_usd
  return v !== null && v !== undefined && Number(v) < 0.5
})

// Présentation du site public : choix du thème et de ses réglages pour un garage.
const themeFor = ref<AdminGarageRow | null>(null)
</script>

<template>
  <div class="page-head">
    <h1>Vue d'ensemble, {{ monthName() }}</h1>
    <button type="button" class="btn btn-primary" @click="addOpen = true">Ajouter un garage</button>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

  <div class="stats">
    <template v-if="!data">
      <div v-for="i in 5" :key="i" class="skel" style="height: 96px; border-radius: 12px" />
    </template>
    <template v-else>
      <div class="stat"><span class="stat-label">Garages actifs</span><span class="stat-value">{{ data.garages_active }}</span><span class="stat-sub">pilotes et abonnés</span></div>
      <div class="stat"><span class="stat-label">Voitures traitées</span><span class="stat-value">{{ data.vehicles_month }}</span></div>
      <div class="stat"><span class="stat-label">Coût des photos IA</span><span class="stat-value">{{ usd(data.cost_usd_month) }}</span></div>
      <div class="stat">
        <span class="stat-label">Par voiture</span>
        <span class="stat-value" :style="{ color: data.cost_per_vehicle_usd === null ? undefined : perCarOk ? '#1E7A50' : '#A55A0B' }">{{ usd(data.cost_per_vehicle_usd) }}</span>
        <span class="stat-sub">objectif : moins de 0,50 $</span>
      </div>
      <div class="stat">
        <span class="stat-label">Photos refaites</span><span class="stat-value">{{ pct(data.redo_rate) }}</span><span class="stat-sub">objectif : moins de 20 %</span>
      </div>
    </template>
  </div>

  <section aria-labelledby="gar" class="col" style="gap: 10px">
    <div class="row between">
      <h2 id="gar" style="font-size: 18px">Garages</h2>
      <RouterLink to="/mending/comparisons" class="btn-link" style="display: inline-flex; align-items: center">Comparer les modèles avant de choisir</RouterLink>
    </div>
    <div class="table-wrap">
      <table class="t" style="min-width: 860px">
        <thead>
          <tr>
            <th>Garage</th>
            <th>Voitures</th>
            <th>Coût IA</th>
            <th>Par voiture</th>
            <th>Refaites</th>
            <th>Réglage photo</th>
            <th>Statut</th>
            <th>Site</th>
            <th title="Affiche « Ce mois : x / quota voitures » dans le menu du garage">Compteur</th>
          </tr>
        </thead>
        <tbody v-if="data">
          <tr v-for="g in data.garages" :key="g.id">
            <td>
              <div class="row" style="flex-wrap: nowrap; gap: 10px">
                <span class="ini" :style="{ background: g.color }">{{ g.initials }}</span>
                <div class="col" style="gap: 2px">
                  <button type="button" class="gname" :aria-label="`Ouvrir l'espace de ${g.name}`" @click="open(g)">{{ g.name }}</button>
                  <span class="muted" style="font-size: 12px">{{ garageStatusLabel(g.status) }}{{ g.todo_count ? ` · ${g.todo_count} à faire` : '' }}</span>
                </div>
              </div>
            </td>
            <td>
              <label class="quota" :title="`Voitures comprises par mois dans l'abonnement de ${g.name}`">
                <span>{{ g.vehicles_month }} /</span>
                <span class="sr-only">Voitures comprises par mois pour {{ g.name }}</span>
                <input
                  class="f f-sm q-in"
                  type="number"
                  min="0"
                  step="1"
                  inputmode="numeric"
                  :value="g.quota ?? ''"
                  placeholder="∞"
                  @change="saveQuota(g, ($event.target as HTMLInputElement))"
                  @keydown.enter="($event.target as HTMLInputElement).blur()"
                />
              </label>
            </td>
            <td>{{ usd(g.cost_usd) }}</td>
            <td>{{ usd(g.cost_per_vehicle_usd) }}</td>
            <td>{{ pct(g.redo_rate) }}</td>
            <td>
              <label>
                <span class="sr-only">Réglage photo de {{ g.name }}</span>
                <select class="f f-sm sel" :value="g.ai_config_id ?? ''" @change="update(g, 'ai_config_id', ($event.target as HTMLSelectElement).value)">
                  <option value="">Par défaut</option>
                  <option v-for="c in configs" :key="c.id" :value="c.id" :disabled="!c.active">{{ c.name }}{{ c.active ? '' : ' (inactif)' }}</option>
                </select>
              </label>
            </td>
            <td>
              <label>
                <span class="sr-only">Statut de {{ g.name }}</span>
                <select class="f f-sm sel" :value="g.status" @change="update(g, 'status', ($event.target as HTMLSelectElement).value)">
                  <option v-for="s in GARAGE_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
              </label>
            </td>
            <td>
              <button type="button" class="btn btn-sm site-btn" :aria-label="`Présentation du site de ${g.name} : ${g.site_theme}`" @click="themeFor = g">{{ g.site_theme }}</button>
            </td>
            <td>
              <label class="chk">
                <input type="checkbox" :checked="g.show_usage" @change="update(g, 'show_usage', ($event.target as HTMLInputElement).checked)" />
                <span class="sr-only">Afficher le compteur de voitures à {{ g.name }}</span>
              </label>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="data && !data.garages.length" class="empty">
        <strong>Aucun garage</strong>
        <button type="button" class="btn btn-primary" @click="addOpen = true">Ajouter un garage</button>
      </div>
    </div>
  </section>

  <section aria-labelledby="err" class="col" style="gap: 10px">
    <h2 id="err" style="font-size: 18px">Dernières erreurs</h2>
    <div class="table-wrap">
      <div v-if="data && !data.errors.length" class="empty">Aucune erreur récente.</div>
      <div v-for="(e, i) in data?.errors ?? []" :key="i" class="erow">
        <span class="mono muted" style="width: 96px">{{ shortDateTime(e.at) }}</span>
        <span class="lvl" :class="e.level">{{ e.level === 'error' ? 'Erreur' : 'Alerte' }}</span>
        <span style="font-size: 14px; flex: 1; white-space: normal">{{ e.message || 'Erreur sans message' }}</span>
        <span class="mono" style="color: var(--ink-2)">{{ e.ref }}</span>
      </div>
    </div>
  </section>

  <SiteThemeModal v-if="themeFor" :garage-id="themeFor.id" :garage-name="themeFor.name" @close="themeFor = null" @saved="(n) => themeFor && (themeFor.site_theme = n)" />

  <AppModal v-if="addOpen" title="Ajouter un garage" @close="closeAdd">
    <form class="col" style="gap: 14px" novalidate @submit.prevent="addGarage">
      <label class="lbl">Nom du garage<input v-model="form.name" class="f" :aria-invalid="!!addErrors.name" /><span v-if="addErrors.name" class="err">{{ addErrors.name }}</span></label>
      <label class="lbl">Ville<input v-model="form.city" class="f" /></label>
      <div class="grid-fields">
        <label class="lbl">
          Statut
          <select v-model="form.status" class="f">
            <option v-for="s in GARAGE_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </label>
        <label class="lbl">Voitures par mois<input v-model="form.vehicle_quota" class="f" inputmode="numeric" /></label>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" :disabled="adding"><span v-if="adding" class="spinner" aria-hidden="true" />Créer le garage</button>
    </form>
  </AppModal>
</template>

<style scoped>
.site-btn {
  white-space: nowrap;
}
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 10px;
}
.ini {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  color: #fff;
  font-size: 11px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.gname {
  background: none;
  border: 0;
  padding: 0;
  font-weight: 500;
  color: var(--ink);
  text-align: left;
  min-height: 28px;
}
.gname:hover {
  color: var(--accent);
  text-decoration: underline;
}
.quota {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.q-in {
  width: 68px;
}
.chk {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 44px;
  min-height: 44px;
  cursor: pointer;
}
.chk input {
  width: 20px;
  height: 20px;
  margin: 0;
}
.sel {
  width: auto;
  min-width: 130px;
  max-width: 210px;
}
.erow {
  display: flex;
  gap: 16px;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--line-2);
  min-width: 640px;
}
.erow:last-child {
  border-bottom: 0;
}
.lvl {
  font-size: 12px;
  font-weight: 500;
  border-radius: 6px;
  padding: 3px 8px;
  width: 72px;
  text-align: center;
  color: #A55A0B;
  background: #FCF1E3;
}
.lvl.error {
  color: #C0392B;
  background: #FBECEB;
}
</style>
