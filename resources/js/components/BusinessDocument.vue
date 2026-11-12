<script setup lang="ts">
import { ref } from 'vue';

/** Presentation only: callers are responsible for authorization and calculations. */
defineProps<{
    title: string;
    reference: string;
    date: string;
    dateLabel?: string;
    metadata?: { label: string; value: string }[];
    recipient: { label: string; name: string; details?: string[] };
    columns: string[];
    rows: { id: string | number; values: string[] }[];
    totals: { label: string; value: string }[];
    note?: string | null;
}>();

const logo = ref<HTMLImageElement | null>(null);
async function printDocument(): Promise<void> {
    try {
        await logo.value?.decode();
    } catch {
        // Printing remains available if the logo cannot be loaded.
    }
    window.print();
}
defineExpose({ printDocument });
</script>

<template>
    <article
        class="business-document mx-auto w-full max-w-4xl overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-950 shadow-sm print:max-w-none print:overflow-visible print:rounded-none print:border-0 print:shadow-none"
    >
        <div
            class="px-6 pt-6 pb-8 sm:px-10 sm:pt-10 sm:pb-10 print:px-0 print:pt-0"
        >
            <header
                class="flex flex-wrap items-start justify-between gap-8 border-b-2 border-slate-900 pb-7"
            >
                <div class="min-w-0">
                    <p
                        class="mb-2 text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
                    >
                        {{ title }}
                    </p>
                    <h1
                        class="text-3xl font-semibold tracking-tight break-words text-slate-950 sm:text-4xl"
                    >
                        {{ title }}
                    </h1>
                    <p
                        class="mt-3 text-sm font-medium break-all text-slate-600"
                    >
                        {{ reference }}
                    </p>
                </div>
                <div class="min-w-0 text-sm sm:text-right">
                    <img
                        ref="logo"
                        src="/favicon.svg"
                        alt="Site logo"
                        class="mb-3 h-10 w-10 object-contain sm:ml-auto"
                    />
                </div>
            </header>

            <div class="grid gap-8 py-8 sm:grid-cols-2">
                <section class="min-w-0 text-sm" :aria-label="recipient.label">
                    <h2
                        class="mb-3 text-xs font-semibold tracking-[0.12em] text-slate-500 uppercase"
                    >
                        {{ recipient.label }}
                    </h2>
                    <p class="text-base font-semibold break-words">
                        {{ recipient.name }}
                    </p>
                    <p
                        v-for="(detail, index) in recipient.details"
                        :key="index"
                        class="mt-1 break-words text-slate-600"
                    >
                        {{ detail }}
                    </p>
                </section>
                <dl
                    class="space-y-2.5 text-sm sm:ml-auto sm:w-full sm:max-w-64"
                >
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">
                            {{ dateLabel ?? 'Date' }}
                        </dt>
                        <dd class="text-right font-medium tabular-nums">
                            {{ date }}
                        </dd>
                    </div>
                    <div
                        v-for="item in metadata"
                        :key="item.label"
                        class="flex justify-between gap-4"
                    >
                        <dt class="text-slate-500">{{ item.label }}</dt>
                        <dd class="text-right font-medium tabular-nums">
                            {{ item.value }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="overflow-x-auto print:overflow-visible">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-y border-slate-300 text-xs tracking-wide text-slate-500 uppercase"
                    >
                        <tr>
                            <th
                                v-for="(column, index) in columns"
                                :key="index"
                                scope="col"
                                class="px-2 py-3 font-semibold first:pl-0 last:pr-0 last:text-right"
                            >
                                {{ column }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="border-b border-slate-100"
                        >
                            <td
                                v-for="(value, index) in row.values"
                                :key="index"
                                class="px-2 py-4 break-words first:pl-0 last:pr-0 last:text-right last:font-medium last:tabular-nums"
                            >
                                {{ value }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <dl class="mt-6 ml-auto w-full max-w-xs space-y-3 text-sm">
                <div
                    v-for="(total, index) in totals"
                    :key="index"
                    class="flex justify-between gap-4"
                    :class="
                        index === totals.length - 1
                            ? 'border-t border-slate-900 pt-3 text-base font-semibold'
                            : ''
                    "
                >
                    <dt
                        :class="
                            index === totals.length - 1
                                ? 'text-slate-950'
                                : 'text-slate-500'
                        "
                    >
                        {{ total.label }}
                    </dt>
                    <dd class="text-right font-semibold tabular-nums">
                        {{ total.value }}
                    </dd>
                </div>
            </dl>
            <p
                v-if="note"
                class="mt-8 border-t border-slate-200 pt-5 text-sm whitespace-pre-wrap text-slate-600"
            >
                {{ note }}
            </p>
        </div>
    </article>
</template>

<style>
@media print {
    body:has(.business-document) * {
        visibility: hidden;
    }
    body:has(.business-document) .business-document,
    body:has(.business-document) .business-document * {
        visibility: visible;
    }
    body:has(.business-document) .business-document {
        position: absolute;
        inset: 0;
        width: 100%;
    }
}
</style>
