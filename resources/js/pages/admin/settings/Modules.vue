<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Boxes, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { edit, update } from '@/routes/admin/modules';

interface Module {
    key: string;
    name: string;
    description: string;
    enabled: boolean;
}

const props = defineProps<{ modules: Module[] }>();
const form = useForm({
    modules: Object.fromEntries(
        props.modules.map((module) => [module.key, module.enabled]),
    ) as Record<string, boolean>,
});
const activeCount = computed(
    () => Object.values(form.modules).filter(Boolean).length,
);

function submit() {
    form.patch(update.url(), { preserveScroll: true });
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Modules', href: edit() }] },
});
</script>

<template>
    <Head title="Modules & Features" />
    <div class="min-w-0 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                variant="small"
                title="Modules & Features"
                description="Control which business modules are available across the application."
            />
            <Badge variant="secondary">
                {{ activeCount }} / {{ modules.length }} active
            </Badge>
        </div>

        <div
            class="flex items-start gap-3 rounded-xl border bg-muted/30 p-4 text-sm"
        >
            <ShieldCheck class="mt-0.5 size-5 shrink-0 text-primary" />
            <p class="leading-relaxed text-muted-foreground">
                Disabled modules are removed from navigation and their routes
                return Not Found. Existing role permissions stay unchanged.
            </p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="divide-y rounded-xl border bg-card">
                <div
                    v-for="module in modules"
                    :key="module.key"
                    class="flex items-center gap-4 p-4"
                >
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Boxes class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-semibold">
                                {{ module.name }}
                            </h2>
                            <Badge
                                :variant="
                                    form.modules[module.key]
                                        ? 'secondary'
                                        : 'outline'
                                "
                                class="text-[10px]"
                            >
                                {{
                                    form.modules[module.key]
                                        ? 'Enabled'
                                        : 'Disabled'
                                }}
                            </Badge>
                        </div>
                        <p
                            class="mt-1 text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ module.description }}
                        </p>
                    </div>
                    <Switch
                        v-model="form.modules[module.key]"
                        :aria-label="`Toggle ${module.name}`"
                        :disabled="form.processing"
                    />
                </div>
            </div>

            <InputError :message="form.errors.modules" />

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save changes' }}
                </Button>
            </div>
        </form>
    </div>
</template>
