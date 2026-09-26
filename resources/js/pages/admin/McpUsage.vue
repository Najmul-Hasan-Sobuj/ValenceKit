<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import Heading from '@/components/Heading.vue'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { formatDistanceToNow } from 'date-fns'
import { computed } from 'vue'
import { index as indexMcpUsage } from '@/routes/admin/mcp/usage'

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
    meta: any
  }
}>()

const formattedLogs = computed(() => {
  return props.logs.data.map(log => {
    return {
      ...log,
      time_ago: log.created_at ? formatDistanceToNow(new Date(log.created_at), { addSuffix: true }) : 'Unknown',
    }
  })
})

defineOptions({
    layout: { breadcrumbs: [{ title: 'MCP Usage Logs', href: indexMcpUsage() }] },
});
</script>

<template>
  <Head title="MCP Usage Logs" />

  <div class="flex flex-col space-y-6">
    <Heading
        variant="small"
        title="MCP Usage Logs"
        description="Monitor who is connecting to your MCP server and using AI tools."
    />

    <div class="rounded-xl border border-sidebar-border/80 bg-card">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>User / Token</TableHead>
            <TableHead>Endpoint</TableHead>
            <TableHead>IP Address</TableHead>
            <TableHead>Time</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="log in formattedLogs" :key="log.id">
            <TableCell>
              <div class="flex flex-col">
                <span class="font-medium">{{ log.user ? log.user.name : 'Unknown User' }}</span>
                <span class="text-xs text-muted-foreground">{{ log.token_used }}</span>
              </div>
            </TableCell>
            <TableCell>
              <span class="text-xs break-all text-muted-foreground">{{ log.endpoint }}</span>
            </TableCell>
            <TableCell>
              <span class="text-sm font-mono text-muted-foreground">{{ log.ip_address }}</span>
            </TableCell>
            <TableCell>
              <span class="text-sm text-muted-foreground">{{ log.time_ago }}</span>
            </TableCell>
          </TableRow>
          <TableRow v-if="formattedLogs.length === 0">
            <TableCell colspan="4" class="py-8 text-center text-muted-foreground">
              No usage logs found.
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </div>
</template>
