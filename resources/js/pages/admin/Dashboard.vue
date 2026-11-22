<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Activity, ArrowRight, DatabaseBackup, Users, Wallet } from '@lucide/vue';
import FinanceSummaryCard from '@/components/FinanceSummaryCard.vue';
import { dashboard } from '@/routes/admin';
import { index as indexMcpUsage } from '@/routes/admin/mcp/usage';
import { index as indexUsers } from '@/routes/admin/users';
import { index as contactIndex } from '@/routes/admin/contact';
import { index as backupIndex } from '@/routes/drivesnap';

const props = defineProps<{
    financeSummary?: any;
    mcpStats?: {
        total_calls: number;
        calls_today: number;
        daily: Array<{ date: string; count: number }>;
    } | null;
    platformStats?: {
        total_users: number;
        total_admins: number;
        verified_users: number;
        passkey_count: number;
    } | null;
    recentActivity?: Array<{ id: number; action: string; description: string | null; created_at: string }> | null;
    contacts?: { unread: number; latest: Array<{ id: number; name: string; subject: string | null; created_at: string }> } | null;
    proposals?: { this_month: number; awaiting_response: number } | null;
    people?: { active: number; present_today: number; pending_leave: number } | null;
    backupOverview?: { latest: { name: string; date: string; status: string; location: string | null } | null } | null;
}>();

const mcpRange = ref<7 | 14>(14);
const selectedMcpDate = ref<string | null>(null);
const mcpDays = computed(() => props.mcpStats?.daily?.slice(-mcpRange.value) ?? []);
const mcpPeak = computed(() => Math.max(0, ...mcpDays.value.map(day => day.count)));
const mcpPeriodTotal = computed(() => mcpDays.value.reduce((total, day) => total + day.count, 0));
const selectedMcpDay = computed(() => mcpDays.value.find(day => day.date === selectedMcpDate.value) ?? mcpDays.value.at(-1));
const mcpAverage = computed(() => mcpDays.value.length ? (mcpPeriodTotal.value / mcpDays.value.length).toFixed(1) : '0');
const mcpDateLabel = (date: string): string => new Date(date + 'T12:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
const dateLabel = (date: string): string => new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

function formatNumber(num: number): string {
    return new Intl.NumberFormat('en-US').format(num);
}
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 md:p-6">
        <!-- Top Cards Grid: 3 Clean, Simple Cards -->
        <div class="grid gap-4 md:grid-cols-3">
            <!-- Card 1: Finance (1 big number + 2 simple metrics) -->
            <FinanceSummaryCard
                v-if="financeSummary"
                :summary="financeSummary"
                class="h-full"
            />
            <div
                v-else
                class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-6 shadow-xs"
            >
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground"
                            >Net Cash Flow</span
                        >
                        <Wallet class="size-4 text-muted-foreground" />
                    </div>
                    <div
                        class="mt-3 text-2xl font-bold tracking-tight text-muted-foreground"
                    >
                        --
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        No financial data available
                    </p>
                </div>
            </div>

            <!-- Card 2: MCP Tool Calls (1 big number + 2 simple metrics) -->
            <div
                v-if="mcpStats"
                class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-6 shadow-xs transition-colors hover:border-border"
            >
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground"
                            >MCP Tool Calls</span
                        >
                        <div
                            class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Activity class="size-4" />
                        </div>
                    </div>

                    <div
                        class="mt-3 text-3xl font-bold tracking-tight text-foreground"
                    >
                        {{ formatNumber(mcpStats.total_calls) }}
                    </div>

                    <div
                        class="mt-2 flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span
                            class="inline-flex items-center font-medium text-emerald-500"
                        >
                            <span
                                class="mr-1.5 size-1.5 rounded-full bg-emerald-500"
                            ></span>
                            Usage recorded
                        </span>
                        <span>•</span>
                        <span>{{ mcpStats.calls_today }} calls today</span>
                    </div>
                </div>

                <div class="mt-5 border-t border-border/50 pt-3">
                    <Link
                        :href="indexMcpUsage()"
                        class="group inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                    >
                        <span>View Usage Logs</span>
                        <ArrowRight
                            class="size-3.5 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                </div>
            </div>

            <!-- Card 3: Registered Users (1 big number + 2 simple metrics) -->
            <div
                v-if="platformStats"
                class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-6 shadow-xs transition-colors hover:border-border"
            >
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground"
                            >Registered Users</span
                        >
                        <div
                            class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Users class="size-4" />
                        </div>
                    </div>

                    <div
                        class="mt-3 text-3xl font-bold tracking-tight text-foreground"
                    >
                        {{ formatNumber(platformStats.total_users) }}
                    </div>

                    <div
                        class="mt-2 flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span class="font-medium text-foreground">
                            {{ platformStats.total_admins }} admin
                        </span>
                        <span>•</span>
                        <span>{{ platformStats.verified_users }} verified</span>
                    </div>
                </div>

                <div class="mt-5 border-t border-border/50 pt-3">
                    <Link
                        :href="indexUsers()"
                        class="group inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                    >
                        <span>Manage Users</span>
                        <ArrowRight
                            class="size-3.5 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                </div>
            </div>
        </div>

        <div v-if="recentActivity || backupOverview" class="grid items-start gap-3 lg:grid-cols-3">
            <section v-if="recentActivity" class="min-w-0 overflow-hidden rounded-lg border bg-card lg:col-span-2">
                <div class="flex items-center justify-between border-b px-3 py-2.5">
                    <h2 class="text-xs font-semibold">Activity monitor</h2>
                    <span class="text-[11px] text-muted-foreground">Latest 10 activities</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <caption class="sr-only">Latest ten recorded activities, newest first</caption>
                        <thead class="bg-muted/30 text-muted-foreground"><tr><th scope="col" class="px-3 py-2 font-medium">Time</th><th scope="col" class="px-3 py-2 font-medium">Action</th><th scope="col" class="px-3 py-2 font-medium">Details</th></tr></thead>
                        <tbody class="divide-y">
                            <tr v-for="event in recentActivity" :key="event.id" class="hover:bg-muted/20">
                                <td class="px-3 py-2 whitespace-nowrap text-muted-foreground"><time :datetime="event.created_at">{{ new Date(event.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</time></td>
                                <td class="px-3 py-2"><span class="block max-w-48 truncate font-medium" :title="event.action">{{ event.action.replace(/[._]+/g, ' ') }}</span></td>
                                <td class="px-3 py-2"><span class="block max-w-64 truncate text-muted-foreground" :title="event.description ?? undefined">{{ event.description || '—' }}</span></td>
                            </tr>
                            <tr v-if="!recentActivity.length"><td colspan="3" class="px-3 py-5 text-center text-muted-foreground">No activity recorded yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section v-if="backupOverview" class="flex min-w-0 flex-col justify-between rounded-xl border border-border/80 bg-card p-6 shadow-xs transition-colors hover:border-border">
                <div>
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-medium text-muted-foreground">Latest backup</h2>
                        <div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <DatabaseBackup class="size-4" />
                        </div>
                    </div>
                    <template v-if="backupOverview.latest">
                        <p class="mt-3 truncate text-lg font-bold tracking-tight text-foreground" :title="backupOverview.latest.name">{{ backupOverview.latest.name }}</p>
                        <time class="mt-2 block text-xs text-muted-foreground" :datetime="backupOverview.latest.date">{{ new Date(backupOverview.latest.date).toLocaleString() }}</time>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <Badge variant="outline">{{ backupOverview.latest.status }}</Badge>
                            <Badge v-if="backupOverview.latest.location" variant="secondary" title="Local file checked; Drive location reflects the recorded upload">{{ backupOverview.latest.location === 'Both' ? 'Local + Drive' : backupOverview.latest.location }}</Badge>
                        </div>
                    </template>
                    <p v-else class="mt-3 text-sm text-muted-foreground">No backups yet.</p>
                </div>
                <div class="mt-5 border-t border-border/50 pt-3">
                    <Link :href="backupIndex()" class="group inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline">
                        <span>View Backups</span>
                        <ArrowRight class="size-3.5 transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </div>
            </section>
        </div>

        <div class="grid items-start gap-4 lg:grid-cols-2">
            <section v-if="mcpStats" class="min-w-0 rounded-xl border border-border/80 bg-card p-6 shadow-xs">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">MCP usage</h2>
                        <p class="mt-1 text-xs text-muted-foreground">How often your AI tools are being used.</p>
                    </div>
                    <div class="flex rounded-lg border p-1" role="group" aria-label="MCP usage period">
                        <button v-for="days in ([7, 14] as const)" :key="days" type="button" :aria-pressed="mcpRange === days" class="rounded-md px-3 py-1.5 text-xs font-medium focus-visible:outline-2 focus-visible:outline-ring" :class="mcpRange === days ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground'" @click="mcpRange = days">
                            {{ days }} days
                        </button>
                    </div>
                </div>
                <dl class="mt-5 grid grid-cols-3 gap-3 border-y py-4">
                    <div><dt class="text-xs text-muted-foreground">Total calls</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ formatNumber(mcpPeriodTotal) }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Daily average</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ mcpAverage }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Today</dt><dd class="mt-1 text-xl font-semibold tabular-nums">{{ formatNumber(mcpStats.calls_today) }}</dd></div>
                </dl>
                <div v-if="mcpPeriodTotal" class="mt-4">
                    <p v-if="selectedMcpDay" class="mb-3 text-xs text-muted-foreground" aria-live="polite">{{ mcpDateLabel(selectedMcpDay.date) }} · <span class="font-medium text-foreground">{{ selectedMcpDay.count }} {{ selectedMcpDay.count === 1 ? 'call' : 'calls' }}</span></p>
                    <div class="flex gap-3">
                        <div class="flex h-36 shrink-0 flex-col justify-between text-[10px] text-muted-foreground" aria-hidden="true"><span>{{ mcpPeak }}</span><span>0</span></div>
                        <div class="flex h-36 min-w-0 flex-1 items-end gap-1 border-b" role="group" aria-label="Daily MCP calls. Select a day to see its count.">
                            <button v-for="day in mcpDays" :key="day.date" type="button" class="flex h-full min-w-0 flex-1 items-end rounded-t-sm focus-visible:outline-2 focus-visible:outline-ring" :aria-label="mcpDateLabel(day.date) + ': ' + day.count + ' calls'" :aria-pressed="selectedMcpDay?.date === day.date" @click="selectedMcpDate = day.date" @focus="selectedMcpDate = day.date" @mouseenter="selectedMcpDate = day.date">
                                <span class="w-full rounded-t-sm transition-colors" :class="selectedMcpDay?.date === day.date ? 'bg-primary' : day.count ? 'bg-primary/50 hover:bg-primary/75' : 'bg-muted'" :style="{ height: day.count / Math.max(1, mcpPeak) * 100 + '%', minHeight: '3px' }"></span>
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="mt-4 flex h-36 flex-col items-center justify-center gap-2 rounded-lg bg-muted/30 px-4 text-center">
                    <Activity class="size-5 text-muted-foreground" />
                    <p class="text-sm font-medium">No tool calls in this period</p>
                    <p class="text-xs text-muted-foreground">Calls will appear here when a connected AI client uses your MCP tools.</p>
                </div>
                <div v-if="mcpDays.length" class="mt-2 flex justify-between text-xs text-muted-foreground"><span>{{ mcpDateLabel(mcpDays[0].date) }}</span><span>{{ mcpDateLabel(mcpDays[mcpDays.length - 1].date) }}</span></div>
                <div class="mt-4 flex flex-wrap items-start justify-between gap-3 border-t pt-3">
                    <Link :href="indexMcpUsage()" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">Explore usage logs <ArrowRight class="size-3" /></Link>
                </div>
            </section>

            <section v-if="contacts" class="min-w-0 rounded-xl border border-border/80 bg-card p-6 shadow-xs">
                <div class="flex items-center justify-between gap-3"><h2 class="text-base font-semibold">Latest contacts</h2><Link :href="contactIndex()" class="text-xs text-primary hover:underline">Open inbox</Link></div>
                <ul v-if="contacts.latest.length" class="mt-4 divide-y">
                    <li v-for="contact in contacts.latest" :key="contact.id" class="py-3 first:pt-0">
                        <div class="flex items-start justify-between gap-3"><p class="min-w-0 break-words text-sm font-medium">{{ contact.name }}</p><time class="shrink-0 text-xs text-muted-foreground" :datetime="contact.created_at">{{ dateLabel(contact.created_at) }}</time></div>
                        <p class="mt-1 truncate text-xs text-muted-foreground">{{ contact.subject || 'Website enquiry' }}</p>
                    </li>
                </ul>
                <p v-else class="py-10 text-sm text-muted-foreground">New customer enquiries will appear here.</p>
            </section>


        </div>

    </div>
</template>
