<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>

<template>
  <section class="rounded-lg border border-[var(--border-soft)] bg-[var(--surface-raised)] p-5">
    <h2 class="text-lg">{{ title }}</h2>
    <p v-if="description" class="mb-4 mt-1 text-sm text-[var(--text-muted)]">{{ description }}</p>
    <div class="mt-4 flex flex-col gap-4">
      <slot />
    </div>
  </section>
</template>
