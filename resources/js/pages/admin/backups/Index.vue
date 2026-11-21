<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import {
    mountDashboard,
    type DashboardData,
} from '../../../drivesnap/dashboard.js';
import '../../../drivesnap/dashboard.css';
const props = defineProps<{ drivesnap: DashboardData }>();
const root = ref<HTMLElement | null>(null);
let dispose: (() => void) | undefined;
onMounted(() => {
    if (root.value) dispose = mountDashboard(root.value, props.drivesnap);
});
onUnmounted(() => dispose?.());
</script>
<template>
    <div>
        <Head title="DriveSnap backups" />
        <div ref="root" class="drivesnap">
            <p role="status">Loading backup dashboard…</p>
        </div>
    </div>
</template>
