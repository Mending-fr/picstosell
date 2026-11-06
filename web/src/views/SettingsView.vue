<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { del, get, patch, errorMessage } from '../api'
import { garageStatusLabel, monthName } from '../labels'
import { session, toast } from '../session'
import type { Garage, GarageSettings, Invitation, Usage, User } from '../types'
import { ROLE_LABEL } from '../team'
import InviteModal from '../components/InviteModal.vue'
import PendingInvitations from '../components/PendingInvitations.vue'
import MoreMenu, { type MenuItem } from '../components/MoreMenu.vue'

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
const invitations = ref<Invitation[]>([])
const teamError = ref('')
const inviteOpen = ref(false)

async function loadUsers() {
  teamError.value = ''
  try {
    const r = await get<{ data: User[]; invitations: Invitation[] }>('/users')
    users.value = r.data
    invitations.value = r.invitations
  } catch (e) {
    teamError.value = errorMessage(e)
  }
}
function onInvited(inv: Invitation) {
  invitations.value = [inv, ...invitations.value.filter((x) => x.id !== inv.id && (!inv.email || x.email !== inv.email))]
}

async function setRole(u: User, role: 'manager' | 'photographer') {
  try {
    const n = await patch<User>(`/users/${u.id}`, { role })
    users.value = (users.value ?? []).map((x) => (x.id === u.id ? { ...x, ...n } : x))
    toast(`${u.name} est maintenant ${ROLE_LABEL[role].toLowerCase()}.`)
  } catch (e) {
    toast(errorMessage(e), 'error')
  }
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

function userMenu(u: User): MenuItem[] {
  return [
    u.role === 'manager'
      ? { label: 'Passer photographe', action: () => setRole(u, 'photographer') }
      : { label: 'Passer gérant', action: () => setRole(u, 'manager') },
    { label: "Retirer l'accès", action: () => removeUser(u), danger: true },
  ]
}

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
  <template v-if="tab === 'team'">
    <p v-if="teamError" class="alert alert-error" role="alert">{{ teamError }} <button type="button" class="btn-link" @click="loadUsers">Réessayer</button></p>
    <section class="col" style="gap: 10px" aria-labelledby="mb-t">
      <div class="row between">
        <h2 id="mb-t">Membres <span v-if="users" class="muted" style="font-weight: 450">{{ users.length }}</span></h2>
        <button type="button" class="btn btn-primary" @click="inviteOpen = true">Inviter</button>
      </div>
      <div class="table-wrap">
        <table class="t">
          <thead>
            <tr><th>Nom</th><th>Rôle</th><th class="hide-s">Dernière connexion</th><th><span class="sr-only">Actions</span></th></tr>
          </thead>
          <tbody>
            <template v-if="!users && !teamError">
              <tr v-for="i in 2" :key="i"><td colspan="4"><span class="skel" style="display: block; width: 220px; height: 14px" /></td></tr>
            </template>
            <tr v-for="u in users ?? []" :key="u.id">
              <td>
                <span class="col" style="gap: 1px; min-width: 0">
                  <span style="font-weight: 500">{{ u.name }}{{ u.id === session.user?.id ? ' (vous)' : '' }}</span>
                  <span class="muted" style="font-size: 12px">{{ u.email }}</span>
                </span>
              </td>
              <td>{{ ROLE_LABEL[u.role] }}</td>
              <td class="soft hide-s">{{ u.invited ? 'Jamais connecté' : 'Déjà connecté' }}</td>
              <td style="text-align: right">
                <MoreMenu v-if="u.id !== session.user?.id" :items="userMenu(u)" label="Gérer" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="invitations.length" class="col" style="gap: 10px" aria-labelledby="iv-t">
      <h2 id="iv-t">Invitations en attente</h2>
      <PendingInvitations :invitations="invitations" base="/invitations" @update="(l) => (invitations = l)" />
    </section>

    <InviteModal v-if="inviteOpen" @close="inviteOpen = false" @created="onInvited" />
  </template>

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
