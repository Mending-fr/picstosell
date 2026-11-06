<script setup lang="ts">
// Invitations pas encore acceptées : copier le lien, renvoyer (nouveau lien), annuler.
import { ref } from 'vue'
import { del, post, errorMessage } from '../api'
import { copyText } from '../labels'
import { toast } from '../session'
import { until } from '../team'
import type { Invitation } from '../types'

const props = defineProps<{ invitations: Invitation[]; base: string; showGarage?: boolean }>()
const emit = defineEmits<{ update: [list: Invitation[]] }>()
const busy = ref<number | null>(null)

async function copy(i: Invitation) {
  const ok = await copyText(i.link)
  toast(ok ? 'Lien copié.' : 'La copie a échoué.', ok ? 'ok' : 'error')
}
async function resend(i: Invitation) {
  busy.value = i.id
  try {
    const n = await post<Invitation>(`${props.base}/${i.id}/resend`)
    emit('update', props.invitations.map((x) => (x.id === i.id ? n : x)))
    if (n.email && n.emailed_at) toast(`Nouvelle invitation envoyée à ${n.email}. L'ancien lien ne marche plus.`)
    else {
      const ok = await copyText(n.link)
      toast(ok ? "Nouveau lien copié. L'ancien ne marche plus." : "Nouveau lien créé. L'ancien ne marche plus.")
    }
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    busy.value = null
  }
}
async function revoke(i: Invitation) {
  if (!window.confirm(`Annuler l'invitation${i.email ? ` de ${i.email}` : ''} ? Le lien ne marchera plus.`)) return
  busy.value = i.id
  try {
    await del(`${props.base}/${i.id}`)
    emit('update', props.invitations.filter((x) => x.id !== i.id))
    toast('Invitation annulée.')
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <div class="table-wrap">
    <table class="t">
      <thead>
        <tr>
          <th>Invitation</th>
          <th>Rôle</th>
          <th v-if="showGarage">Garage</th>
          <th class="hide-s">État</th>
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="i in invitations" :key="i.id">
          <td>
            <span class="col" style="gap: 1px">
              <span class="who">{{ i.name || i.email || 'Lien sans adresse' }}</span>
              <span class="muted sub">{{ i.name && i.email ? i.email : i.invited_by ? `Par ${i.invited_by}` : '' }}</span>
            </span>
          </td>
          <td>{{ i.role_label }}</td>
          <td v-if="showGarage" class="soft">{{ i.garage_name ?? '–' }}</td>
          <td class="hide-s">
            <span v-if="i.expired" class="pill" style="background: #fedad9; color: #8e0b21">Expirée</span>
            <span v-else class="soft">{{ i.emailed_at ? 'E-mail envoyé' : 'Lien à partager' }} · {{ until(i.expires_at) }}</span>
          </td>
          <td class="acts">
            <button type="button" class="btn-link" :disabled="busy === i.id || i.expired" @click="copy(i)">Copier le lien</button>
            <button type="button" class="btn-link" :disabled="busy === i.id" @click="resend(i)">{{ i.email ? 'Renvoyer' : 'Nouveau lien' }}</button>
            <button type="button" class="btn-link danger" :disabled="busy === i.id" @click="revoke(i)">Annuler</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.who {
  font-weight: 500;
}
.sub {
  font-size: 12px;
}
.acts {
  text-align: right;
}
.acts .btn-link {
  margin-left: 10px;
}
.danger {
  color: var(--red-ink);
}
@media (max-width: 760px) {
  .hide-s {
    display: none;
  }
  .acts {
    white-space: normal;
  }
}
</style>
