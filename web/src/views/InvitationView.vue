<script setup lang="ts">
// Page ouverte depuis le lien d'invitation : la personne choisit son mot de passe et arrive directement dans l'outil.
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, get, post, errorMessage } from '../api'
import { isSuper, hasGarage, startSession } from '../session'
import type { User } from '../types'

interface InviteInfo {
  name: string | null
  email: string | null
  role: string
  role_label: string
  garage_name: string | null
  expired: boolean
  accepted: boolean
}

const route = useRoute()
const router = useRouter()
const token = String(route.params.token)
const info = ref<InviteInfo | null>(null)
const loadError = ref('')
const form = reactive({ name: '', email: '', password: '' })
const errors = ref<Record<string, string>>({})
const error = ref('')
const busy = ref(false)
const show = ref(false)

onMounted(async () => {
  try {
    info.value = await get<InviteInfo>(`/invitations/${encodeURIComponent(token)}`)
    form.name = info.value.name ?? ''
  } catch (e) {
    loadError.value = e instanceof ApiError && e.status === 404 ? "Ce lien ne marche plus. Il a peut-être été remplacé par une invitation plus récente." : errorMessage(e)
  }
})

async function submit() {
  errors.value = {}
  error.value = ''
  if (!form.name.trim()) errors.value.name = 'Indiquez votre prénom et votre nom.'
  if (!info.value?.email && !/^\S+@\S+\.\S+$/.test(form.email.trim())) errors.value.email = 'Indiquez une adresse e-mail valide.'
  if (form.password.length < 8) errors.value.password = 'Au moins 8 caractères.'
  if (Object.keys(errors.value).length) return
  busy.value = true
  try {
    const r = await post<{ token: string; user: User }>(`/invitations/${encodeURIComponent(token)}/accept`, {
      name: form.name.trim(),
      email: info.value?.email ? undefined : form.email.trim(),
      password: form.password,
      device_name: 'web',
    })
    await startSession(r.token)
    router.replace(isSuper.value && !hasGarage.value ? '/mending' : '/')
  } catch (e) {
    if (e instanceof ApiError && e.status === 422 && Object.keys(e.errors).length) for (const [k, m] of Object.entries(e.errors)) errors.value[k] = m[0]
    else error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="wrap">
    <div class="box">
      <div class="brand"><span class="logo" aria-hidden="true">P</span><span>picstosell</span></div>

      <template v-if="loadError">
        <h1>Invitation introuvable</h1>
        <p class="muted" style="margin: 0">{{ loadError }}</p>
        <RouterLink to="/login" class="btn">Aller à la connexion</RouterLink>
      </template>

      <template v-else-if="!info">
        <span class="skel" style="height: 24px; width: 60%" />
        <span class="skel" style="height: 120px" />
      </template>

      <template v-else-if="info.accepted">
        <h1>Votre accès existe déjà</h1>
        <p class="muted" style="margin: 0">Cette invitation a déjà servi. Connectez-vous avec votre adresse e-mail et votre mot de passe.</p>
        <RouterLink to="/login" class="btn btn-primary">Se connecter</RouterLink>
      </template>

      <template v-else-if="info.expired">
        <h1>Invitation expirée</h1>
        <p class="muted" style="margin: 0">Demandez à la personne qui vous a invité de vous en renvoyer une.</p>
      </template>

      <form v-else class="col" style="gap: 14px" novalidate @submit.prevent="submit">
        <div class="col" style="gap: 4px">
          <h1>Rejoindre {{ info.garage_name ?? "l'équipe Mending" }}</h1>
          <p class="muted" style="margin: 0; font-size: 13px">Vous êtes invité en tant que <strong style="color: var(--ink)">{{ info.role === 'super_admin' ? info.role_label : info.role_label.toLowerCase() }}</strong>. Choisissez votre mot de passe pour créer votre accès.</p>
        </div>
        <label class="lbl">
          Prénom et nom
          <input v-model="form.name" class="f" autocomplete="name" :aria-invalid="!!errors.name" />
          <span v-if="errors.name" class="err">{{ errors.name }}</span>
        </label>
        <label class="lbl">
          E-mail
          <input v-if="info.email" class="f" :value="info.email" readonly autocomplete="username" />
          <input v-else v-model="form.email" class="f" type="email" inputmode="email" autocomplete="username" :aria-invalid="!!errors.email" />
          <span v-if="errors.email" class="err">{{ errors.email }}</span>
        </label>
        <label class="lbl">
          Mot de passe
          <span class="pw">
            <input v-model="form.password" class="f" :type="show ? 'text' : 'password'" autocomplete="new-password" :aria-invalid="!!errors.password" />
            <button type="button" class="btn-link" @click="show = !show">{{ show ? 'Masquer' : 'Afficher' }}</button>
          </span>
          <span v-if="errors.password" class="err">{{ errors.password }}</span>
          <span v-else class="muted" style="font-weight: 400; font-size: 12.5px">Au moins 8 caractères.</span>
        </label>
        <p v-if="error" class="alert alert-error" role="alert" style="margin: 0">{{ error }}</p>
        <button type="submit" class="btn btn-primary btn-lg" :disabled="busy"><span v-if="busy" class="spinner" aria-hidden="true" />Créer mon accès</button>
        <p class="muted" style="margin: 0; font-size: 12.5px">Vous utiliserez la même adresse et le même mot de passe dans l'appli mobile.</p>
      </form>
    </div>
  </div>
</template>

<style scoped>
.wrap {
  background: var(--ground);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 16px;
}
.box {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.07), 0 0 0 1px rgba(0, 0, 0, 0.06);
  padding: 28px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
  font-size: 15px;
}
.logo {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  background: #303030;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
}
.pw {
  position: relative;
  display: block;
}
.pw .f {
  padding-right: 76px;
}
.pw .btn-link {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 12.5px;
}
</style>
