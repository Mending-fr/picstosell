<script setup lang="ts">
import { isFullPlate, onPlateInput, onThousandsInput } from '../inputs'
import PriceHelper from './PriceHelper.vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppModal from './AppModal.vue'
import PhotoPicker, { type PickedPhoto } from './PhotoPicker.vue'
import { ApiError, get, post, errorMessage } from '../api'
import { FUELS, GEARBOXES, fuelLabel, gearboxLabel, num, parseNum, plateNormalize } from '../labels'
import { session, isManager, refreshSidebar } from '../session'
import { stockChanged } from '../modals'
import { uploadPhotos } from '../uploads'
import type { PlateLookup, User, Vehicle } from '../types'

const emit = defineEmits<{ close: [] }>()
const router = useRouter()

const STEPS = ['La plaque', 'La voiture', 'Prix et km', 'Les photos']
const step = ref(1)

// ---------- étape 1 : la plaque ----------
const plate = ref('')
const looking = ref(false)
const lookup = ref<'idle' | 'found' | 'notfound'>('idle')
const lookupMsg = ref('')
const lookupDemo = ref(false)
// 7 caractères qui n'ont pas la forme AB-123-CD : on le signale sans bloquer (ancienne plaque possible).
const plateOdd = computed(() => plate.value.replace(/-/g, '').length === 7 && !isFullPlate(plate.value))
/** Saisie d'un numéro de série à la place de la plaque : pas de recherche, la plaque se saisit ensuite. */
const byVin = ref(false)

// ---------- étapes 2 et 3 : la voiture, prix et km ----------
const blank = () => ({
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
const form = reactive(blank())
const editing = ref(false)
const showHistory = ref(false)
const errors = ref<Record<string, string>>({})
const stepError = ref('')

// ---------- étape 4 : les photos ----------
const mode = ref<'phone' | 'upload'>('phone')
const users = ref<User[]>([])
const photographer = ref<number | null>(null)
const photos = ref<PickedPhoto[]>([])
const saving = ref(false)
const saveStep = ref('')

// ---------- écran final ----------
const created = ref<Vehicle | null>(null)
const doneText = ref('')

const busy = computed(() => looking.value || saving.value)

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

const VIN_RE = /^[A-HJ-NPR-Z0-9]{17}$/i

async function find() {
  errors.value = {}
  stepError.value = ''
  const raw = plate.value.trim()
  const compact = raw.replace(/[\s-]/g, '')
  if (VIN_RE.test(compact)) {
    byVin.value = true
    form.vin = compact.toUpperCase()
    plate.value = ''
    lookup.value = 'notfound'
    lookupMsg.value = 'La recherche se fait par la plaque. Complétez les informations et la plaque à la main.'
    editing.value = true
    step.value = 2
    return
  }
  if (raw.length < 4) {
    errors.value.plate = 'Tapez la plaque complète, par exemple AB-123-CD, ou le numéro de série.'
    return
  }
  byVin.value = false
  plate.value = plateNormalize(raw)
  looking.value = true
  try {
    const r = await get<PlateLookup>(`/lookup/plate/${encodeURIComponent(plate.value)}`)
    if (r.plate) plate.value = r.plate
    lookupDemo.value = !!r.demo
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
      form.vin = d.vin ?? form.vin
      lookup.value = 'found'
      editing.value = false
    } else {
      lookup.value = 'notfound'
      lookupMsg.value = r.message ?? "Cette plaque n'est pas dans la base. Complétez les informations à la main."
      editing.value = true
    }
  } catch (e) {
    lookup.value = 'notfound'
    lookupMsg.value = `${errorMessage(e)} Vous pouvez compléter à la main.`
    editing.value = true
  } finally {
    looking.value = false
  }
  step.value = 2
}

function manual() {
  errors.value = {}
  if (!plate.value.trim()) {
    errors.value.plate = 'Tapez au moins la plaque.'
    return
  }
  plate.value = plateNormalize(plate.value)
  lookup.value = 'notfound'
  lookupMsg.value = 'Complétez les informations à la main.'
  editing.value = true
  step.value = 2
}

const found = computed(() => lookup.value === 'found')
const carName = computed(() => [form.make, form.model].filter(Boolean).join(' ').trim())
const facts = computed(() => {
  const parts: string[] = []
  if (form.first_registration) {
    const d = new Date(`${form.first_registration}-01T12:00:00`)
    if (!Number.isNaN(d.getTime())) {
      const s = d.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
      parts.push(s.charAt(0).toUpperCase() + s.slice(1))
    }
  }
  if (form.fuel) parts.push(fuelLabel(form.fuel))
  if (form.gearbox) parts.push(gearboxLabel(form.gearbox))
  if (form.power_hp) parts.push(`${form.power_hp} ch`)
  return parts.join(' · ')
})

function nextFromCar() {
  errors.value = {}
  stepError.value = ''
  if (byVin.value && !plate.value.trim()) {
    errors.value.plate = 'Indiquez la plaque de la voiture.'
    editing.value = true
    return
  }
  if (plate.value.trim()) plate.value = plateNormalize(plate.value)
  step.value = 3
}

function nextFromPrice() {
  errors.value = {}
  stepError.value = ''
  if (form.history_url.trim() && !/^https?:\/\//i.test(form.history_url.trim())) {
    errors.value.history_url = 'Collez un lien complet qui commence par https://'
    showHistory.value = true
    return
  }
  step.value = 4
}

const who = computed(() => users.value.find((u) => u.id === photographer.value) ?? null)
const phoneText = computed(() => {
  if (who.value && who.value.id !== session.user?.id) return `La voiture apparaît dans l'appli de ${who.value.name}.`
  return "La voiture apparaît dans votre appli, avec les photos à faire."
})

const sentCount = computed(() => photos.value.filter((p) => p.state === 'done').length)

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

const priceCar = computed(() => ({
  plate: plate.value || null,
  make: form.make,
  model: form.model,
  version: form.version || null,
  first_registration: form.first_registration || null,
  mileage: parseNum(form.mileage),
  fuel: form.fuel || null,
  gearbox: form.gearbox || null,
  power_hp: parseNum(form.power_hp),
  color: form.color || null,
}))
function usePrice(p: number) {
  form.price = num(p)
}
const STEP_OF: Record<string, number> = { plate: 1, mileage: 3, price: 3, history_url: 3, photographer_id: 4 }

async function save() {
  errors.value = {}
  stepError.value = ''
  saving.value = true
  saveStep.value = 'Enregistrement de la voiture…'
  let v: Vehicle
  try {
    v = await post<Vehicle>('/vehicles', payload())
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      let target = 4
      for (const [k, msgs] of Object.entries(e.errors)) {
        errors.value[k] = msgs[0]
        let s = STEP_OF[k] ?? 2
        if (k === 'plate' && byVin.value) s = 2
        target = Math.min(target, s)
      }
      if (target === 2) editing.value = true
      if (target === 3 && errors.value.history_url) showHistory.value = true
      // On reprend le message du serveur : certains champs n'ont pas d'emplacement visible à l'étape affichée.
      const msgs = [...new Set(Object.values(e.errors).map((m) => m[0]))]
      stepError.value = msgs.length ? msgs.join(' ') : 'Certaines informations sont à corriger.'
      step.value = target
    } else stepError.value = errorMessage(e)
    saving.value = false
    return
  }

  const lines: string[] = []
  if (mode.value === 'upload' && photos.value.length) {
    const total = photos.value.length
    saveStep.value = `Envoi des photos (0 sur ${total})…`
    const list = photos.value
    const sent = await uploadPhotos(v.id, list)
    const failed = total - sent
    const exterior = list.some((p) => p.state === 'done' && p.slot !== 'interior' && p.slot !== 'detail')
    lines.push(`${sent} photo${sent > 1 ? 's' : ''} envoyée${sent > 1 ? 's' : ''}.`)
    if (exterior) {
      saveStep.value = 'Préparation des photos showroom…'
      try {
        await post(`/vehicles/${v.id}/renders`)
        lines.push('Les photos showroom sont en préparation, elles arriveront dans « Vérifier les photos ».')
      } catch (e) {
        lines.push(`La préparation des photos showroom n'a pas démarré : ${errorMessage(e)}`)
      }
    }
    if (failed) lines.push(`${failed} photo${failed > 1 ? 's' : ''} n'${failed > 1 ? 'ont' : 'a'} pas pu être envoyée${failed > 1 ? 's' : ''}. Ajoutez-la depuis la fiche.`)
  } else if (mode.value === 'phone') {
    if (who.value && who.value.id !== session.user?.id) lines.push(`${who.value.name} a reçu la demande. Les photos apparaîtront dans « Vérifier les photos » une fois traitées.`)
    else lines.push('Elle attend ses photos. Prenez-les sur le parking avec votre téléphone.')
  } else {
    lines.push('Importez les photos depuis la fiche de la voiture, on les place dans votre showroom.')
  }
  doneText.value = lines.join(' ')
  created.value = v
  saving.value = false
  saveStep.value = ''
  step.value = 5
  stockChanged()
  refreshSidebar()
}

function restart() {
  Object.assign(form, blank())
  plate.value = ''
  lookup.value = 'idle'
  lookupMsg.value = ''
  byVin.value = false
  editing.value = false
  showHistory.value = false
  errors.value = {}
  stepError.value = ''
  mode.value = 'phone'
  photos.value = []
  created.value = null
  doneText.value = ''
  step.value = 1
}

function seeCar() {
  const id = created.value?.id
  emit('close')
  if (id) router.push(`/vehicles/${id}`)
}

function back() {
  errors.value = {}
  stepError.value = ''
  step.value = Math.max(1, step.value - 1)
}

function next() {
  if (busy.value) return
  if (step.value === 1) find()
  else if (step.value === 2) nextFromCar()
  else if (step.value === 3) nextFromPrice()
  else if (step.value === 4) save()
}

function close() {
  if (saving.value) return
  emit('close')
}

const nextLabel = computed(() => {
  if (step.value === 1) return 'Chercher la voiture'
  if (step.value === 4) return 'Ajouter la voiture'
  return 'Continuer'
})

const doneTitle = computed(() => {
  const name = created.value ? [created.value.make, created.value.model].filter(Boolean).join(' ') : ''
  return name ? `${name} est dans votre stock` : 'La voiture est dans votre stock'
})
</script>

<template>
  <AppModal title="Ajouter une voiture" :steps="STEPS" :step="step" @close="close">
    <form id="add-step" class="col" style="gap: 18px" novalidate @submit.prevent="next">
      <!-- 1. La plaque -->
      <template v-if="step === 1">
        <label class="lbl big-lbl">
          Quelle est la plaque ?
          <input
            :value="plate"
            class="f f-plate plate-in"
            :class="{ complete: isFullPlate(plate), odd: plateOdd }"
            @input="plate = onPlateInput($event)"
            autocomplete="off"
            autocapitalize="characters"
            spellcheck="false"
            placeholder="AB-123-CD"
            :aria-invalid="!!errors.plate"
            aria-describedby="plate-help"
            :disabled="looking"
          />
          <span v-if="errors.plate" class="err" role="alert">{{ errors.plate }}</span>
          <span v-else-if="plateOdd" class="odd-hint">Une plaque récente a la forme AB-123-CD : 2 lettres, 3 chiffres, 2 lettres.</span>
        </label>
        <span id="plate-help" class="help">
          On retrouve le modèle et la version. Vous pouvez aussi taper le numéro de série.
          <button type="button" class="btn-link inline" @click="manual">Saisir à la main</button>
        </span>
      </template>

      <!-- 2. La voiture -->
      <template v-else-if="step === 2">
        <div v-if="found" class="found">
          <span class="found-ico" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1E7A50" stroke-width="1.8"><path d="M5 16l1.5-5h11L19 16" /><rect x="3" y="16" width="18" height="4" rx="1" /><circle cx="7.5" cy="18" r=".5" /><circle cx="16.5" cy="18" r=".5" /></svg>
          </span>
          <div class="col" style="gap: 4px; min-width: 0">
            <span class="found-k">{{ lookupDemo ? 'Données d\'exemple (garage en démo)' : 'Voiture trouvée' }} · <span class="plate" style="font-size: 11px">{{ plate }}</span></span>
            <span class="found-t">{{ carName || 'Modèle inconnu' }} <span v-if="form.version" class="found-v">{{ form.version }}</span></span>
            <span v-if="facts" class="found-x">{{ facts }}</span>
          </div>
        </div>
        <p v-else class="alert alert-warn" style="margin: 0">{{ lookupMsg }}</p>

        <button v-if="found" type="button" class="btn-link fix" :aria-expanded="editing" aria-controls="car-fields" @click="editing = !editing">
          {{ editing ? 'Masquer les informations' : "Ce n'est pas ça ? Corriger les informations" }}
        </button>

        <div v-show="editing" id="car-fields" class="grid2">
          <label v-if="byVin || errors.plate" class="lbl">
            Plaque
            <input :value="plate" class="f f-plate" autocomplete="off" placeholder="AB-123-CD" @input="plate = onPlateInput($event)" :aria-invalid="!!errors.plate" />
            <span v-if="errors.plate" class="err">{{ errors.plate }}</span>
          </label>
          <label class="lbl">Marque<input v-model="form.make" class="f" :aria-invalid="!!errors.make" /><span v-if="errors.make" class="err">{{ errors.make }}</span></label>
          <label class="lbl">Modèle<input v-model="form.model" class="f" :aria-invalid="!!errors.model" /><span v-if="errors.model" class="err">{{ errors.model }}</span></label>
          <label class="lbl">Version<input v-model="form.version" class="f" placeholder="Ex. 1.0 TCe 90 Evolution" /></label>
          <label class="lbl">
            1re mise en circulation<input v-model="form.first_registration" class="f" type="month" :aria-invalid="!!errors.first_registration" />
            <span v-if="errors.first_registration" class="err">{{ errors.first_registration }}</span>
          </label>
          <label class="lbl">
            Carburant
            <select v-model="form.fuel" class="f" :aria-invalid="!!errors.fuel">
              <option value="">Choisir</option>
              <option v-for="o in FUELS" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </label>
          <label class="lbl">
            Boîte
            <select v-model="form.gearbox" class="f" :aria-invalid="!!errors.gearbox">
              <option value="">Choisir</option>
              <option v-for="o in GEARBOXES" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </label>
          <label class="lbl">Puissance (ch)<input v-model="form.power_hp" class="f" inputmode="numeric" :aria-invalid="!!errors.power_hp" /><span v-if="errors.power_hp" class="err">{{ errors.power_hp }}</span></label>
          <label class="lbl">Puissance fiscale (CV)<input v-model="form.fiscal_hp" class="f" inputmode="numeric" :aria-invalid="!!errors.fiscal_hp" /></label>
          <label class="lbl">Couleur<input v-model="form.color" class="f" /></label>
          <label class="lbl">Portes<input v-model="form.doors" class="f" inputmode="numeric" :aria-invalid="!!errors.doors" /><span v-if="errors.doors" class="err">{{ errors.doors }}</span></label>
          <label class="lbl">Places<input v-model="form.seats" class="f" inputmode="numeric" :aria-invalid="!!errors.seats" /><span v-if="errors.seats" class="err">{{ errors.seats }}</span></label>
          <label class="lbl">Numéro de série<input v-model="form.vin" class="f f-plate" autocomplete="off" :aria-invalid="!!errors.vin" /><span v-if="errors.vin" class="err">{{ errors.vin }}</span></label>
        </div>
      </template>

      <!-- 3. Prix et km -->
      <template v-else-if="step === 3">
        <div class="grid2">
          <label class="lbl big-lbl">
            Kilométrage
            <span class="unit-f" :class="{ bad: !!errors.mileage }">
              <input :value="form.mileage" inputmode="numeric" placeholder="Ex. 19 300" @input="form.mileage = onThousandsInput($event)" :aria-invalid="!!errors.mileage" />
              <span>km</span>
            </span>
            <span v-if="errors.mileage" class="err">{{ errors.mileage }}</span>
          </label>
          <label class="lbl big-lbl">
            Prix de vente
            <span class="unit-f" :class="{ bad: !!errors.price }">
              <input :value="form.price" inputmode="numeric" placeholder="Ex. 31 900" @input="form.price = onThousandsInput($event)" :aria-invalid="!!errors.price" />
              <span>€ TTC</span>
            </span>
            <span v-if="errors.price" class="err">{{ errors.price }}</span>
          </label>
        </div>
        <PriceHelper :car="priceCar" @use="usePrice" />
        <button v-if="!showHistory" type="button" class="btn-link fix" @click="showHistory = true">+ Ajouter le lien HistoVec (facultatif)</button>
        <label v-else class="lbl">
          Lien HistoVec envoyé par l'ancien propriétaire (facultatif)
          <input v-model="form.history_url" class="f" type="url" placeholder="Collez le lien ici" :aria-invalid="!!errors.history_url" />
          <span v-if="errors.history_url" class="err">{{ errors.history_url }}</span>
        </label>
      </template>

      <!-- 4. Les photos -->
      <template v-else-if="step === 4">
        <span class="q">Qui fait les photos ?</span>
        <div role="radiogroup" aria-label="Qui fait les photos" class="col" style="gap: 10px">
          <button type="button" role="radio" class="mode" :aria-checked="mode === 'phone'" :disabled="saving" @click="mode = 'phone'">
            <span class="mode-ico" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0A626A" stroke-width="1.8"><rect x="7" y="3" width="10" height="18" rx="2" /><path d="M11 18h2" /></svg>
            </span>
            <span class="col" style="gap: 2px">
              <span class="mode-t">Sur le parking, au téléphone</span>
              <span class="mode-x">{{ phoneText }}</span>
            </span>
          </button>
          <button type="button" role="radio" class="mode" :aria-checked="mode === 'upload'" :disabled="saving" @click="mode = 'upload'">
            <span class="mode-ico violet" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5B3FC4" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="9" cy="10" r="1.5" /><path d="M21 16l-5-5-8 8" /></svg>
            </span>
            <span class="col" style="gap: 2px">
              <span class="mode-t">J'ai déjà les photos</span>
              <span class="mode-x">Vous les importez depuis l'ordinateur.</span>
            </span>
          </button>
        </div>
        <label v-if="mode === 'phone' && isManager && users.length" class="lbl" style="max-width: 340px">
          Qui s'en occupe ?
          <select v-model="photographer" class="f" :disabled="saving">
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.id === session.user?.id ? `Moi (${u.name})` : u.name }}</option>
          </select>
        </label>
        <PhotoPicker v-if="mode === 'upload'" v-model="photos" :disabled="saving" />
      </template>

      <!-- Écran final -->
      <div v-else class="done">
        <span class="done-ico" aria-hidden="true">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1E7A50" stroke-width="2.5"><path d="M5 12l5 5 9-10" /></svg>
        </span>
        <span class="done-t" role="status">{{ doneTitle }}</span>
        <span v-if="created" class="plate">{{ created.plate }}</span>
        <span class="done-x">{{ doneText }}</span>
      </div>

      <p v-if="stepError && step < 5" class="alert alert-error" role="alert" style="margin: 0">{{ stepError }}</p>
    </form>

    <template #footer>
      <template v-if="step < 5">
        <button v-if="step > 1" type="button" class="btn btn-ghost btn-lg" :disabled="saving" @click="back">Retour</button>
        <span class="foot-note" role="status">
          <template v-if="saving">{{ mode === 'upload' && photos.length && sentCount ? `Envoi des photos (${sentCount} sur ${photos.length})…` : saveStep }}</template>
          <template v-else>Étape {{ step }} sur 4</template>
        </span>
        <button type="submit" form="add-step" class="btn btn-primary btn-lg" :disabled="busy">
          <span v-if="busy" class="spinner" aria-hidden="true" />
          {{ nextLabel }}
        </button>
      </template>
      <template v-else>
        <button type="button" class="btn btn-lg" style="margin-left: auto" @click="restart">Ajouter une autre</button>
        <button type="button" class="btn btn-primary btn-lg" @click="seeCar">Voir la voiture</button>
      </template>
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
.plate-in {
  font-size: 24px;
  letter-spacing: 0.08em;
  min-height: 62px;
  padding: 0 18px;
}
.help {
  font-size: 14px;
  color: var(--muted-3);
  line-height: 1.5;
}
.btn-link.inline {
  min-height: 0;
  padding: 0;
  font-size: 14px;
}
.btn-link.fix {
  align-self: flex-start;
  padding: 4px 0;
  min-height: 36px;
  font-size: 14px;
}
.found {
  display: flex;
  gap: 16px;
  align-items: center;
  background: #f1f9f4;
  border: 1px solid #d6eedf;
  border-radius: 16px;
  padding: 18px;
}
.found-ico {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: #dcf0e5;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.found-k {
  font-size: 12px;
  color: var(--green-ink);
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
}
.found-t {
  font-size: 22px;
  font-weight: 500;
  line-height: 1.25;
}
.found-v {
  color: var(--muted);
  font-weight: 400;
  font-size: 16px;
}
.found-x {
  font-size: 14px;
  color: var(--muted);
}
.grid2 {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 16px;
}
.unit-f {
  display: flex;
  align-items: center;
  border: 1px solid var(--field);
  background: var(--field-bg);
  border-radius: 12px;
  padding: 0 16px;
  height: 56px;
  gap: 8px;
  transition: border-color 0.12s ease, box-shadow 0.12s ease;
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
  font-size: 20px;
  font-weight: 500;
  border: 0;
  outline: 0;
  flex: 1;
  min-width: 0;
  color: var(--ink);
  background: transparent;
}
.unit-f input::placeholder {
  color: #b5bac5;
  font-weight: 400;
}
.unit-f span {
  color: var(--muted-3);
  font-size: 15px;
  white-space: nowrap;
}
.q {
  font-size: 14px;
  color: var(--muted);
}
.mode {
  text-align: left;
  border: 1px solid var(--line);
  background: #fff;
  border-radius: 14px;
  padding: 16px 18px;
  display: flex;
  align-items: center;
  gap: 14px;
  color: var(--ink);
}
.mode:hover:not(:disabled) {
  border-color: var(--accent-line);
}
.mode[aria-checked='true'] {
  border-color: var(--accent);
  background: var(--accent-tint);
}
.mode-ico {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--accent-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.mode-ico.violet {
  background: var(--violet-tint);
}
.mode-t {
  font-size: 15px;
  font-weight: 500;
}
.mode-x {
  font-size: 14px;
  color: var(--muted-2);
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
}
.done-x {
  font-size: 15px;
  color: var(--muted-2);
  max-width: 440px;
  line-height: 1.5;
}
.foot-note {
  margin-left: auto;
  font-size: 13px;
  color: var(--muted-3);
  text-align: right;
}
@media (max-width: 560px) {
  .grid2 {
    grid-template-columns: 1fr;
  }
  .found {
    padding: 14px;
  }
  .found-t {
    font-size: 19px;
  }
}
.plate-in.odd {
  border-color: #E5A04B;
}
.odd-hint {
  font-size: 13px;
  color: var(--amber-ink, #A55A0B);
}
.plate-in.complete {
  border-color: var(--green-ink, #1E7A50);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='22' height='22' viewBox='0 0 24 24' fill='none' stroke='%231E7A50' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12l5 5 9-10'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 18px center;
  transition: border-color 0.15s ease;
}
</style>
