<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { store as storeToken, destroy as destroyToken } from '@/routes/admin/mcp/tokens';
import { useClipboard } from '@vueuse/core';
import { toast } from 'vue-sonner';
import { formatDistanceToNow } from 'date-fns';
import { 
    Activity, 
    Bot, 
    Cpu, 
    Database, 
    Server, 
    ShieldCheck, 
    ArrowRight, 
    Zap, 
    BarChart3, 
    Terminal, 
    FileText, 
    Users, 
    Radio,
    Sparkles
} from '@lucide/vue';

const props = defineProps<{
    tokens: Array<{
        id: string | number;
        name: string;
        last_used_at: string | null;
        created_at: string;
    }>;
    serverUrl: string;
    activityChart?: Array<{
        day: string;
        date: string;
        requests: number;
    }>;
    stats?: {
        totalRequests: number;
        uniqueClients: number;
        activeTokensCount: number;
    };
}>();

const form = useForm({
    name: '',
});

const page = usePage();
const flashToken = computed(() => page.props.flash?.token as string | undefined);
const flashMessage = computed(() => page.props.flash?.message as string | undefined);

const { copy } = useClipboard();

const copyToClipboard = (text: string, message = 'Copied to clipboard!') => {
    copy(text);
    toast.success(message);
};

const maxRequests = computed(() => {
    if (!props.activityChart || props.activityChart.length === 0) return 10;
    const max = Math.max(...props.activityChart.map(item => item.requests));
    return max > 0 ? max : 10;
});

const createToken = () => {
    form.post(storeToken(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (flashMessage.value) {
                toast.success(flashMessage.value);
            }
        },
    });
};

const revokeToken = (id: string | number) => {
    if (confirm('Are you sure you want to revoke this API token? Any AI tools using this token will lose access immediately.')) {
        router.delete(destroyToken({ tokenId: id.toString() }), {
            preserveScroll: true,
            onSuccess: () => toast.success('Token revoked successfully.'),
        });
    }
};
</script>

<template>
    <Head title="MCP AI Tools Settings" />

    <div class="space-y-6">
        <!-- Server Connection Overview -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Model Context Protocol (MCP) Server</CardTitle>
                    <Badge variant="outline" class="gap-1.5 py-1 px-2.5 text-xs text-emerald-500 border-emerald-500/30 bg-emerald-500/10">
                        <Radio class="size-3 animate-pulse text-emerald-500" />
                        SSE Server Online
                    </Badge>
                </div>
                <CardDescription>
                    Connect AI agents like Claude Desktop or Cursor to your ValenceKit application.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label>Server URL</Label>
                        <div class="flex items-center gap-2">
                            <Input :model-value="serverUrl" readonly />
                            <Button variant="secondary" @click="copyToClipboard(serverUrl, 'Server URL copied!')">
                                Copy
                            </Button>
                        </div>
                        <p class="text-sm text-muted-foreground mt-2">
                            Use this URL when configuring an SSE (Server-Sent Events) MCP connection in your AI client. You will also need to provide a Bearer API token generated below.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Visual Architecture & Communication Flow Diagram -->
        <Card class="overflow-hidden border-border/80">
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between">
                    <div class="space-y-1">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Cpu class="size-4 text-primary" />
                            MCP Architecture & Communication Flow
                        </CardTitle>
                        <CardDescription>
                            Visual topology of how AI assistants securely authenticate and invoke ValenceKit capabilities.
                        </CardDescription>
                    </div>
                    <Badge variant="secondary" class="font-mono text-xs">JSON-RPC 2.0</Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div class="rounded-xl border border-border/60 bg-muted/30 p-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        
                        <!-- Client Layer -->
                        <div class="flex flex-col gap-3 p-4 rounded-lg border bg-card/80 shadow-xs relative">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">AI Client Layer</span>
                                <Bot class="size-4 text-primary" />
                            </div>
                            <div class="space-y-1.5">
                                <div class="text-sm font-medium">Cursor / Claude / Windsurf</div>
                                <div class="text-xs text-muted-foreground">Prompt & context orchestration via MCP protocol</div>
                            </div>
                            <div class="pt-2 border-t flex flex-wrap gap-1">
                                <span class="text-[10px] px-2 py-0.5 rounded bg-muted font-mono">Claude Desktop</span>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-muted font-mono">Cursor AI</span>
                            </div>
                        </div>

                        <!-- Transport & Gateway -->
                        <div class="flex flex-col gap-3 p-4 rounded-lg border border-primary/30 bg-primary/5 shadow-xs relative">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-primary">Transport & Security</span>
                                <ShieldCheck class="size-4 text-primary" />
                            </div>
                            <div class="space-y-1.5">
                                <div class="text-sm font-medium">SSE Stream + Bearer Auth</div>
                                <div class="text-xs text-muted-foreground font-mono">POST /mcp/valencekit</div>
                            </div>
                            <div class="pt-2 border-t border-primary/20 flex flex-wrap gap-1">
                                <span class="text-[10px] px-2 py-0.5 rounded bg-primary/10 text-primary font-mono font-medium">Bearer Sanctum Token</span>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-primary/10 text-primary font-mono">Audit Log</span>
                            </div>
                        </div>

                        <!-- Backend Capabilities -->
                        <div class="flex flex-col gap-3 p-4 rounded-lg border bg-card/80 shadow-xs relative">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">ValenceKit Engine</span>
                                <Server class="size-4 text-emerald-500" />
                            </div>
                            <div class="space-y-1.5">
                                <div class="text-sm font-medium">Tools & Resource Provider</div>
                                <div class="text-xs text-muted-foreground">Direct access to models, permissions & logs</div>
                            </div>
                            <div class="pt-2 border-t flex flex-wrap gap-1">
                                <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-500 font-mono">get-user-roles</span>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-blue-500/10 text-blue-500 font-mono">system-logs</span>
                            </div>
                        </div>

                    </div>

                    <!-- Flow Steps Sequence -->
                    <div class="mt-4 pt-4 border-t border-border/50 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-muted-foreground">
                        <div class="flex items-center gap-2">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold text-[10px]">1</span>
                            <span>Client handshakes via SSE stream</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold text-[10px]">2</span>
                            <span>Token verified against admin permissions</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary font-semibold text-[10px]">3</span>
                            <span>Tool execution logged & streamed back</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Visual Usage & Activity Chart -->
        <Card>
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between">
                    <div class="space-y-1">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <BarChart3 class="size-4 text-primary" />
                            MCP Activity & Invocations Chart
                        </CardTitle>
                        <CardDescription>
                            Visualizing daily AI agent requests and tool invocations over the past 7 days.
                        </CardDescription>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right hidden sm:block">
                            <div class="text-xs text-muted-foreground">Total Invocations</div>
                            <div class="text-lg font-bold">{{ stats?.totalRequests ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Stat Pills -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="p-3 rounded-lg border bg-muted/20">
                        <div class="text-xs text-muted-foreground">Total Invocations</div>
                        <div class="text-xl font-bold mt-1">{{ stats?.totalRequests ?? 0 }}</div>
                    </div>
                    <div class="p-3 rounded-lg border bg-muted/20">
                        <div class="text-xs text-muted-foreground">Active Tokens</div>
                        <div class="text-xl font-bold mt-1">{{ stats?.activeTokensCount ?? tokens.length }}</div>
                    </div>
                    <div class="p-3 rounded-lg border bg-muted/20">
                        <div class="text-xs text-muted-foreground">Unique Clients</div>
                        <div class="text-xl font-bold mt-1">{{ stats?.uniqueClients ?? 1 }}</div>
                    </div>
                    <div class="p-3 rounded-lg border bg-muted/20">
                        <div class="text-xs text-muted-foreground">Protocol Spec</div>
                        <div class="text-xl font-bold mt-1 font-mono text-primary text-base">v2024-11-05</div>
                    </div>
                </div>

                <!-- SVG Activity Bar Chart -->
                <div class="rounded-xl border bg-muted/20 p-4">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Requests Trend (Past 7 Days)</span>
                        <span class="text-xs text-muted-foreground">Peak: {{ maxRequests }} reqs/day</span>
                    </div>

                    <div class="h-44 flex items-end gap-3 sm:gap-6 pt-6 pb-2 px-2 border-b border-border/60">
                        <div 
                            v-for="(item, idx) in activityChart" 
                            :key="idx" 
                            class="flex-1 flex flex-col items-center gap-2 h-full justify-end group relative"
                        >
                            <!-- Tooltip -->
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity absolute -top-8 bg-foreground text-background text-[11px] py-0.5 px-2 rounded font-mono shadow-md pointer-events-none whitespace-nowrap z-10">
                                {{ item.date }}: {{ item.requests }} requests
                            </div>

                            <!-- Bar -->
                            <div 
                                class="w-full max-w-[42px] rounded-t-md transition-all duration-500 ease-out relative overflow-hidden"
                                :class="item.requests > 0 ? 'bg-primary hover:bg-primary/90' : 'bg-muted-foreground/20'"
                                :style="{ height: Math.max(8, (item.requests / maxRequests) * 100) + '%' }"
                            >
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                            </div>

                            <!-- Value Label on Bar Top -->
                            <span class="text-[10px] font-mono text-muted-foreground">
                                {{ item.requests }}
                            </span>
                        </div>
                    </div>

                    <!-- X-Axis Labels -->
                    <div class="flex justify-between gap-3 sm:gap-6 px-2 mt-2">
                        <div 
                            v-for="(item, idx) in activityChart" 
                            :key="'label-' + idx" 
                            class="flex-1 text-center"
                        >
                            <span class="text-xs font-medium text-foreground block">{{ item.day }}</span>
                            <span class="text-[10px] text-muted-foreground block">{{ item.date }}</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Visual Capabilities Cards -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Available Capabilities</CardTitle>
                        <CardDescription>
                            This MCP server exposes the following tools and resources to your AI assistant:
                        </CardDescription>
                    </div>
                    <Badge variant="outline">2 Active Endpoints</Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Tool Card -->
                    <div class="p-4 rounded-xl border bg-muted/20 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Users class="size-4 text-emerald-500" />
                                <span class="font-semibold text-sm">get-user-roles</span>
                            </div>
                            <Badge class="bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500/20 border-emerald-500/30">Tool</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Queries and inspects assigned Spatie roles and granular permissions for any user in ValenceKit.
                        </p>
                        <div class="text-[11px] font-mono bg-background p-2 rounded border text-muted-foreground">
                            Parameters: <span class="text-foreground font-semibold">userId</span> (required: integer)
                        </div>
                    </div>

                    <!-- Resource Card -->
                    <div class="p-4 rounded-xl border bg-muted/20 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <FileText class="size-4 text-blue-500" />
                                <span class="font-semibold text-sm">system-logs</span>
                            </div>
                            <Badge class="bg-blue-500/10 text-blue-500 hover:bg-blue-500/20 border-blue-500/30">Resource</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Live telemetry and application runtime logs (`storage/logs/laravel.log`) for real-time debugging.
                        </p>
                        <div class="text-[11px] font-mono bg-background p-2 rounded border text-muted-foreground">
                            URI: <span class="text-foreground font-semibold">valencekit://system-logs</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

            <Card>
                <CardHeader>
                    <CardTitle>How to Connect</CardTitle>
                    <CardDescription>
                        Follow these steps to connect your AI assistant (Claude Desktop, Cursor, etc.) to this application.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 text-sm">
                    <ol class="list-decimal list-inside space-y-4">
                        <li>
                            <strong class="text-foreground">Generate a Token:</strong> Use the form below to create a new API token. When the green box appears, copy the long string. That exact string replaces <code>YOUR_TOKEN_HERE</code> in the Authorization header.
                        </li>
                        <li>
                            <strong class="text-foreground">Configure your AI Client:</strong> Open your AI tool's MCP configuration file (e.g., <code>claude_desktop_config.json</code>).
                        </li>
                        <li>
                            <strong class="text-foreground">Add the Server:</strong> Paste the following configuration into your MCP client (this connects via SSE):
                            
                            <div class="mt-4 space-y-4">
                                <div>
                                    <div class="p-4 bg-muted rounded-md overflow-x-auto">
                                        <pre><code>{
  "mcpServers": {
    "valencekit": {
      "type": "sse",
      "url": "{{ serverUrl }}",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN_HERE"
      }
    }
  }
}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Create API Token</CardTitle>
                    <CardDescription>
                        Generate a new API token to authenticate an AI client.
                    </CardDescription>
                </CardHeader>
                <form @submit.prevent="createToken">
                    <CardContent class="space-y-4">
                        <div v-if="flashToken" class="p-4 bg-muted border border-primary/20 rounded-md">
                            <h4 class="font-medium mb-2 text-primary">New API Token Generated</h4>
                            <p class="text-sm text-muted-foreground mb-4">
                                {{ flashMessage }}
                            </p>
                            <div class="flex items-center gap-2">
                                <Input :model-value="flashToken" readonly class="font-mono text-sm bg-background" />
                                <Button type="button" @click="copyToClipboard(flashToken, 'API Token copied!')">
                                    Copy Token
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="name">Token Name</Label>
                            <div class="flex items-center gap-3">
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="e.g., Claude Desktop, Cursor AI"
                                    :disabled="form.processing"
                                    class="max-w-md"
                                />
                                <Button type="submit" :disabled="form.processing || !form.name.trim()">
                                    {{ form.processing ? 'Generating...' : 'Create Token' }}
                                </Button>
                            </div>
                            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                        </div>
                    </CardContent>
                </form>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Active API Tokens</CardTitle>
                    <CardDescription>
                        Manage your active API tokens. Revoke tokens that are no longer needed.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table v-if="tokens.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Last Used</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="token in tokens" :key="token.id">
                                <TableCell class="font-medium">{{ token.name }}</TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ token.last_used_at ? formatDistanceToNow(new Date(token.last_used_at), { addSuffix: true }) : 'Never' }}
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ formatDistanceToNow(new Date(token.created_at), { addSuffix: true }) }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button variant="destructive" size="sm" @click="revokeToken(token.id)">
                                        Revoke
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="text-center py-6 text-muted-foreground">
                        You have not generated any API tokens yet.
                    </div>
                </CardContent>
            </Card>
        </div>
</template>
