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
    path: '/escribir',
    name: 'letter-new',
    component: () => import('@/views/letters/EditorView.vue'),
    meta: { auth: true },
  },
  {
    path: '/escribir/:id',
    name: 'letter-edit',
    component: () => import('@/views/letters/EditorView.vue'),
    meta: { auth: true },
  },
  {
    path: '/enviar/:id',
    name: 'letter-send',
    component: () => import('@/views/letters/SendView.vue'),
    meta: { auth: true, verified: true },
  },
  {
    path: '/buzon',
    name: 'mailbox',
    component: () => import('@/views/mailbox/MailboxView.vue'),
    meta: { auth: true },
  },
  {
    path: '/buzon/:id',
    name: 'mailbox-read',
    component: () => import('@/views/mailbox/MailboxReadView.vue'),
    meta: { auth: true },
  },
  {
    path: '/envios',
    name: 'outbox',
    component: () => import('@/views/deliveries/OutboxView.vue'),
    meta: { auth: true },
  },
  {
    path: '/programaciones',
    name: 'schedules',
    component: () => import('@/views/schedules/SchedulesView.vue'),
    meta: { auth: true },
  },
  {
    path: '/programaciones/:id',
    name: 'schedule-timeline',
    component: () => import('@/views/schedules/ScheduleTimelineView.vue'),
    meta: { auth: true },
  },
  {
    path: '/botella',
    name: 'bottle',
    component: () => import('@/views/random/BottleView.vue'),
    meta: { auth: true, verified: true },
  },
  {
    path: '/blog',
    name: 'blog',
    component: () => import('@/views/blog/BlogFeedView.vue'),
    meta: { auth: true },
  },
  {
    path: '/blog/nuevo',
    name: 'blog-compose',
    component: () => import('@/views/blog/PostComposeView.vue'),
    meta: { auth: true, verified: true },
  },
  {
    path: '/blog/consentimientos',
    name: 'blog-consent',
    component: () => import('@/views/blog/ConsentRequestsView.vue'),
    meta: { auth: true },
  },
  {
    path: '/blog/:slug',
    name: 'blog-post',
    component: () => import('@/views/blog/PostView.vue'),
    meta: { auth: true },
  },
  {
    path: '/envios/:id',
    name: 'delivery-tracking',
    component: () => import('@/views/deliveries/DeliveryTrackingView.vue'),
    meta: { auth: true },
  },
  {
    path: '/dolls',
    name: 'dolls',
    component: () => import('@/views/dolls/DollDirectoryView.vue'),
    meta: { auth: true },
  },
  {
    path: '/dolls/ser-doll',
    name: 'doll-become',
    component: () => import('@/views/dolls/BecomeDollView.vue'),
    meta: { auth: true, verified: true },
  },
  {
    path: '/dolls/solicitudes',
    name: 'doll-requests',
    component: () => import('@/views/dolls/DollRequestsListView.vue'),
    meta: { auth: true },
  },
  {
    path: '/dolls/solicitudes/nueva',
    name: 'doll-request-new',
    component: () => import('@/views/dolls/DollRequestNewView.vue'),
    meta: { auth: true, verified: true },
  },
  {
    path: '/dolls/solicitudes/:id',
    name: 'doll-request-detail',
    component: () => import('@/views/dolls/DollRequestDetailView.vue'),
    meta: { auth: true },
  },
  {
    path: '/dolls/:id',
    name: 'doll-profile',
    component: () => import('@/views/dolls/DollProfileView.vue'),
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
