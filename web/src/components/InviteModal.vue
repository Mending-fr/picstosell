<script setup lang="ts">
// Inviter quelqu'un : par e-mail, ou simplement avec un lien à partager. Sert au garage et à l'espace Mending.
import { computed, reactive, ref } from 'vue'
import { ApiError, post, errorMessage } from '../api'
import { copyText } from '../labels'
import { toast } from '../session'
import { ROLE_HELP, ROLE_LABEL } from '../team'
import AppModal from './AppModal.vue'
import type { GarageSummary, Invitation, Role } from '../types'

const props = defineProps<{
  /** Espace Mending : on peut inviter un admin Mending et choisir le garage. */
  mending?: boolean
  garages?: GarageSummary[]
  garageId?: number | null
}>()
const emit = defineEmits<{ close: []; created: [inv: Invitation] }>()

const roles = computed<Role[]>(() => (props.mending ? ['manager', 'photographer', 'super_admin'] : ['photographer', 'manager']))
const form = reactive({
  role: (props.mending ? 'manager' : 'photographer') as Role,
  garage_id: props.garageId ?? props.garages?.[0]?.id ?? null,
  name: '',
  email: '',
})
const errors = ref<Record<string, string>>({})
const busy = ref(false)
const done = ref<Invitation | null>(null)
const copied = ref(false)

async function submit() {
  errors.value = {}
  const email = form.email.trim()
  if (email && !/^\S+@\S+\.\S+$/.test(email)) errors.value.email = 'Cette adresse ne semble pas valide.'
  if (props.mending && form.role !== 'super_admin' && !form.garage_id) errors.value.garage_id = 'Choisissez le garage.'
  if (Object.keys(errors.value).length) return
  busy.value = true
  try {
    const inv = await post<Invitation>(props.mending ? '/admin/invitations' : '/invitations', {
      role: form.role,
      garage_id: props.mending && form.role !== 'super_admin' ? form.garage_id : undefined,
      name: form.name.trim() || null,
      email: email || null,
    })
    done.value = inv
    copied.value = false
    emit('created', inv)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) for (const [k, m] of Object.entries(e.errors)) errors.value[k] = m[0]
    else toast(errorMessage(e), 'error')
  } finally {
    busy.value = false
  }
}

async function copy() {
  if (!done.value) return
  copied.value = await copyText(done.value.link)
  if (!copied.value) toast('La copie a échoué. Sélectionnez le lien à la main.', 'error')
}

function again() {
  done.value = null
  form.name = ''
  form.email = ''
}
</script>

<template>
  <AppModal :title="done ? 'Invitation prête' : 'Inviter quelqu\'un'" @close="emit('close')">
    <form v-if="!done" id="invite-form" class="col" style="gap: 14px" novalidate @submit.prevent="submit">
      <fieldset class="roles">
        <legend class="lbl">Rôle</legend>
        <label v-for="r in roles" :key="r" class="role" :class="{ on: form.role === r }">
          <input v-model="form.role" type="radio" name="role" :value="r" />
          <span class="col" style="gap: 1px">
            <span class="rt">{{ ROLE_LABEL[r] }}</span>
            <span class="muted rh">{{ ROLE_HELP[r] }}</span>
          </span>
        </label>
      </fieldset>

      <label v-if="mending && form.role !== 'super_admin'" class="lbl">
        Garage
        <select v-model="form.garage_id" class="f" :aria-invalid="!!errors.garage_id">
          <option v-for="g in garages ?? []" :key="g.id" :value="g.id">{{ g.name }}</option>
        </select>
        <span v-if="errors.garage_id" class="err">{{ errors.garage_id }}</span>
      </label>

      <label class="lbl">
        E-mail
        <input v-model="form.email" class="f" type="email" placeholder="prenom@garage.fr" autocomplete="off" :aria-invalid="!!errors.email" />
        <span v-if="errors.email" class="err">{{ errors.email }}</span>
        <span v-else class="hint">Laissez vide pour obtenir seulement un lien à envoyer vous-même (SMS, WhatsApp…).</span>
      </label>
      <label class="lbl">
        <span>Prénom et nom <span class="muted" style="font-weight: 400">(facultatif)</span></span>
        <input v-model="form.name" class="f" autocomplete="off" />
      </label>
    </form>

    <div v-else class="col" style="gap: 14px">
      <p v-if="done.email && done.emailed_at" class="alert alert-ok" style="margin: 0">E-mail envoyé à {{ done.email }}.</p>
      <p v-else-if="done.email" class="alert alert-warn" style="margin: 0">L'e-mail n'a pas pu partir. Copiez le lien ci-dessous et envoyez-le vous-même à {{ done.email }}.</p>
      <p v-else class="muted" style="margin: 0; font-size: 13px">Envoyez ce lien à la personne. Elle choisira son adresse et son mot de passe en l'ouvrant.</p>
      <label class="lbl">
        Lien d'invitation, {{ done.role === 'super_admin' ? done.role_label : done.role_label.toLowerCase() }}{{ done.garage_name ? ` chez ${done.garage_name}` : '' }}
        <span class="link">
          <input class="f" :value="done.link" readonly @focus="($event.target as HTMLInputElement).select()" />
          <button type="button" class="btn" @click="copy">{{ copied ? 'Copié' : 'Copier' }}</button>
        </span>
        <span class="hint">Valable 7 jours, pour une seule personne.</span>
      </label>
    </div>

    <template #footer>
      <template v-if="!done">
        <button type="button" class="btn" @click="emit('close')">Annuler</button>
        <button type="submit" form="invite-form" class="btn btn-primary" :disabled="busy">
          <span v-if="busy" class="spinner" aria-hidden="true" />{{ form.email.trim() ? "Envoyer l'invitation" : 'Créer le lien' }}
        </button>
      </template>
      <template v-else>
        <button type="button" class="btn" @click="again">Inviter quelqu'un d'autre</button>
        <button type="button" class="btn btn-primary" @click="emit('close')">Terminé</button>
      </template>
    </template>
  </AppModal>
</template>

<style scoped>
.roles {
  border: 0;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.roles legend {
  margin-bottom: 6px;
}
.role {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  border-radius: 10px;
  padding: 10px 12px;
  box-shadow: 0 0 0 1px #e3e3e3;
  cursor: pointer;
}
.role.on {
  box-shadow: 0 0 0 2px #303030;
}
.role input {
  margin: 2px 0 0;
}
.rt {
  font-size: 13px;
  font-weight: 600;
}
.rh,
.hint {
  font-size: 12.5px;
  font-weight: 400;
  color: var(--muted);
}
.link {
  display: flex;
  gap: 8px;
}
.link .f {
  font-size: 12.5px;
  color: var(--ink-2);
}
</style>
