<script setup lang="ts">
// Espace Mending : tous les comptes (équipe Mending et garages), les invitations, et le changement de rôle.
import { computed, onMounted, reactive, ref } from 'vue'
import { ApiError, del, get, patch, errorMessage } from '../../api'
import { session, toast } from '../../session'
import { ROLE_LABEL, since } from '../../team'
import AppModal from '../../components/AppModal.vue'
import InviteModal from '../../components/InviteModal.vue'
import MoreMenu, { type MenuItem } from '../../components/MoreMenu.vue'
import PendingInvitations from '../../components/PendingInvitations.vue'
import type { Invitation, Role, User } from '../../types'

const users = ref<User[] | null>(null)
const invitations = ref<Invitation[]>([])
const error = ref('')
const inviteOpen = ref(false)

async function load() {
  error.value = ''
  try {
    const r = await get<{ data: User[]; invitations: Invitation[] }>('/admin/users')
    users.value = r.data
    invitations.value = r.invitations
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(load)

type Filter = 'all' | 'mending' | 'garages'
const filter = ref<Filter>('all')
const shown = computed(() =>
  (users.value ?? []).filter((u) => filter.value === 'all' || (filter.value === 'mending' ? u.role === 'super_admin' : u.role !== 'super_admin')),
)
const count = (f: Filter) => (users.value ?? []).filter((u) => f === 'all' || (f === 'mending' ? u.role === 'super_admin' : u.role !== 'super_admin')).length

function onInvited(inv: Invitation) {
  invitations.value = [inv, ...invitations.value.filter((x) => x.id !== inv.id && (!inv.email || x.email !== inv.email))]
}

// ---------- changer le rôle ----------
const editing = ref<User | null>(null)
const form = reactive({ role: 'manager' as Role, garage_id: null as number | null })
const formErrors = ref<Record<string, string>>({})
const saving = ref(false)
function edit(u: User) {
  editing.value = u
  form.role = u.role
  form.garage_id = u.garage_id ?? session.garages[0]?.id ?? null
  formErrors.value = {}
}
async function saveRole() {
  const u = editing.value
  if (!u) return
  saving.value = true
  formErrors.value = {}
  try {
    const n = await patch<User>(`/admin/users/${u.id}`, { role: form.role, garage_id: form.role === 'super_admin' ? null : form.garage_id })
    users.value = (users.value ?? []).map((x) => (x.id === u.id ? { ...x, ...n } : x))
    toast(`${u.name} : rôle enregistré.`)
    editing.value = null
  } catch (e) {
    if (e instanceof ApiError && e.status === 422 && Object.keys(e.errors).length) for (const [k, m] of Object.entries(e.errors)) formErrors.value[k] = m[0]
    else toast(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function remove(u: User) {
  if (!window.confirm(`Retirer l'accès de ${u.name} ? Cette personne ne pourra plus se connecter.`)) return
  try {
    await del(`/admin/users/${u.id}`)
    users.value = (users.value ?? []).filter((x) => x.id !== u.id)
    toast(`${u.name} n'a plus accès.`)
  } catch (e) {
    toast(errorMessage(e), 'error')
  }
}

function menu(u: User): MenuItem[] {
  return [
    { label: 'Changer le rôle', action: () => edit(u) },
    { label: "Retirer l'accès", action: () => remove(u), danger: true },
  ]
}
</script>

<template>
  <div class="page">
    <div class="page-head">
      <h1>Utilisateurs</h1>
      <button type="button" class="btn btn-primary" @click="inviteOpen = true">Inviter</button>
    </div>

    <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

    <div class="table-wrap">
      <div role="tablist" aria-label="Filtrer" class="tabs tbar">
        <button type="button" role="tab" class="tab" :aria-selected="filter === 'all'" @click="filter = 'all'">Tous<span class="n">{{ users ? count('all') : '' }}</span></button>
        <button type="button" role="tab" class="tab" :aria-selected="filter === 'mending'" @click="filter = 'mending'">Équipe Mending<span class="n">{{ users ? count('mending') : '' }}</span></button>
        <button type="button" role="tab" class="tab" :aria-selected="filter === 'garages'" @click="filter = 'garages'">Garages<span class="n">{{ users ? count('garages') : '' }}</span></button>
      </div>
      <table class="t">
        <thead>
          <tr><th>Nom</th><th>Rôle</th><th>Garage</th><th class="hide-s">Dernière connexion</th><th><span class="sr-only">Actions</span></th></tr>
        </thead>
        <tbody>
          <template v-if="!users && !error">
            <tr v-for="i in 4" :key="i"><td colspan="5"><span class="skel" style="display: block; width: 240px; height: 14px" /></td></tr>
          </template>
          <tr v-for="u in shown" :key="u.id">
            <td>
              <span class="col" style="gap: 1px">
                <span style="font-weight: 500">{{ u.name }}{{ u.id === session.user?.id ? ' (vous)' : '' }}</span>
                <span class="muted" style="font-size: 12px">{{ u.email }}</span>
              </span>
            </td>
            <td>{{ ROLE_LABEL[u.role] }}</td>
            <td class="soft">{{ u.garage_name ?? '–' }}</td>
            <td class="soft hide-s">{{ since(u.last_login_at) }}</td>
            <td style="text-align: right"><MoreMenu v-if="u.id !== session.user?.id" :items="menu(u)" label="Gérer" /></td>
          </tr>
        </tbody>
      </table>
      <div v-if="users && !shown.length" class="empty">Personne dans cette liste.</div>
    </div>

    <section v-if="invitations.length" class="col" style="gap: 10px" aria-labelledby="iv-t">
      <h2 id="iv-t">Invitations en attente</h2>
      <PendingInvitations :invitations="invitations" base="/admin/invitations" show-garage @update="(l) => (invitations = l)" />
    </section>
  </div>

  <InviteModal v-if="inviteOpen" mending :garages="session.garages" @close="inviteOpen = false" @created="onInvited" />

  <AppModal v-if="editing" :title="`Rôle de ${editing.name}`" @close="editing = null">
    <div class="col" style="gap: 14px">
      <label class="lbl">
        Rôle
        <select v-model="form.role" class="f">
          <option value="manager">Gérant</option>
          <option value="photographer">Photographe</option>
          <option value="super_admin">Admin Mending</option>
        </select>
      </label>
      <label v-if="form.role !== 'super_admin'" class="lbl">
        Garage
        <select v-model="form.garage_id" class="f" :aria-invalid="!!formErrors.garage_id">
          <option v-for="g in session.garages" :key="g.id" :value="g.id">{{ g.name }}</option>
        </select>
        <span v-if="formErrors.garage_id" class="err">{{ formErrors.garage_id }}</span>
      </label>
      <p v-if="form.role === 'super_admin'" class="alert alert-warn" style="margin: 0">Un admin Mending voit l'espace Mending et tous les garages.</p>
    </div>
    <template #footer>
      <button type="button" class="btn" @click="editing = null">Annuler</button>
      <button type="button" class="btn btn-primary" :disabled="saving" @click="saveRole"><span v-if="saving" class="spinner" aria-hidden="true" />Enregistrer</button>
    </template>
  </AppModal>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.tbar {
  padding: 8px;
  border-bottom: 1px solid #e3e3e3;
}
</style>
