import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { authGuard } from './guards'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'landing',
    component: () => import('@/views/LandingView.vue'),
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/registro',
    name: 'register',
    component: () => import('@/views/auth/RegisterView.vue'),
    meta: { guest: true },
  },
  {
    path: '/verificar-email',
    name: 'verify-email',
    component: () => import('@/views/auth/VerifyEmailView.vue'),
  },
  {
    path: '/recuperar',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { guest: true },
  },
  {
    path: '/recuperar/nueva',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPasswordView.vue'),
    meta: { guest: true },
  },
  {
    path: '/escritorio',
    name: 'desk',
    component: () => import('@/views/DeskView.vue'),
    meta: { auth: true },
  },
  {
    path: '/ajustes',
    name: 'settings',
    component: () => import('@/views/SettingsView.vue'),
    meta: { auth: true },
  },
  {
    path: '/actualiza',
    name: 'upgrade',
    component: () => import('@/views/UpgradeView.vue'),
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(authGuard)

export default router
