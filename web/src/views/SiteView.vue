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
const preview = computed(() => online.value.slice(0, 6))
</script>

<template>
  <div class="page-head">
    <h1>Mon site</h1>
    <template v-if="garage">
      <span class="dom">{{ garage.domain || garage.site_url.replace(/^https?:\/\//, '') }}</span>
      <span class="pill" style="color: var(--green-ink); background: var(--green-tint)">En ligne · {{ online.length }} voiture{{ online.length > 1 ? 's' : '' }}</span>
    </template>
    <span v-else class="skel" style="display: inline-block; width: 260px; height: 16px" />
    <span class="grow" />
    <span v-if="dirty" class="unsaved">Modifications non enregistrées</span>
    <a v-if="garage" :href="garage.site_url" target="_blank" rel="noopener" class="btn">Voir mon site</a>
    <button v-if="garage" type="button" class="btn btn-primary" :disabled="saving || !dirty" @click="save"><span v-if="saving" class="spinner" aria-hidden="true" />Enregistrer</button>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

  <div v-if="garage" class="cols">
    <div class="left">
      <section class="panel look" aria-labelledby="look">
        <h2 id="look" class="h2">L'apparence</h2>
        <div class="row" style="gap: 12px; flex-wrap: nowrap">
          <div class="logo">
            <img v-if="garage.logo_url" :src="garage.logo_url" alt="Logo actuel" />
            <span v-else>Pas de logo</span>
          </div>
          <input ref="logoInput" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="uploadLogo" />
          <button type="button" class="btn btn-sm" :disabled="uploading" @click="logoInput?.click()">
            <span v-if="uploading" class="spinner" aria-hidden="true" />{{ garage.logo_url ? 'Changer le logo' : 'Ajouter le logo' }}
          </button>
        </div>
        <div role="radiogroup" aria-label="Couleur principale" class="col" style="gap: 8px">
          <span class="lbl">Couleur principale</span>
          <div class="row" style="gap: 8px">
            <button
              v-for="c in COLORS"
              :key="c.hex"
              type="button"
              role="radio"
              class="swatch"
              :aria-checked="form.brand_color.toLowerCase() === c.hex.toLowerCase()"
              :aria-label="c.name"
              :style="{ background: c.hex, '--sw': c.hex }"
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

      <section class="panel coord" aria-labelledby="coord">
        <h2 id="coord" class="h2">Vos coordonnées sur le site</h2>
        <div class="grid3">
          <label class="lbl">Nom du garage<input v-model="form.name" class="f" :aria-invalid="!!errors.name" /></label>
          <label class="lbl">Téléphone<input v-model="form.phone" class="f" type="tel" autocomplete="tel" :aria-invalid="!!errors.phone" /><span v-if="errors.phone" class="err">{{ errors.phone }}</span></label>
          <label class="lbl">E-mail qui reçoit les demandes<input v-model="form.email" class="f" type="email" :aria-invalid="!!errors.email" /><span v-if="errors.email" class="err">{{ errors.email }}</span></label>
          <label class="lbl" style="grid-column: span 2">Adresse<input v-model="form.address" class="f" /></label>
          <label class="lbl">Ville<input v-model="form.city" class="f" /></label>
        </div>
        <div class="grid2">
          <label class="lbl">Horaires<textarea v-model="form.hours" class="f" rows="4" placeholder="Lundi au vendredi : 9 h - 18 h" /></label>
          <label class="lbl">Présentation du garage<textarea v-model="form.about" class="f" rows="4" placeholder="Deux ou trois phrases sur votre garage, votre équipe, vos garanties." /></label>
        </div>
      </section>

      <section class="panel pub" aria-labelledby="pub">
        <h2 id="pub" class="h2" style="white-space: nowrap">Leboncoin et La Centrale</h2>
        <span class="muted" style="font-size: 13px; line-height: 1.5">Chaque annonce prête vous donne ses photos et un texte à copier. La mise en ligne automatique viendra plus tard.</span>
      </section>
    </div>

    <aside aria-label="Aperçu" class="right">
      <span class="muted" style="font-size: 13px; font-weight: 500">Aperçu, mis à jour pendant que vous tapez</span>
      <div class="pv">
        <div class="pv-bar" aria-hidden="true">
          <span /><span /><span />
          <span class="pv-url">{{ garage.domain || garage.site_url.replace(/^https?:\/\//, '') }}</span>
        </div>
        <div class="pv-head">
          <span class="row" style="gap: 10px; flex-wrap: nowrap; min-width: 0">
            <img v-if="garage.logo_url" :src="garage.logo_url" alt="" style="height: 26px; width: auto" />
            <span class="pv-name">{{ form.name || 'Votre garage' }}</span>
          </span>
          <span class="pv-call" :style="{ background: form.brand_color }">Appeler</span>
        </div>
        <div class="pv-body">
          <span style="font-size: 16px; font-weight: 600">Nos occasions</span>
          <div v-if="preview.length" class="pv-grid">
            <div v-for="v in preview" :key="v.id" class="pv-car">
              <img v-if="v.cover_url" :src="v.cover_url" alt="" />
              <div v-else class="pv-img" />
              <div class="col" style="padding: 8px 10px; gap: 2px">
                <span style="font-size: 12px; font-weight: 500">{{ vehicleTitle(v, false) }}</span>
                <span style="font-size: 12px; font-weight: 600" :style="{ color: form.brand_color }">{{ euros(v.price) }}</span>
              </div>
            </div>
          </div>
          <span v-else class="muted" style="font-size: 13px">Vos voitures en ligne apparaîtront ici.</span>
          <div v-if="form.phone || form.address" class="pv-foot">
            <span v-if="form.phone" style="font-weight: 500">{{ form.phone }}</span>
            <span v-if="form.address">{{ form.address }}{{ form.city ? `, ${form.city}` : '' }}</span>
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
.page-head {
  flex-wrap: nowrap;
  align-items: center;
}
.dom {
  font-size: 14px;
  color: var(--muted);
  margin-left: 8px;
}
.unsaved {
  font-size: 13px;
  font-weight: 500;
  color: var(--amber-ink);
  white-space: nowrap;
}
.h2 {
  font-size: 15px;
  font-weight: 500;
}
.cols {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 560px;
  gap: 18px;
  align-items: start;
}
.left {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
}
.panel {
  background: #fff;
  border: 1px solid #e6e7ea;
  border-radius: 16px;
  padding: 18px 22px;
}
.look {
  display: flex;
  align-items: center;
  gap: 28px;
  flex-wrap: wrap;
}
.look .h2 {
  width: 120px;
}
.coord {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.grid3 {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px 14px;
}
.grid2 {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.pub {
  display: flex;
  gap: 16px;
  align-items: center;
  padding-top: 14px;
  padding-bottom: 14px;
}
.logo {
  width: 96px;
  height: 52px;
  border: 1px dashed var(--field);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: var(--muted);
  overflow: hidden;
  padding: 4px;
  flex-shrink: 0;
}
.logo img {
  max-height: 100%;
  max-width: 100%;
  object-fit: contain;
}
.swatch {
  width: 32px;
  height: 32px;
  border-radius: 16px;
  border: 0;
}
.swatch[aria-checked='true'] {
  box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--sw);
}
.custom {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid var(--field-2);
  border-radius: 18px;
  padding: 0 12px 0 3px;
  height: 36px;
  font-size: 13px;
  cursor: pointer;
}
.custom.on {
  border: 2px solid var(--ink);
}
.custom input {
  width: 28px;
  height: 28px;
  border: 0;
  padding: 0;
  background: none;
  border-radius: 14px;
  cursor: pointer;
}
.right {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
}
.pv {
  background: #fff;
  border: 1px solid #e6e7ea;
  border-radius: 16px;
  overflow: hidden;
}
.pv-bar {
  height: 30px;
  background: #f0f1f3;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 12px;
}
.pv-bar > span:not(.pv-url) {
  width: 8px;
  height: 8px;
  border-radius: 4px;
  background: #d3d5d9;
}
.pv-url {
  margin-left: 10px;
  font-size: 11px;
  color: var(--muted-3);
  background: #fff;
  border-radius: 6px;
  padding: 2px 10px;
}
.pv-head {
  padding: 14px 18px;
  border-bottom: 1px solid var(--line-2);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.pv-name {
  font-weight: 600;
  font-size: 14px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pv-call {
  font-size: 12px;
  font-weight: 500;
  color: #fff;
  border-radius: 999px;
  padding: 6px 12px;
}
.pv-body {
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.pv-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.pv-car {
  border: 1px solid var(--line-2);
  border-radius: 10px;
  overflow: hidden;
}
.pv-car img,
.pv-img {
  width: 100%;
  aspect-ratio: 4/3;
  object-fit: cover;
  background: var(--accent-tint);
  display: block;
}
.pv-foot {
  border-top: 1px solid var(--line-2);
  padding-top: 10px;
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  font-size: 12px;
  color: var(--ink-2);
}
@media (max-width: 1280px) {
  .cols {
    grid-template-columns: minmax(0, 1fr) 420px;
  }
  .pv-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .grid3 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 960px) {
  .page-head {
    flex-wrap: wrap;
  }
  .cols,
  .grid2,
  .grid3 {
    grid-template-columns: minmax(0, 1fr);
  }
  .grid3 .lbl {
    grid-column: auto !important;
  }
  .pub {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
