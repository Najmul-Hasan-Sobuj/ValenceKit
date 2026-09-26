<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import Heading from '@/components/Heading.vue'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Blobatar } from '@blobatar/vue'
import RelativeTime from '@/components/RelativeTime.vue'
import { Button } from '@/components/ui/button'
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart'
import { VisXYContainer, VisStackedBar, VisAxis, VisCrosshair } from '@unovis/vue'
import { computed } from 'vue'
import { index as indexMcpUsage } from '@/routes/admin/mcp/usage'
import { BarChart3, Activity, Users, Radio } from '@lucide/vue'

const props = defineProps<{
  logs: {
    data: Array<{
      id: number
      token_used: string
      ip_address: string
      user_agent: string
      endpoint: string
      created_at: string
      user: {
        id: number
        name: string
        email: string
      } | null
    }>
    links: any[]
    from?: number
    to?: number
    total?: number
  }
  activityChart?: Array<{
    day: string
    date: string
    requests: number
  }>
  stats?: {
    totalRequests: number
    uniqueClients: number
    todayRequests: number
  }
}>()

const maxRequests = computed(() => {
  if (!props.activityChart || props.activityChart.length === 0) return 10
  const max = Math.max(...props.activityChart.map(item => item.requests))
  return max > 0 ? max : 10
})

const formattedLogs = computed(() => {
  return props.logs.data
})

const chartConfig = {
  requests: {
    label: 'Requests',
    color: 'hsl(var(--primary))'
  }
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'MCP Usage Logs', href: indexMcpUsage() }] },
});
</script>

<template>
  <Head title="MCP Usage Logs" />

  <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <Heading
          variant="small"
          title="MCP Usage Logs"
          description="Monitor who is connecting to your MCP server and using AI tools."
      />
      <Badge variant="outline" class="gap-1.5 py-1 px-2.5 text-xs text-emerald-500 border-emerald-500/30 bg-emerald-500/10">
        <Radio class="size-3 animate-pulse text-emerald-500" />
        Live Logging Active
      </Badge>
    </div>

    <!-- Usage Activity Chart & Stats -->
    <Card class="overflow-hidden border-border/80">
      <CardHeader class="pb-3">
        <div class="flex items-center justify-between">
          <div class="space-y-1">
            <CardTitle class="flex items-center gap-2 text-base">
              <BarChart3 class="size-4 text-primary" />
              Invocations & Traffic (Past 7 Days)
            </CardTitle>
            <CardDescription>
              Volume of MCP requests received from AI agents (Cursor, Claude Desktop).
            </CardDescription>
          </div>
          <div class="text-right hidden sm:block">
            <div class="text-xs text-muted-foreground">Total Invocations</div>
            <div class="text-lg font-bold">{{ stats?.totalRequests ?? logs.data.length }}</div>
          </div>
        </div>
      </CardHeader>
      <CardContent class="space-y-6">
        <!-- Quick Stats -->
        <div class="grid grid-cols-3 gap-3">
          <div class="p-3 rounded-lg border bg-muted/20">
            <div class="text-xs text-muted-foreground">Total Requests</div>
            <div class="text-xl font-bold mt-1">{{ stats?.totalRequests ?? logs.data.length }}</div>
          </div>
          <div class="p-3 rounded-lg border bg-muted/20">
            <div class="text-xs text-muted-foreground">Unique Clients</div>
            <div class="text-xl font-bold mt-1">{{ stats?.uniqueClients ?? 1 }}</div>
          </div>
          <div class="p-3 rounded-lg border bg-muted/20">
            <div class="text-xs text-muted-foreground">Today's Volume</div>
            <div class="text-xl font-bold mt-1 text-primary">{{ stats?.todayRequests ?? 0 }}</div>
          </div>
        </div>

        <!-- Usage Activity Bar Chart (Shadcn Vue / Unovis) -->
        <div class="rounded-xl border bg-muted/20 p-4">
          <div class="flex items-center justify-between mb-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Daily Request Distribution</span>
            <span class="text-xs text-muted-foreground">Peak: {{ maxRequests }} reqs/day</span>
          </div>
          
          <ChartContainer :config="chartConfig" class="h-48 w-full mt-4">
            <VisXYContainer :data="activityChart" :margin="{ left: 0, right: 0, top: 0, bottom: 0 }">
              <VisStackedBar
                :x="(d, i) => i"
                :y="[(d) => d.requests]"
                :color="() => 'var(--color-requests)'"
                :roundedCorners="4"
                :barPadding="0.15"
              />
              <VisAxis 
                type="x" 
                :tickFormat="(i) => activityChart[i]?.day" 
                :gridLine="false"
                :domainLine="false"
                :tickLine="false"
              />
              <VisCrosshair :color="() => 'var(--color-requests)'" />
              <VisTooltip :customComponent="ChartTooltipContent" />
            </VisXYContainer>
          </ChartContainer>
        </div>
      </CardContent>
    </Card>

    <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead class="pl-4">User / Token</TableHead>
            <TableHead>Endpoint</TableHead>
            <TableHead>IP Address</TableHead>
            <TableHead>Time</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="log in formattedLogs" :key="log.id">
            <TableCell class="pl-4">
              <div class="flex items-center gap-3">
                <div class="relative shrink-0">
                  <Avatar class="size-9 rounded-lg overflow-hidden bg-muted/40 ring-1 ring-border/50">
                    <AvatarImage
                        v-if="log.user?.avatar"
                        :src="log.user.avatar"
                        :alt="log.user.name"
                    />
                    <Blobatar
                        v-else
                        :name="log.user ? log.user.email : log.token_used"
                        :size="36"
                        animate="hover"
                        class="size-full object-cover"
                    />
                  </Avatar>
                </div>
                <div>
                  <div class="font-medium">{{ log.user ? log.user.name : 'Unknown User' }}</div>
                  <div class="text-xs text-muted-foreground break-all" v-if="!log.user || log.token_used">{{ log.token_used }}</div>
                </div>
              </div>
            </TableCell>
            <TableCell>
              <Badge variant="secondary" class="font-mono text-xs">{{ log.endpoint }}</Badge>
            </TableCell>
            <TableCell>
              <span class="text-sm font-mono text-muted-foreground">{{ log.ip_address }}</span>
            </TableCell>
            <TableCell class="text-muted-foreground text-sm">
              <RelativeTime :date="log.created_at" fallback="Unknown" />
            </TableCell>
          </TableRow>
          <TableRow v-if="formattedLogs.length === 0">
            <TableCell colspan="4" class="h-24 text-center text-muted-foreground">
              No usage logs found.
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Pagination -->
    <div class="flex flex-col justify-between gap-3 text-sm sm:flex-row sm:items-center">
        <p class="text-muted-foreground">Showing {{ logs.from || 0 }} to {{ logs.to || 0 }} of {{ logs.total || 0 }} requests</p>
        <div class="flex items-center gap-1">
            <Button v-for="link in logs.links" :key="link.label" variant="outline" size="sm" :disabled="!link.url" :class="{ 'bg-primary/10 text-primary border-primary/20': link.active }" @click="link.url ? router.get(link.url) : null">
                <span v-html="link.label" />
            </Button>
        </div>
    </div>
  </div>
</template>
