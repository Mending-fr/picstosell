<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError, get, post, errorMessage } from '../api'
import { FUELS, GEARBOXES, parseNum, plateNormalize } from '../labels'
import { session, isManager, toast, refreshSidebar } from '../session'
import PhotoPicker, { type PickedPhoto } from '../components/PhotoPicker.vue'
import { uploadPhotos } from '../uploads'
import type { PlateLookup, User, Vehicle } from '../types'

const router = useRouter()

const plate = ref('')
const looking = ref(false)
const lookup = ref<'idle' | 'found' | 'notfound'>('idle')
const lookupMsg = ref('')
const plateErr = ref('')

const form = reactive({
  make: '',
  model: '',
  version: '',
  first_registration: '',
  fuel: '' as string,
  gearbox: '' as string,
  power_hp: '',
  fiscal_hp: '',
  color: '',
  doors: '',
  seats: '',
  vin: '',
  mileage: '',
  price: '',
  history_url: '',
})
const errors = ref<Record<string, string>>({})

const mode = ref<'phone' | 'upload'>('phone')
const users = ref<User[]>([])
const photographer = ref<number | null>(null)
const photos = ref<PickedPhoto[]>([])
const saving = ref(false)
const saveStep = ref('')
const formError = ref('')
const step2 = ref<HTMLElement | null>(null)

onMounted(async () => {
  if (!isManager.value) return
  try {
    users.value = (await get<{ data: User[] }>('/users')).data
    const ph = users.value.find((u) => u.role === 'photographer')
    photographer.value = ph?.id ?? session.user?.id ?? null
  } catch {
    /* la liste n'est pas indispensable */
  }
})

const showDetails = computed(() => lookup.value !== 'idle')

async function find() {
  plateErr.value = ''
  const p = plate.value.trim()
  if (p.length < 4) {
    plateErr.value = 'Tapez la plaque complète, par exemple AB-123-CD.'
    return
  }
  plate.value = plateNormalize(p)
  looking.value = true
  try {
    const r = await get<PlateLookup>(`/lookup/plate/${encodeURIComponent(plate.value)}`)
    if (r.plate) plate.value = r.plate
    const d = (r.data ?? {}) as Partial<NonNullable<PlateLookup['data']>> & Record<string, unknown>
    if (r.found) {
      form.make = d.make ?? ''
      form.model = d.model ?? ''
      form.version = d.version ?? ''
      form.first_registration = d.first_registration ? String(d.first_registration).slice(0, 7) : ''
      form.fuel = d.fuel ?? ''
      form.gearbox = d.gearbox ?? ''
      form.power_hp = d.power_hp != null ? String(d.power_hp) : ''
      form.fiscal_hp = d.fiscal_hp != null ? String(d.fiscal_hp) : ''
      form.color = d.color ?? ''
      form.doors = d.doors != null ? String(d.doors) : ''
      form.seats = d.seats != null ? String(d.seats) : ''
      form.vin = d.vin ?? ''
      lookup.value = 'found'
    } else {
      lookup.value = 'notfound'
      lookupMsg.value = r.message ?? "Cette plaque n'est pas dans la base. Complétez les informations à la main."
    }
  } catch (e) {
    lookup.value = 'notfound'
    lookupMsg.value = `${errorMessage(e)} Vous pouvez compléter à la main.`
  } finally {
    looking.value = false
  }
  await nextTick()
  step2.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  ;(step2.value?.querySelector(lookup.value === 'found' ? '#f-mileage' : '#f-make') as HTMLInputElement | null)?.focus({ preventScroll: true })
}

function manual() {
  if (!plate.value.trim()) {
    plateErr.value = 'Tapez au moins la plaque.'
    return
  }
  plate.value = plateNormalize(plate.value)
  lookup.value = 'notfound'
  lookupMsg.value = 'Complétez les informations à la main.'
}

const saveLabel = computed(() => (mode.value === 'phone' ? 'Enregistrer et demander les photos' : photos.value.length ? 'Enregistrer et préparer les photos' : 'Enregistrer sans photo'))

function payload() {
  const n = (s: string) => parseNum(s)
  return {
    plate: plate.value.trim(),
    make: form.make.trim() || null,
    model: form.model.trim() || null,
    version: form.version.trim() || null,
    first_registration: form.first_registration ? `${form.first_registration}-01` : null,
    fuel: form.fuel || null,
    gearbox: form.gearbox || null,
    power_hp: n(form.power_hp),
    fiscal_hp: n(form.fiscal_hp),
    color: form.color.trim() || null,
    doors: n(form.doors),
    seats: n(form.seats),
    vin: form.vin.trim() || null,
    mileage: n(form.mileage),
    price: n(form.price),
    history_url: form.history_url.trim() || null,
    photographer_id: mode.value === 'phone' ? photographer.value : null,
  }
}

async function save() {
  errors.value = {}
  formError.value = ''
  if (form.history_url.trim() && !/^https?:\/\//i.test(form.history_url.trim())) {
    errors.value.history_url = 'Collez un lien complet qui commence par https://'
    return
  }
  saving.value = true
  saveStep.value = 'Enregistrement de la voiture…'
  let v: Vehicle
  try {
    v = await post<Vehicle>('/vehicles', payload())
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      for (const [k, msgs] of Object.entries(e.errors)) errors.value[k] = msgs[0]
      formError.value = 'Certaines informations sont à corriger.'
    } else formError.value = errorMessage(e)
    saving.value = false
    return
  }
  if (mode.value === 'upload' && photos.value.length) {
    saveStep.value = `Envoi des photos (0 sur ${photos.value.length})…`
    const total = photos.value.length
    const list = photos.value
    const sent = await uploadPhotos(v.id, list)
    const failed = total - sent
    const exterior = list.some((p) => p.state === 'done' && p.slot !== 'interior' && p.slot !== 'detail')
    if (exterior) {
      saveStep.value = 'Préparation des photos showroom…'
      try {
        await post(`/vehicles/${v.id}/renders`)
      } catch (e) {
        toast(`Photos envoyées, mais la préparation n'a pas démarré : ${errorMessage(e)}`, 'error')
      }
    }
    if (failed) toast(`${failed} photo${failed > 1 ? 's' : ''} n'${failed > 1 ? 'ont' : 'a'} pas pu être envoyée${failed > 1 ? 's' : ''}. Ajoutez-la depuis la fiche.`, 'error')
    else toast('Voiture ajoutée. Les photos de showroom sont en préparation.')
  } else if (mode.value === 'phone') {
    const who = users.value.find((u) => u.id === photographer.value)
    toast(who && who.id !== session.user?.id ? `Voiture ajoutée. ${who.name} la verra dans son appli.` : 'Voiture ajoutée. Elle attend ses photos.')
  } else {
    toast('Voiture ajoutée.')
  }
  refreshSidebar()
  router.replace(`/vehicles/${v.id}`)
}
</script>

<template>
  <div class="col" style="gap: 6px">
    <RouterLink to="/vehicles" class="back">‹ Mon stock</RouterLink>
    <h1>Ajouter une voiture</h1>
  </div>

  <section class="card" aria-labelledby="s1">
    <div class="row"><span class="step" aria-hidden="true">1</span><h2 id="s1" class="h2">La plaque</h2></div>
    <form class="row" style="align-items: flex-end" @submit.prevent="find">
      <label class="lbl" style="flex: 1 1 240px">
        Plaque d'immatriculation
        <input
          v-model="plate"
          class="f f-plate"
          style="font-size: 18px; min-height: 52px"
          autocomplete="off"
          autocapitalize="characters"
          placeholder="AB-123-CD"
          :aria-invalid="!!plateErr"
          aria-describedby="plate-help"
          :disabled="saving"
        />
      </label>
      <button type="submit" class="btn btn-primary" style="min-height: 52px; padding: 0 22px" :disabled="looking || saving">
        <span v-if="looking" class="spinner" aria-hidden="true" />
        Chercher la voiture
      </button>
    </form>
    <p v-if="plateErr" class="err" role="alert" style="margin: 0">{{ plateErr }}</p>
    <span v-if="lookup === 'idle'" id="plate-help" class="muted" style="font-size: 14px">
      On retrouve la marque, le modèle, la version et les caractéristiques. Vous n'avez plus qu'à ajouter le kilométrage et le prix.
      <button type="button" class="btn-link" style="min-height: 0; font-size: 14px" @click="manual">Saisir à la main</button>
    </span>
  </section>

  <template v-if="showDetails">
    <section ref="step2" class="card" aria-labelledby="s2">
      <div class="row between">
        <div class="row"><span class="step" aria-hidden="true">2</span><h2 id="s2" class="h2">La voiture</h2></div>
        <span v-if="lookup === 'found'" class="pill" style="color: #0b5e39; background: #e3f4ea">Trouvée : vérifiez et complétez</span>
        <span v-else class="pill" style="color: #8a4307; background: #fff1de">À compléter</span>
      </div>
      <p v-if="lookup === 'notfound'" class="alert alert-warn" style="margin: 0">{{ lookupMsg }}</p>
      <div class="grid-fields">
        <label class="lbl">Marque<input id="f-make" v-model="form.make" class="f" :aria-invalid="!!errors.make" /></label>
        <label class="lbl">Modèle<input v-model="form.model" class="f" :aria-invalid="!!errors.model" /></label>
        <label class="lbl">Version<input v-model="form.version" class="f" placeholder="Ex. 1.0 TCe 90 Evolution" /></label>
        <label class="lbl">1re mise en circulation<input v-model="form.first_registration" class="f" type="month" :aria-invalid="!!errors.first_registration" /></label>
        <label class="lbl">
          Carburant
          <select v-model="form.fuel" class="f">
            <option value="">Choisir</option>
            <option v-for="o in FUELS" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </label>
        <label class="lbl">
          Boîte
          <select v-model="form.gearbox" class="f">
            <option value="">Choisir</option>
            <option v-for="o in GEARBOXES" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </label>
        <label class="lbl">Puissance (ch)<input v-model="form.power_hp" class="f" inputmode="numeric" :aria-invalid="!!errors.power_hp" /></label>
        <label class="lbl">Puissance fiscale (CV)<input v-model="form.fiscal_hp" class="f" inputmode="numeric" /></label>
        <label class="lbl">Couleur<input v-model="form.color" class="f" /></label>
        <label class="lbl">Portes<input v-model="form.doors" class="f" inputmode="numeric" :aria-invalid="!!errors.doors" /></label>
        <label class="lbl">Places<input v-model="form.seats" class="f" inputmode="numeric" :aria-invalid="!!errors.seats" /></label>
      </div>
      <div class="grid-fields warm">
        <label class="lbl">
          Kilométrage
          <input id="f-mileage" v-model="form.mileage" class="f" placeholder="Ex. 19 300" inputmode="numeric" :aria-invalid="!!errors.mileage" />
          <span v-if="errors.mileage" class="err">{{ errors.mileage }}</span>
        </label>
        <label class="lbl">
          Prix de vente TTC (€)
          <input v-model="form.price" class="f" placeholder="Ex. 31 900" inputmode="numeric" :aria-invalid="!!errors.price" />
          <span v-if="errors.price" class="err">{{ errors.price }}</span>
        </label>
      </div>
      <label class="lbl">
        Lien HistoVec envoyé par l'ancien propriétaire (facultatif)
        <input v-model="form.history_url" class="f" type="url" placeholder="Collez le lien ici" :aria-invalid="!!errors.history_url" />
        <span v-if="errors.history_url" class="err">{{ errors.history_url }}</span>
      </label>
    </section>

    <section class="card" aria-labelledby="s3">
      <div class="row"><span class="step" aria-hidden="true">3</span><h2 id="s3" class="h2">Les photos</h2></div>
      <div role="radiogroup" aria-label="Qui prend les photos" class="modes">
        <button type="button" role="radio" class="mode" :aria-checked="mode === 'phone'" @click="mode = 'phone'">
          <span class="mode-t">Sur le parking, au téléphone</span>
          <span class="mode-x">La voiture apparaît dans l'appli de la personne choisie, avec les photos à faire.</span>
        </button>
        <button type="button" role="radio" class="mode" :aria-checked="mode === 'upload'" @click="mode = 'upload'">
          <span class="mode-t">J'ai déjà les photos</span>
          <span class="mode-x">Importez-les depuis l'ordinateur. On les place quand même dans votre showroom.</span>
        </button>
      </div>
      <label v-if="mode === 'phone' && isManager && users.length" class="lbl" style="max-width: 360px">
        Qui s'en occupe ?
        <select v-model="photographer" class="f">
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.id === session.user?.id ? `Moi (${u.name})` : u.name }}</option>
        </select>
      </label>
      <PhotoPicker v-if="mode === 'upload'" v-model="photos" :disabled="saving" />
    </section>

    <p v-if="formError" class="alert alert-error" role="alert" style="margin: 0">{{ formError }}</p>
    <div class="row" style="justify-content: flex-end">
      <span v-if="saving" class="muted" role="status" style="margin-right: auto">{{ saveStep }}</span>
      <RouterLink to="/vehicles" class="btn btn-lg">Annuler</RouterLink>
      <button type="button" class="btn btn-dark btn-lg" :disabled="saving" @click="save">
        <span v-if="saving" class="spinner" aria-hidden="true" />
        {{ saveLabel }}
      </button>
    </div>
  </template>
</template>

<style scoped>
.h2 {
  font-size: 18px;
}
.step {
  width: 28px;
  height: 28px;
  border-radius: 14px;
  background: var(--ink);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.warm {
  background: #fff7ed;
  border: 1px solid #f3d9b5;
  border-radius: 12px;
  padding: 14px;
}
.modes {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
}
.mode {
  text-align: left;
  border: 2px solid var(--line);
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: var(--ink);
}
.mode[aria-checked='true'] {
  border-color: var(--accent);
  background: var(--accent-soft);
}
.mode-t {
  font-size: 16px;
  font-weight: 700;
}
.mode-x {
  font-size: 14px;
  color: var(--ink-2);
}
</style>
