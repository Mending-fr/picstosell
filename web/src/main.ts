import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { auth } from './api'
import { clearSession } from './session'
import './styles.css'

auth.onUnauthorized(() => {
  clearSession()
  const here = router.currentRoute.value
  if (here.path !== '/login') router.replace({ path: '/login', query: { next: here.fullPath } })
})

createApp(App).use(router).mount('#app')
