<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, errorMessage } from '../api'
import { login, isSuper, hasGarage } from '../session'

const router = useRouter()
const route = useRoute()
const email = ref('')
const password = ref('')
const busy = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  if (!email.value || !password.value) {
    error.value = 'Indiquez votre e-mail et votre mot de passe.'
    return
  }
  busy.value = true
  try {
    await login(email.value.trim(), password.value)
    const next = typeof route.query.next === 'string' && route.query.next.startsWith('/') ? route.query.next : null
    if (next && next !== '/login') router.replace(next)
    else router.replace(isSuper.value && !hasGarage.value ? '/mending' : '/')
  } catch (e) {
    error.value = e instanceof ApiError && e.status === 422 ? (e.field('email') ?? e.message) : errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="wrap">
    <form class="box" novalidate @submit.prevent="submit">
      <div class="brand">
        <span class="logo" aria-hidden="true">P</span>
        <span>picstosell</span>
      </div>
      <h1>Connexion</h1>
      <p class="lead" style="margin-top: -8px">Connectez-vous pour gérer les voitures de votre garage.</p>
      <label class="lbl">
        E-mail
        <input v-model="email" class="f" type="email" autocomplete="username" inputmode="email" required :aria-invalid="!!error" />
      </label>
      <label class="lbl">
        Mot de passe
        <input v-model="password" class="f" type="password" autocomplete="current-password" required :aria-invalid="!!error" />
      </label>
      <p v-if="error" class="alert alert-error" role="alert" style="margin: 0">{{ error }}</p>
      <button type="submit" class="btn btn-primary btn-lg" :disabled="busy">
        <span v-if="busy" class="spinner" aria-hidden="true" />
        Se connecter
      </button>
      <p class="muted" style="font-size: 13px; margin: 0">Mot de passe oublié ? Demandez au gérant du garage ou écrivez à l'équipe Mending.</p>
    </form>
  </div>
</template>

<style scoped>
.wrap {
  background: linear-gradient(180deg, var(--accent-band) 0%, var(--ground) 60%);
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
  border: 1px solid var(--line);
  border-radius: 20px;
  padding: 32px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 500;
  font-size: 18px;
}
.logo {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  background: var(--accent-soft);
  color: var(--accent-ink);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--mono);
}
</style>
