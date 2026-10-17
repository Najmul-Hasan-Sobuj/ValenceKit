<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        loading?: boolean;
        class?: string;
    }>(),
    {
        loading: false,
        class: '',
    },
);
</script>

<template>
    <div class="relative w-full" :class="props.class">
        <Transition
            mode="out-in"
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="loading" key="skeleton" class="w-full">
                <slot name="skeleton" />
            </div>
            <div v-else key="content" class="w-full">
                <slot />
            </div>
        </Transition>
    </div>
</template>
