<script setup lang="ts">
defineProps<{ label?: string }>()
</script>

<template>
  <span class="spinner" role="status" :aria-label="label">
    <span></span><span></span><span></span>
  </span>
</template>

<style scoped>
.spinner {
  display: inline-flex;
  gap: 3px;
  align-items: center;
}
.spinner span {
  width: 5px;
  height: 5px;
  border-radius: 9999px;
  background: currentColor;
  animation: pulse 1s var(--ease-calm, ease) infinite;
}
.spinner span:nth-child(2) {
  animation-delay: 0.15s;
}
.spinner span:nth-child(3) {
  animation-delay: 0.3s;
}
@keyframes pulse {
  0%,
  100% {
    opacity: 0.3;
  }
  50% {
    opacity: 1;
  }
}
</style>
