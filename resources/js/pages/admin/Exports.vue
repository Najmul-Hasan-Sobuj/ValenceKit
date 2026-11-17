<script setup lang="ts">
import { Head, useForm, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Download, RefreshCw } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { usePermissions } from '@/composables/usePermissions';
import { store, download, index } from '@/routes/admin/exports';
defineOptions({ breadcrumbs: [{ title: 'Exports', href: index.url() }] });
const props = defineProps<{
    exports: {
        id: number;
        module: string;
        status: string;
        created_at: string;
        expires_at: string;
    }[];
}>();
const { can } = usePermissions();
const modules = computed(() =>
    ['finance', 'leads', 'hr'].filter((module) => can(`${module}.export`)),
);
const form = useForm({ module: modules.value[0] ?? '', from: '', to: '' });
usePoll(15000, { only: ['exports'] });
</script>
<template>
    <div class="space-y-6 p-4 md:p-6">
        <Head title="Private exports" />
        <header>
            <h1 class="text-2xl font-semibold">Private exports</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Prepare larger datasets in the background. Downloads expire
                after 24 hours and require your current permissions.
            </p>
        </header>
        <form
            class="grid items-end gap-4 rounded-xl border bg-card p-4 sm:grid-cols-4"
            @submit.prevent="form.post(store.url())"
        >
            <label class="text-sm"
                >Module<select
                    v-model="form.module"
                    class="mt-1 h-9 w-full rounded-md border bg-background px-2"
                >
                    <option
                        v-for="module in modules"
                        :key="module"
                        :value="module"
                    >
                        {{ module === 'hr' ? 'Attendance report' : module }}
                    </option></select
                ><span class="text-destructive">{{
                    form.errors.module
                }}</span></label
            >
            <label class="text-sm"
                >From
                <span class="text-muted-foreground">(required for HR)</span
                ><Input
                    v-model="form.from"
                    type="date"
                    :required="form.module === 'hr'"
                /><span class="text-destructive">{{
                    form.errors.from
                }}</span></label
            >
            <label class="text-sm"
                >To<Input
                    v-model="form.to"
                    type="date"
                    :required="form.module === 'hr'"
                /><span class="text-destructive">{{
                    form.errors.to
                }}</span></label
            >
            <Button :disabled="form.processing"
                ><RefreshCw class="size-4" />Prepare export</Button
            >
        </form>
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-3">Module</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Expires</th>
                        <th class="p-3">Download</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in props.exports"
                        :key="item.id"
                        class="border-t"
                    >
                        <td class="p-3 capitalize">{{ item.module }}</td>
                        <td class="p-3">
                            <Badge variant="secondary">{{ item.status }}</Badge>
                        </td>
                        <td class="p-3">
                            {{ new Date(item.expires_at).toLocaleString() }}
                        </td>
                        <td class="p-3">
                            <Button
                                v-if="
                                    item.status === 'ready' &&
                                    new Date(item.expires_at).getTime() >
                                        Date.now() &&
                                    can(`${item.module}.export`)
                                "
                                as-child
                                variant="outline"
                                size="sm"
                                ><a :href="download.url(item.id)"
                                    ><Download class="size-4" />Download CSV</a
                                ></Button
                            ><span v-else class="text-muted-foreground">{{
                                item.status === 'failed'
                                    ? 'Request a new export'
                                    : '—'
                            }}</span>
                        </td>
                    </tr>
                    <tr v-if="!props.exports.length">
                        <td
                            colspan="4"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No exports requested yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
