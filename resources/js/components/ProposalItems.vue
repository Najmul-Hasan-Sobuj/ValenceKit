<script setup lang="ts">
import { formatMoney } from '@/lib/money';
import type { Proposal } from '@/pages/public/Proposal.vue';
const props = defineProps<{ proposal: Proposal; embedded: boolean }>();
const money = (minor: number) => formatMoney(minor, props.proposal.currency);
</script>
<template>
    <section class="overflow-x-auto" :class="embedded ? 'p-4' : 'p-6 sm:p-8'">
        <div
            v-if="embedded"
            class="mb-3 flex items-center justify-between gap-3"
        >
            <h3 class="text-sm font-semibold">Line items</h3>
            <span class="text-xs text-muted-foreground"
                >{{ proposal.items.length }}
                {{ proposal.items.length === 1 ? 'item' : 'items' }}</span
            >
        </div>
        <div class="sm:hidden" :class="embedded ? 'divide-y' : 'space-y-3'">
            <div
                v-for="(item, i) in proposal.items"
                :key="i"
                :class="
                    embedded
                        ? 'py-2.5 first:pt-0'
                        : 'rounded-xl border bg-muted/30 p-4'
                "
            >
                <p
                    class="font-medium break-words"
                    :class="embedded ? 'text-sm' : ''"
                >
                    {{ item.description }}
                </p>
                <div
                    v-if="embedded"
                    class="mt-1 flex items-center justify-between gap-3 text-xs"
                >
                    <span class="text-muted-foreground"
                        >{{ item.quantity }} ×
                        {{ money(item.unit_price_minor) }}</span
                    ><span class="font-medium tabular-nums">{{
                        money(Math.round(item.quantity * item.unit_price_minor))
                    }}</span>
                </div>
                <div v-else class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <span class="text-muted-foreground">Quantity</span
                    ><span class="text-right">{{ item.quantity }}</span>
                    <span class="text-muted-foreground">Unit price</span
                    ><span class="text-right">{{
                        money(item.unit_price_minor)
                    }}</span>
                    <span class="font-medium">Amount</span
                    ><span class="text-right font-medium">{{
                        money(Math.round(item.quantity * item.unit_price_minor))
                    }}</span>
                </div>
            </div>
        </div>
        <table class="hidden w-full min-w-lg text-sm sm:table">
            <thead>
                <tr class="border-b text-left text-muted-foreground">
                    <th class="pb-3 font-medium">Description</th>
                    <th class="pb-3 text-right font-medium">Qty</th>
                    <th class="pb-3 text-right font-medium">Unit price</th>
                    <th class="pb-3 text-right font-medium">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(item, i) in proposal.items"
                    :key="i"
                    class="border-b"
                >
                    <td
                        class="max-w-md pr-3 break-words whitespace-normal"
                        :class="embedded ? 'py-2.5' : 'py-4'"
                    >
                        {{ item.description }}
                    </td>
                    <td
                        class="text-right"
                        :class="embedded ? 'py-2.5' : 'py-4'"
                    >
                        {{ item.quantity }}
                    </td>
                    <td
                        class="text-right"
                        :class="embedded ? 'py-2.5' : 'py-4'"
                    >
                        {{ money(item.unit_price_minor) }}
                    </td>
                    <td
                        class="text-right font-medium"
                        :class="embedded ? 'py-2.5' : 'py-4'"
                    >
                        {{
                            money(
                                Math.round(
                                    item.quantity * item.unit_price_minor,
                                ),
                            )
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
        <div
            class="ml-auto max-w-xs space-y-2 text-sm"
            :class="embedded ? 'mt-4' : 'mt-6'"
        >
            <div class="flex justify-between">
                <span class="text-muted-foreground">Subtotal</span
                ><span>{{ money(proposal.subtotal_minor) }}</span>
            </div>
            <div v-if="proposal.discount_minor" class="flex justify-between">
                <span class="text-muted-foreground">Discount</span
                ><span>−{{ money(proposal.discount_minor) }}</span>
            </div>
            <div v-if="proposal.tax_minor" class="flex justify-between">
                <span class="text-muted-foreground"
                    >Tax ({{ proposal.tax_rate_basis_points / 100 }}%)</span
                ><span>{{ money(proposal.tax_minor) }}</span>
            </div>
            <div
                class="flex justify-between border-t pt-3 text-lg font-semibold"
            >
                <span>Total</span><span>{{ money(proposal.total_minor) }}</span>
            </div>
        </div>
    </section>
</template>
