<script setup lang="ts">
withDefaults(defineProps<{ color?: string; sigil?: string; size?: number; broken?: boolean }>(), {
  color: '#8b2635',
  size: 56,
})

const sigils: Record<string, string> = {
  violet: 'M12 3c2 3 5 4 5 8a5 5 0 0 1-10 0c0-4 3-5 5-8Z',
  lily: 'M12 4c1 4 4 4 6 3-2 4-6 4-6 9 0-5-4-5-6-9 2 1 5 1 6-3Z',
  feather: 'M6 18c6 0 12-6 12-12-6 0-12 6-12 12Zm0 0 4-4',
  compass: 'M12 4v16M4 12h16M8 8l8 8',
}
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    :class="{ 'wax-broken': broken }"
    aria-hidden="true"
  >
    <circle cx="12" cy="12" r="11" :fill="color" />
    <circle cx="12" cy="12" r="11" fill="none" stroke="rgba(0,0,0,.25)" stroke-width="1" />
    <path
      :d="sigils[sigil ?? 'violet'] ?? sigils.violet"
      fill="none"
      stroke="rgba(255,255,255,.85)"
      stroke-width="1.4"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
</template>

<style scoped>
.wax-broken {
  animation: crack 1.2s var(--ease-calm, ease) forwards;
}
@keyframes crack {
  60% {
    transform: rotate(-4deg) scale(1.04);
  }
  100% {
    transform: translateY(6px) rotate(-10deg) scale(0.9);
    opacity: 0;
  }
}
</style>
