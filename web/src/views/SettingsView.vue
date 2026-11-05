<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, del, get, patch, post, errorMessage } from '../api'
import { copyText, garageStatusLabel, monthName } from '../labels'
import { session, toast } from '../session'
import type { Garage, GarageSettings, Usage, User } from '../types'

const route = useRoute()
const router = useRouter()
type Tab = 'team' | 'photos' | 'plan'
const TABS: { id: Tab; label: string }[] = [
  { id: 'team', label: 'Équipe' },
  { id: 'photos', label: 'Photos' },
  { id: 'plan', label: 'Abonnement et factures' },
]
const tab = ref<Tab>((TABS.find((t) => t.id === route.query.onglet)?.id ?? 'team') as Tab)
function setTab(t: Tab) {
  tab.value = t
  router.replace({ query: { onglet: t } })
  if (t === 'plan' && !usage.value) loadUsage()
  if (t === 'photos' && !garage.value) loadGarage()
}

// ---------- équipe ----------
const users = ref<User[] | null>(null)
const teamError = ref('')
const invite = reactive({ name: '', email: '', role: 'photographer' as 'photographer' | 'manager' })
const inviteErrors = ref<Record<string, string>>({})
const inviting = ref(false)
const created = ref<{ user: User; temporary_password: string } | null>(null)
const copied = ref(false)

async function loadUsers() {
  teamError.value = ''
  try {
    users.value = (await get<{ data: User[] }>('/users')).data
  } catch (e) {
    teamError.value = errorMessage(e)
  }
}

async function sendInvite() {
  inviteErrors.value = {}
  if (!invite.name.trim()) inviteErrors.value.name = 'Indiquez le prénom et le nom.'
  if (!/^\S+@\S+\.\S+$/.test(invite.email.trim())) inviteErrors.value.email = 'Indiquez une adresse e-mail valide.'
  if (Object.keys(inviteErrors.value).length) return
  inviting.value = true
  try {
    const r = await post<{ user: User; temporary_password: string }>('/users', { name: invite.name.trim(), email: invite.email.trim(), role: invite.role })
    created.value = r
    copied.value = false
    users.value = [...(users.value ?? []), { ...r.user, invited: true }].sort((a, b) => a.name.localeCompare(b.name))
    invite.name = ''
    invite.email = ''
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) for (const [k, m] of Object.entries(e.errors)) inviteErrors.value[k] = m[0]
    else toast(errorMessage(e), 'error')
  } finally {
    inviting.value = false
  }
}

async function copyPassword() {
  if (!created.value) return
  const text = `Adresse : ${location.origin}/app/\nE-mail : ${created.value.user.email}\nMot de passe provisoire : ${created.value.temporary_password}`
  copied.value = await copyText(text)
  if (!copied.value) toast('La copie a échoué, notez le mot de passe à la main.', 'error')
}

async function removeUser(u: User) {
  if (!window.confirm(`Retirer l'accès de ${u.name} ? Cette personne ne pourra plus se connecter.`)) return
  const before = users.value
  users.value = (users.value ?? []).filter((x) => x.id !== u.id)
  try {
    await del(`/users/${u.id}`)
    toast(`${u.name} n'a plus accès.`)
  } catch (e) {
    users.value = before
    toast(errorMessage(e), 'error')
  }
}

const roleLabel = (r: string) => (r === 'manager' ? 'Gérant : tout faire' : r === 'photographer' ? 'Photos seulement' : 'Équipe Mending')

// ---------- photos ----------
const garage = ref<Garage | null>(null)
async function loadGarage() {
  try {
    garage.value = await get<Garage>('/garage')
  } catch (e) {
    toast(errorMessage(e), 'error')
  }
}
async function setSetting<K extends keyof GarageSettings>(key: K, value: GarageSettings[K]) {
  if (!garage.value) return
  const before = garage.value.settings[key]
  garage.value.settings[key] = value
  try {
    const g = await patch<Garage>('/garage', { settings: { [key]: value } })
    garage.value = g
    session.garage = g
    toast('Réglage enregistré.')
  } catch (e) {
    garage.value.settings[key] = before
    toast(errorMessage(e), 'error')
  }
}

// ---------- abonnement ----------
const usage = ref<Usage | null>(null)
const usageError = ref('')
async function loadUsage() {
  usageError.value = ''
  try {
    usage.value = await get<Usage>('/usage')
  } catch (e) {
    usageError.value = errorMessage(e)
  }
}
const usagePct = computed(() => (usage.value && usage.value.quota ? Math.min(100, Math.round((usage.value.vehicles / usage.value.quota) * 100)) : 0))

onMounted(() => {
  loadUsers()
  if (tab.value === 'photos') loadGarage()
  if (tab.value === 'plan') loadUsage()
})
</script>

<template>
  <h1>Réglages</h1>

  <div role="tablist" aria-label="Rubriques" class="tabs">
    <button v-for="t in TABS" :key="t.id" type="button" role="tab" class="tab" :aria-selected="tab === t.id" @click="setTab(t.id)">{{ t.label }}</button>
  </div>

  <!-- Équipe -->
  <section v-if="tab === 'team'" class="card" aria-label="Équipe">
    <p v-if="teamError" class="alert alert-error" role="alert">{{ teamError }}</p>
    <div class="col">
      <template v-if="!users && !teamError">
        <div v-for="i in 3" :key="i" class="urow"><span class="skel" style="width: 180px; height: 18px" /></div>
      </template>
      <div v-for="u in users ?? []" :key="u.id" class="urow">
        <div class="col" style="gap: 2px; min-width: 0">
          <span style="font-weight: 500">{{ u.name }}{{ u.id === session.user?.id ? ' (vous)' : '' }}</span>
          <span class="muted" style="font-size: 13px; word-break: break-all">{{ u.invited ? `${u.email} · pas encore connecté` : u.email }}</span>
        </div>
        <div class="row">
          <span class="pill" :style="u.role === 'manager' ? { background: '#E0F0FF', color: '#00527C' } : { background: '#F1F3F6', color: '#3B4150' }">{{ roleLabel(u.role) }}</span>
          <button v-if="u.id !== session.user?.id" type="button" class="btn-link" style="color: var(--red-ink)" :aria-label="`Retirer l'accès de ${u.name}`" @click="removeUser(u)">Retirer</button>
        </div>
      </div>
    </div>

    <div v-if="created" class="alert alert-ok col" style="gap: 10px; font-weight: 400" role="status">
      <strong>{{ created.user.name }} peut se connecter.</strong>
      <span>Donnez-lui ce mot de passe provisoire. Il ne sera plus affiché ensuite.</span>
      <div class="row">
        <code class="pw">{{ created.temporary_password }}</code>
        <button type="button" class="btn btn-sm" @click="copyPassword">{{ copied ? 'Copié' : 'Copier les accès' }}</button>
        <button type="button" class="btn-link" @click="created = null">J'ai noté, masquer</button>
      </div>
    </div>

    <form class="invite" novalidate @submit.prevent="sendInvite">
      <label class="lbl">
        Prénom et nom
        <input v-model="invite.name" class="f" autocomplete="off" :aria-invalid="!!inviteErrors.name" />
        <span v-if="inviteErrors.name" class="err">{{ inviteErrors.name }}</span>
      </label>
      <label class="lbl">
        Inviter quelqu'un (e-mail)
        <input v-model="invite.email" class="f" type="email" placeholder="prenom@garage.fr" autocomplete="off" :aria-invalid="!!inviteErrors.email" />
        <span v-if="inviteErrors.email" class="err">{{ inviteErrors.email }}</span>
      </label>
      <label class="lbl">
        Ce qu'il peut faire
        <select v-model="invite.role" class="f">
          <option value="photographer">Photos seulement</option>
          <option value="manager">Gérant : tout faire</option>
        </select>
      </label>
      <button type="submit" class="btn btn-primary" :disabled="inviting"><span v-if="inviting" class="spinner" aria-hidden="true" />Créer l'accès</button>
    </form>
  </section>

  <!-- Photos -->
  <section v-if="tab === 'photos'" class="card" aria-label="Photos">
    <template v-if="garage">
      <label class="check">
        <input type="checkbox" :checked="garage.settings.hide_plate" @change="setSetting('hide_plate', ($event.target as HTMLInputElement).checked)" />
        <span class="col" style="gap: 2px">
          <span style="font-weight: 500">Cacher la plaque sur les photos mises en ligne</span>
          <span class="muted" style="font-size: 13px">La plaque est toujours cachée avant le traitement des photos, même si cette case est décochée.</span>
        </span>
      </label>
      <label class="check">
        <input type="checkbox" :checked="garage.settings.add_logo" @change="setSetting('add_logo', ($event.target as HTMLInputElement).checked)" />
        <span class="col" style="gap: 2px">
          <span style="font-weight: 500">Ajouter le logo du garage sur les photos</span>
          <span class="muted" style="font-size: 13px">En bas à droite, discret.</span>
        </span>
      </label>
      <label class="check">
        <input type="checkbox" :checked="garage.settings.review_before_publish" @change="setSetting('review_before_publish', ($event.target as HTMLInputElement).checked)" />
        <span class="col" style="gap: 2px">
          <span style="font-weight: 500">Vérifier chaque photo avant la mise en ligne</span>
          <span class="muted" style="font-size: 13px">Recommandé tant que vous découvrez l'outil.</span>
        </span>
      </label>
      <label class="lbl" style="max-width: 360px">
        Taille des photos téléchargées
        <select class="f" :value="garage.settings.export_size" @change="setSetting('export_size', ($event.target as HTMLSelectElement).value)">
          <option value="marketplace">Leboncoin et La Centrale (recommandé)</option>
          <option value="large">Grande taille</option>
        </select>
      </label>
    </template>
    <template v-else>
      <div v-for="i in 3" :key="i" class="skel" style="height: 52px" />
    </template>
  </section>

  <!-- Abonnement -->
  <section v-if="tab === 'plan'" class="card" aria-label="Abonnement">
    <p v-if="usageError" class="alert alert-error" role="alert">{{ usageError }} <button type="button" class="btn-link" @click="loadUsage">Réessayer</button></p>
    <template v-if="usage">
      <div class="row between" style="align-items: flex-start">
        <div class="col" style="gap: 4px">
          <span class="muted" style="font-size: 13px">Votre abonnement</span>
          <span style="font-size: 24px; font-weight: 500">{{ usage.quota }} voitures par mois</span>
          <span style="font-size: 14px; color: var(--ink-2)">Photos, site, hébergement et assistance compris</span>
        </div>
        <span class="pill" style="background: #E9F5EE; color: #1E7A50">{{ session.garage ? garageStatusLabel(session.garage.status) : 'Actif' }}</span>
      </div>
      <div class="col" style="gap: 8px">
        <div class="row between" style="font-size: 15px">
          <span>Voitures en {{ monthName() }}</span><span style="font-weight: 500">{{ usage.vehicles }} sur {{ usage.quota }}</span>
        </div>
        <div class="bar" role="progressbar" :aria-valuenow="usagePct" aria-valuemin="0" aria-valuemax="100" aria-label="Voitures utilisées ce mois">
          <div :style="{ width: usagePct + '%', background: usagePct >= 100 ? '#A55A0B' : '#005BD3' }" />
        </div>
        <span class="muted" style="font-size: 13px">{{ usage.renders }} photo{{ usage.renders > 1 ? 's' : '' }} de showroom préparée{{ usage.renders > 1 ? 's' : '' }} ce mois-ci.</span>
      </div>
      <div class="col">
        <span class="muted" style="font-size: 13px; font-weight: 500; padding-bottom: 6px">En plus ce mois-ci</span>
        <div v-for="(x, i) in usage.extras" :key="i" class="urow">
          <span>{{ x.label ?? 'Option' }}{{ x.quantity ? ` : ${x.quantity}` : '' }}</span>
          <span v-if="x.amount" style="font-weight: 500">{{ x.amount }} € HT</span>
        </div>
        <span v-if="!usage.extras.length" class="muted" style="font-size: 14px">Rien en plus ce mois-ci.</span>
      </div>
      <div class="col">
        <span class="muted" style="font-size: 13px; font-weight: 500; padding-bottom: 6px">Factures</span>
        <span class="muted" style="font-size: 14px">Vos factures apparaîtront ici. En attendant, l'équipe Mending vous les envoie par e-mail.</span>
      </div>
    </template>
    <template v-else-if="!usageError">
      <div v-for="i in 3" :key="i" class="skel" style="height: 40px" />
    </template>
  </section>
</template>

<style scoped>
.urow {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 14px 0;
  border-bottom: 1px solid var(--line-2);
  flex-wrap: wrap;
}
.invite {
  background: #f7f7f7;
  border-radius: 10px;
  padding: 12px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
  gap: 12px;
  align-items: end;
}
@media (max-width: 900px) {
  .invite {
    grid-template-columns: minmax(0, 1fr);
  }
}
.pw {
  font-family: var(--mono);
  font-size: 16px;
  font-weight: 500;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 8px 12px;
  color: var(--ink);
  user-select: all;
}
.bar {
  height: 8px;
  background: #F1F3F6;
  border-radius: 4px;
}
.bar div {
  height: 8px;
  border-radius: 4px;
}
</style>
