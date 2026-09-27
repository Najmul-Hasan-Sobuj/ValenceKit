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
    mcpTools: Array<{
        id: number;
        name: string;
        description: string;
        is_active: boolean;
    }>;
}>();

import { Switch } from '@/components/ui/switch';
import { update } from '@/actions/App/Http/Controllers/Admin/Settings/McpController';

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
                <CardTitle>Manage MCP Tools</CardTitle>
                <CardDescription>
                    Enable or disable AI tools for the workspace dynamically.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-col gap-4">
                    <div 
                        v-for="tool in mcpTools" 
                        :key="tool.id" 
                        class="flex items-center justify-between py-4 border-b border-gray-200 dark:border-gray-700 last:border-0"
                    >
                        <div class="flex flex-col gap-1">
                            <h3 class="font-medium text-gray-900 dark:text-white">
                                {{ tool.name }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ tool.description }}
                            </p>
                        </div>
                        
                        <form @submit.prevent="router.patch(update.url(tool.id), { is_active: !tool.is_active }, { preserveScroll: true })">
                            <Switch 
                                :checked="tool.is_active"
                                @update:checked="$event => router.patch(update.url(tool.id), { is_active: $event }, { preserveScroll: true, onSuccess: () => toast.success('Tool status updated.') })" 
                            />
                        </form>
                    </div>
                </div>
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
