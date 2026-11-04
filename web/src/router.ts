import { createRouter, createWebHistory } from 'vue-router'
import { auth } from './api'
import { session, loadMe, isSuper, isManager, hasGarage } from './session'

declare module 'vue-router' {
  interface RouteMeta {
    /** garage : barre latérale garage ; mending : espace interne ; bare : sans barre ; public : sans connexion */
    layout?: 'garage' | 'mending' | 'bare' | 'public'
    /** Rôle minimum : manager (gérant ou super-admin) ou super */
    role?: 'manager' | 'super'
    title?: string
  }
}

const router = createRouter({
  history: createWebHistory('/app/'),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    { path: '/login', component: () => import('./views/LoginView.vue'), meta: { layout: 'public', title: 'Connexion' } },
    { path: '/', component: () => import('./views/HomeView.vue'), meta: { layout: 'garage', title: 'Accueil' } },
    { path: '/vehicles', component: () => import('./views/StockView.vue'), meta: { layout: 'garage', title: 'Mon stock' } },
    { path: '/vehicles/new', component: () => import('./views/AddVehicleView.vue'), meta: { layout: 'garage', title: 'Ajouter une voiture' } },
    { path: '/vehicles/:id(\\d+)', component: () => import('./views/VehicleView.vue'), meta: { layout: 'bare', title: 'Vérifier et mettre en ligne' } },
    { path: '/leads', component: () => import('./views/LeadsView.vue'), meta: { layout: 'garage', role: 'manager', title: 'Demandes reçues' } },
    { path: '/showroom', component: () => import('./views/ShowroomView.vue'), meta: { layout: 'garage', role: 'manager', title: 'Mon showroom' } },
    { path: '/site', component: () => import('./views/SiteView.vue'), meta: { layout: 'garage', role: 'manager', title: 'Mon site' } },
    { path: '/settings', component: () => import('./views/SettingsView.vue'), meta: { layout: 'garage', role: 'manager', title: 'Réglages' } },
    { path: '/mending', component: () => import('./views/mending/OverviewView.vue'), meta: { layout: 'mending', role: 'super', title: 'Espace Mending' } },
    { path: '/mending/models', component: () => import('./views/mending/ModelsView.vue'), meta: { layout: 'mending', role: 'super', title: 'Réglages photo IA' } },
    { path: '/mending/comparisons', component: () => import('./views/mending/ComparisonsView.vue'), meta: { layout: 'mending', role: 'super', title: 'Comparer les modèles' } },
    { path: '/mending/comparisons/:id(\\d+)', component: () => import('./views/mending/ComparisonView.vue'), meta: { layout: 'mending', role: 'super', title: 'Comparatif' } },
    { path: '/:pathMatch(.*)*', component: () => import('./views/NotFoundView.vue'), meta: { layout: 'bare', title: 'Page introuvable' } },
  ],
})

router.beforeEach(async (to) => {
  if (to.meta.layout === 'public') {
    if (auth.token && to.path === '/login') {
      try {
        if (!session.loaded) await loadMe()
        return '/'
      } catch {
        return true
      }
    }
    return true
  }
  if (!auth.token) return { path: '/login', query: to.fullPath !== '/' ? { next: to.fullPath } : {} }
  if (!session.loaded) {
    try {
      await loadMe()
    } catch {
      return { path: '/login' }
    }
  }
  if (to.meta.role === 'super' && !isSuper.value) return '/'
  if (to.meta.role === 'manager' && !isManager.value) return '/'
  // Un super-admin sans garage choisi va dans l'espace Mending.
  if (isSuper.value && to.meta.layout !== 'mending' && !hasGarage.value && to.matched.length && !to.path.startsWith('/mending')) {
    if (to.meta.title !== 'Page introuvable') return '/mending'
  }
  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · picstosell` : 'picstosell'
})

export default router
