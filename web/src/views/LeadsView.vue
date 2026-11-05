<script setup lang="ts">
// Demandes reçues : la liste, comme le parc. Chaque ligne ouvre la page de la demande.
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { get, errorMessage } from '../api'
import { relative } from '../labels'
import { session } from '../session'
import { LEAD_KIND, leadSubject } from '../leadText'
import type { Lead } from '../types'

const route = useRoute()
const router = useRouter()
const leads = ref<Lead[] | null>(null)
const error = ref('')
const filter = ref<'all' | 'new' | 'done'>(route.query.statut === 'traitees' ? 'done' : route.query.statut === 'nouvelles' ? 'new' : 'all')

async function load() {
  error.value = ''
  try {
    const r = await get<{ data: Lead[]; new_count: number }>('/leads')
    leads.value = r.data
    session.newLeads = r.new_count
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(() => {
  // Ancien lien « /leads?id=12 » : on ouvre directement la demande.
  if (route.query.id) router.replace(`/leads/${route.query.id}`)
  else load()
})

const shown = computed(() => (leads.value ?? []).filter((l) => filter.value === 'all' || l.status === filter.value))
const newCount = computed(() => (leads.value ?? []).filter((l) => l.status === 'new').length)
</script>

<template>
  <div class="page">
    <div class="page-head">
      <h1>Demandes</h1>
    </div>

    <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

    <div class="table-wrap">
      <div role="tablist" aria-label="Filtrer" class="tabs tbar">
        <button type="button" role="tab" class="tab" :aria-selected="filter === 'all'" @click="filter = 'all'">Toutes<span class="n">{{ leads?.length ?? '' }}</span></button>
        <button type="button" role="tab" class="tab" :aria-selected="filter === 'new'" @click="filter = 'new'">Nouvelles<span class="n">{{ leads ? newCount : '' }}</span></button>
        <button type="button" role="tab" class="tab" :aria-selected="filter === 'done'" @click="filter = 'done'">Traitées</button>
      </div>
      <table class="t">
        <thead>
          <tr>
            <th>Client</th>
            <th>Demande</th>
            <th class="hide-s">Message</th>
            <th class="hide-s">Reçue</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody v-if="!leads && !error">
          <tr v-for="i in 5" :key="i">
            <td v-for="j in 5" :key="j" :class="{ 'hide-s': j === 3 || j === 4 }"><span class="skel" style="display: block; width: 90px; height: 14px" /></td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr v-for="l in shown" :key="l.id" class="clickable" :class="{ unread: l.status === 'new' }" @click="router.push(`/leads/${l.id}`)">
            <td>
              <RouterLink :to="`/leads/${l.id}`" class="who" @click.stop>{{ l.name }}</RouterLink>
            </td>
            <td class="what">
              <span>{{ leadSubject(l) }}</span>
              <span class="muted sub">{{ LEAD_KIND[l.type] ?? 'Demande' }}</span>
            </td>
            <td class="msg hide-s">{{ l.message || '–' }}</td>
            <td class="soft hide-s">{{ relative(l.created_at) }}</td>
            <td>
              <span class="pill" :class="l.status === 'new' ? 'p-new' : 'p-done'">{{ l.status === 'new' ? 'Nouvelle' : 'Traitée' }}</span>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="leads && !leads.length" class="empty">
        <strong>Aucune demande pour l'instant</strong>
        <span>Quand un client remplit un formulaire sur votre site, sa demande arrive ici.</span>
        <a v-if="session.garage?.site_url" :href="session.garage.site_url" target="_blank" rel="noopener" class="btn">Voir mon site</a>
      </div>
      <div v-else-if="leads && !shown.length" class="empty">Aucune demande dans cette liste.</div>
    </div>
  </div>
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
.who {
  color: var(--ink);
  text-decoration: none;
  font-weight: 500;
}
.unread .who {
  font-weight: 650;
}
.who:hover {
  text-decoration: underline;
  color: var(--ink);
}
.what {
  white-space: normal;
}
.what span {
  display: block;
}
.sub {
  font-size: 12px;
}
.msg {
  color: var(--muted);
  max-width: 380px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.p-new {
  background: #e0f0ff;
  color: #00428a;
}
.p-done {
  background: #f1f1f1;
  color: var(--muted);
}
@media (max-width: 760px) {
  .hide-s {
    display: none;
  }
}
</style>
