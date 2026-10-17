<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

interface SkeletonProps {
  class?: HTMLAttributes["class"]
  shimmer?: boolean
}

const props = withDefaults(defineProps<SkeletonProps>(), {
  shimmer: true,
})
</script>

<template>
  <div
    data-slot="skeleton"
    :class="cn('relative overflow-hidden rounded-md bg-muted-foreground/15 dark:bg-muted-foreground/20', props.class)"
  >
    <!-- Sweeping Shimmer Light Wave -->
    <div
      v-if="props.shimmer"
      class="pointer-events-none absolute inset-0 -translate-x-full animate-[shimmer_1.8s_infinite] bg-gradient-to-r from-transparent via-white/60 to-transparent dark:via-white/15"
      aria-hidden="true"
    />
  </div>
</template>
