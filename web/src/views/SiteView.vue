<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { ApiError, get, patch, post, errorMessage } from '../api'
import { euros, vehicleTitle } from '../labels'
import { session, toast } from '../session'
import type { Garage, Vehicle } from '../types'

const garage = ref<Garage | null>(null)
const online = ref<Vehicle[]>([])
const error = ref('')
const form = reactive({ name: '', phone: '', email: '', address: '', hours: '', about: '', brand_color: '#B8222A', city: '' })
const saved = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const logoInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)

const COLORS = [
  { name: 'Rouge', hex: '#B8222A' },
  { name: 'Jaune foncé', hex: '#A16207' },
  { name: 'Bleu', hex: '#1F4FD8' },
  { name: 'Vert', hex: '#1E7A50' },
  { name: 'Noir', hex: '#15171C' },
]

function fill(g: Garage) {
  garage.value = g
  form.name = g.name ?? ''
  form.city = g.city ?? ''
  form.phone = g.phone ?? ''
  form.email = g.email ?? ''
  form.address = g.address ?? ''
  form.hours = g.hours ?? ''
  form.about = g.about ?? ''
  form.brand_color = g.brand_color || '#B8222A'
  saved.value = JSON.stringify(form)
}
const dirty = computed(() => saved.value !== '' && JSON.stringify(form) !== saved.value)
const custom = computed(() => !COLORS.some((c) => c.hex.toLowerCase() === form.brand_color.toLowerCase()))

async function load() {
  error.value = ''
  try {
    const [g, v] = await Promise.all([get<Garage>('/garage'), get<{ data: Vehicle[] }>('/vehicles?status=online')])
    fill(g)
    online.value = v.data
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(load)

async function save() {
  errors.value = {}
  if (!/^#[0-9a-f]{6}$/i.test(form.brand_color)) {
    errors.value.brand_color = 'Couleur au format #RRGGBB, par exemple #B8222A.'
    return
  }
  saving.value = true
  try {
    const g = await patch<Garage>('/garage', {
      name: form.name.trim() || undefined,
      city: form.city.trim() || null,
      phone: form.phone.trim() || null,
      email: form.email.trim() || null,
      address: form.address.trim() || null,
      hours: form.hours.trim() || null,
      about: form.about.trim() || null,
      brand_color: form.brand_color.toUpperCase(),
    })
    fill(g)
    session.garage = g
    toast('Votre site est à jour.')
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      for (const [k, m] of Object.entries(e.errors)) errors.value[k] = m[0]
      toast('Certaines informations sont à corriger.', 'error')
    } else toast(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function uploadLogo(e: Event) {
  const t = e.target as HTMLInputElement
  const f = t.files?.[0]
  t.value = ''
  if (!f) return
  uploading.value = true
  const fd = new FormData()
  fd.append('file', f)
  try {
    const g = await post<Garage>('/garage/logo', fd)
    if (garage.value) garage.value.logo_url = g.logo_url
    session.garage = g
    toast('Logo enregistré.')
  } catch (err) {
    toast(errorMessage(err), 'error')
  } finally {
    uploading.value = false
  }
}

onBeforeRouteLeave(() => !dirty.value || window.confirm("Vos modifications ne sont pas enregistrées. Quitter quand même ?"))
const preview = computed(() => online.value.slice(0, 4))
</script>

<template>
  <div class="page-head">
    <div class="col" style="gap: 4px">
      <h1>Mon site</h1>
      <p class="lead" style="margin: 0">
        <template v-if="garage">{{ garage.domain || garage.site_url.replace(/^https?:\/\//, '') }} · en ligne, {{ online.length }} voiture{{ online.length > 1 ? 's' : '' }} affichée{{ online.length > 1 ? 's' : '' }}</template>
        <span v-else class="skel" style="display: inline-block; width: 260px; height: 16px" />
      </p>
    </div>
    <a v-if="garage" :href="garage.site_url" target="_blank" rel="noopener" class="btn btn-primary">Voir mon site</a>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

  <div v-if="garage" class="split">
    <div class="col" style="flex: 999 1 480px; min-width: 0; gap: 20px">
      <section class="card" aria-labelledby="look">
        <h2 id="look" class="h2">L'apparence</h2>
        <div class="row" style="gap: 16px">
          <div class="logo">
            <img v-if="garage.logo_url" :src="garage.logo_url" alt="Logo actuel" />
            <span v-else>Pas de logo</span>
          </div>
          <input ref="logoInput" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="uploadLogo" />
          <button type="button" class="btn" :disabled="uploading" @click="logoInput?.click()">
            <span v-if="uploading" class="spinner" aria-hidden="true" />{{ garage.logo_url ? 'Changer le logo' : 'Ajouter le logo' }}
          </button>
        </div>
        <div role="radiogroup" aria-label="Couleur principale" class="col" style="gap: 8px">
          <span class="lbl">Couleur principale</span>
          <div class="row">
            <button
              v-for="c in COLORS"
              :key="c.hex"
              type="button"
              role="radio"
              class="swatch"
              :aria-checked="form.brand_color.toLowerCase() === c.hex.toLowerCase()"
              :aria-label="c.name"
              :style="{ background: c.hex }"
              @click="form.brand_color = c.hex"
            />
            <label class="custom" :class="{ on: custom }">
              <input v-model="form.brand_color" type="color" aria-label="Autre couleur" />
              <span>Autre</span>
            </label>
          </div>
          <span v-if="errors.brand_color" class="err">{{ errors.brand_color }}</span>
        </div>
      </section>

      <section class="card" aria-labelledby="coord">
        <h2 id="coord" class="h2">Vos coordonnées sur le site</h2>
        <div class="grid-fields">
          <label class="lbl">Nom du garage<input v-model="form.name" class="f" :aria-invalid="!!errors.name" /></label>
          <label class="lbl">Ville<input v-model="form.city" class="f" /></label>
          <label class="lbl">Téléphone<input v-model="form.phone" class="f" type="tel" autocomplete="tel" :aria-invalid="!!errors.phone" /><span v-if="errors.phone" class="err">{{ errors.phone }}</span></label>
          <label class="lbl">E-mail qui reçoit les demandes<input v-model="form.email" class="f" type="email" :aria-invalid="!!errors.email" /><span v-if="errors.email" class="err">{{ errors.email }}</span></label>
          <label class="lbl" style="grid-column: 1 / -1">Adresse<input v-model="form.address" class="f" /></label>
        </div>
        <label class="lbl">Horaires<textarea v-model="form.hours" class="f" rows="3" placeholder="Lundi au vendredi : 9 h - 18 h" /></label>
        <label class="lbl">Présentation du garage<textarea v-model="form.about" class="f" rows="3" placeholder="Deux ou trois phrases sur votre garage, votre équipe, vos garanties." /></label>
      </section>

      <section class="card" aria-labelledby="pub">
        <h2 id="pub" class="h2">Leboncoin et La Centrale</h2>
        <p style="margin: 0; font-size: 15px; color: var(--ink-2); line-height: 1.5">
          Pour l'instant, chaque annonce prête vous donne ses photos et un texte à copier. La mise en ligne automatique viendra plus tard.
        </p>
      </section>

      <div class="row" style="justify-content: flex-end">
        <span v-if="dirty" class="muted" style="font-size: 13px">Modifications non enregistrées</span>
        <button type="button" class="btn btn-primary btn-lg" :disabled="saving || !dirty" @click="save"><span v-if="saving" class="spinner" aria-hidden="true" />Enregistrer</button>
      </div>
    </div>

    <aside aria-label="Aperçu" class="col" style="flex: 1 1 300px; min-width: 0; gap: 8px; position: sticky; top: 16px">
      <span class="muted" style="font-size: 13px; font-weight: 500">Aperçu</span>
      <div class="pv">
        <div class="pv-head">
          <span class="row" style="gap: 8px; flex-wrap: nowrap; min-width: 0">
            <img v-if="garage.logo_url" :src="garage.logo_url" alt="" style="height: 22px; width: auto" />
            <span class="pv-name" :style="{ color: form.brand_color }">{{ form.name || 'Votre garage' }}</span>
          </span>
          <span class="pv-call" :style="{ background: form.brand_color }">Appeler</span>
        </div>
        <div class="pv-body">
          <span style="font-size: 17px; font-weight: 500">Nos occasions</span>
          <div v-if="preview.length" class="pv-grid">
            <div v-for="v in preview" :key="v.id" class="pv-car">
              <img v-if="v.cover_url" :src="v.cover_url" alt="" />
              <div v-else class="pv-img" />
              <div class="col" style="padding: 6px 8px; gap: 2px">
                <span style="font-size: 11px; font-weight: 500">{{ vehicleTitle(v, false) }}</span>
                <span style="font-size: 12px; font-weight: 500" :style="{ color: form.brand_color }">{{ euros(v.price) }}</span>
              </div>
            </div>
          </div>
          <span v-else class="muted" style="font-size: 13px">Vos voitures en ligne apparaîtront ici.</span>
          <div v-if="form.phone || form.address || form.hours" class="pv-foot">
            <span v-if="form.phone" style="font-weight: 500">{{ form.phone }}</span>
            <span v-if="form.address">{{ form.address }}{{ form.city ? `, ${form.city}` : '' }}</span>
            <span v-if="form.hours" style="white-space: pre-line">{{ form.hours }}</span>
          </div>
        </div>
      </div>
    </aside>
  </div>
  <div v-else-if="!error" class="col" style="gap: 16px">
    <div class="skel" style="height: 160px; border-radius: 14px" />
    <div class="skel" style="height: 260px; border-radius: 14px" />
  </div>
</template>

<style scoped>
.h2 {
  font-size: 18px;
}
.split {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  align-items: flex-start;
}
.logo {
  width: 120px;
  height: 60px;
  border: 1px dashed var(--field);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: var(--muted);
  overflow: hidden;
  padding: 4px;
}
.logo img {
  max-height: 100%;
  object-fit: contain;
}
.swatch {
  width: 44px;
  height: 44px;
  border-radius: 22px;
  border: 3px solid transparent;
  box-shadow: 0 0 0 2px #fff inset;
}
.swatch[aria-checked='true'] {
  border-color: var(--ink);
}
.custom {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid var(--field-2);
  border-radius: 22px;
  padding: 0 12px 0 4px;
  height: 44px;
  font-size: 14px;
  cursor: pointer;
}
.custom.on {
  border: 3px solid var(--ink);
}
.custom input {
  width: 34px;
  height: 34px;
  border: 0;
  padding: 0;
  background: none;
  border-radius: 17px;
  cursor: pointer;
}
.pv {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  overflow: hidden;
}
.pv-head {
  padding: 12px 14px;
  border-bottom: 1px solid var(--line-2);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.pv-name {
  font-weight: 500;
  font-size: 14px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pv-call {
  font-size: 11px;
  font-weight: 500;
  color: #fff;
  border-radius: 6px;
  padding: 5px 8px;
}
.pv-body {
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.pv-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.pv-car {
  border: 1px solid var(--line-2);
  border-radius: 8px;
  overflow: hidden;
}
.pv-car img,
.pv-img {
  width: 100%;
  aspect-ratio: 4/3;
  object-fit: cover;
  background: #EEF3F9;
}
.pv-foot {
  border-top: 1px solid var(--line-2);
  padding-top: 10px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 12px;
  color: var(--ink-2);
}
</style>
