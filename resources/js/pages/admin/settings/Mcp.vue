<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    CardFooter,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    store as storeToken,
    destroy as destroyToken,
} from '@/routes/admin/mcp/tokens';
import { sync as syncTools } from '@/routes/admin/mcp/tools';
import { useClipboard } from '@vueuse/core';
import { toast } from 'vue-sonner';
import RelativeTime from '@/components/RelativeTime.vue';
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
    Sparkles,
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
        slug: string | null;
        version: string;
        provider: string | null;
        transport: string;
        required_scope: string;
        health_status: string;
        last_synced_at: string | null;
        description: string;
        is_active: boolean;
    }>;
    oauthDiscoveryUrl: string;
    oauthScopes: Record<string, string>;
}>();

import { Switch } from '@/components/ui/switch';
import { update } from '@/actions/App/Http/Controllers/Admin/Settings/McpController';

const form = useForm({
    name: '',
});

const page = usePage<{ flash?: { token?: string; message?: string } }>();
const flashToken = computed(() => page.props.flash?.token);
const flashMessage = computed(() => page.props.flash?.message);

const { copy } = useClipboard();

const copyToClipboard = (text: string, message = 'Copied to clipboard!') => {
    copy(text);
    toast.success(message);
};

const maxRequests = computed(() => {
    if (!props.activityChart || props.activityChart.length === 0) return 10;
    const max = Math.max(...props.activityChart.map((item) => item.requests));
    return max > 0 ? max : 10;
});

const createToken = () => {
    form.post(storeToken.url(), {
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
    if (
        confirm(
            'Are you sure you want to revoke this API token? Any AI tools using this token will lose access immediately.',
        )
    ) {
        router.delete(destroyToken.url({ tokenId: id.toString() }), {
            preserveScroll: true,
            onSuccess: () => toast.success('Token revoked successfully.'),
        });
    }
};

const updateToolStatus = (toolId: number, isActive: boolean): void => {
    router.patch(
        update.url(toolId),
        { is_active: isActive },
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Tool status updated.'),
        },
    );
};

const synchronizeTools = () => {
    router.post(syncTools.url(), {}, { preserveScroll: true });
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
                    <Badge
                        variant="outline"
                        class="gap-1.5 border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs text-emerald-500"
                    >
                        <Radio class="size-3 animate-pulse text-emerald-500" />
                        SSE Server Online
                    </Badge>
                </div>
                <CardDescription>
                    Connect AI agents like Claude Desktop or Cursor to your
                    ValenceKit application.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label>Server URL</Label>
                        <div class="flex items-center gap-2">
                            <Input :model-value="serverUrl" readonly />
                            <Button
                                variant="secondary"
                                @click="
                                    copyToClipboard(
                                        serverUrl,
                                        'Server URL copied!',
                                    )
                                "
                            >
                                Copy
                            </Button>
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Use this URL when configuring an SSE (Server-Sent
                            Events) MCP connection in your AI client. You will
                            also need to provide a Bearer API token generated
                            below.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>OAuth 2.1 Connection</CardTitle>
                <CardDescription>
                    Connect MCP clients through OAuth and PKCE. Clients request
                    the mcp:use scope, then these permissions are enforced by
                    admin roles.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label>Protected Resource Metadata</Label>
                    <div class="flex items-center gap-2">
                        <Input :model-value="oauthDiscoveryUrl" readonly />
                        <Button
                            variant="secondary"
                            @click="
                                copyToClipboard(
                                    oauthDiscoveryUrl,
                                    'OAuth URL copied!',
                                )
                            "
                        >
                            Copy
                        </Button>
                    </div>
                </div>
                <Label>Application permissions</Label>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="(description, scope) in oauthScopes"
                        :key="scope"
                        class="rounded-md border p-3"
                    >
                        <div class="font-mono text-xs">{{ scope }}</div>
                        <div class="mt-1 text-sm text-muted-foreground">
                            {{ description }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>How to Connect</CardTitle>
                <CardDescription>
                    Follow these steps to connect your AI assistant (Claude
                    Desktop, Cursor, etc.) to this application.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <ol class="list-inside list-decimal space-y-4">
                    <li>
                        <strong class="text-foreground"
                            >Generate a Token:</strong
                        >
                        Use the form below to create a new API token. When the
                        green box appears, copy the long string. That exact
                        string replaces <code>YOUR_TOKEN_HERE</code> in the
                        Authorization header.
                    </li>
                    <li>
                        <strong class="text-foreground"
                            >Configure your AI Client:</strong
                        >
                        Open your AI tool's MCP configuration file (e.g.,
                        <code>claude_desktop_config.json</code>).
                    </li>
                    <li>
                        <strong class="text-foreground">Add the Server:</strong>
                        Paste the following configuration into your MCP client
                        (this connects via SSE):

                        <div class="mt-4 space-y-4">
                            <div>
                                <div
                                    class="overflow-x-auto rounded-md bg-muted p-4"
                                >
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
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <CardTitle>Manage MCP Registry</CardTitle>
                        <CardDescription>
                            Control enabled tools, scopes, versions, and sync
                            status.
                        </CardDescription>
                    </div>
                    <Button variant="secondary" @click="synchronizeTools">
                        Sync Registry
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div class="flex flex-col gap-4">
                    <div
                        v-for="tool in mcpTools"
                        :key="tool.id"
                        class="flex items-center justify-between border-b border-gray-200 py-4 last:border-0 dark:border-gray-700"
                    >
                        <div class="flex flex-col gap-1">
                            <h3
                                class="font-medium text-gray-900 dark:text-white"
                            >
                                {{ tool.name }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ tool.description }}
                            </p>
                            <div
                                class="flex flex-wrap gap-2 text-xs text-muted-foreground"
                            >
                                <Badge variant="outline"
                                    >v{{ tool.version }}</Badge
                                >
                                <Badge variant="outline">{{
                                    tool.required_scope
                                }}</Badge>
                                <Badge variant="outline">{{
                                    tool.health_status
                                }}</Badge>
                            </div>
                        </div>

                        <form
                            @submit.prevent="
                                updateToolStatus(tool.id, !tool.is_active)
                            "
                        >
                            <Switch
                                :checked="tool.is_active"
                                @update:checked="
                                    (value: boolean | 'indeterminate') =>
                                        updateToolStatus(
                                            tool.id,
                                            value === true,
                                        )
                                "
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
                    <div
                        v-if="flashToken"
                        class="rounded-md border border-primary/20 bg-muted p-4"
                    >
                        <h4 class="mb-2 font-medium text-primary">
                            New API Token Generated
                        </h4>
                        <p class="mb-4 text-sm text-muted-foreground">
                            {{ flashMessage }}
                        </p>
                        <div class="flex items-center gap-2">
                            <Input
                                :model-value="flashToken"
                                readonly
                                class="bg-background font-mono text-sm"
                            />
                            <Button
                                type="button"
                                @click="
                                    copyToClipboard(
                                        flashToken,
                                        'API Token copied!',
                                    )
                                "
                            >
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
                            <Button
                                type="submit"
                                :disabled="form.processing || !form.name.trim()"
                            >
                                {{
                                    form.processing
                                        ? 'Generating...'
                                        : 'Create Token'
                                }}
                            </Button>
                        </div>
                        <p
                            v-if="form.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>
                </CardContent>
            </form>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Active API Tokens</CardTitle>
                <CardDescription>
                    Manage your active API tokens. Revoke tokens that are no
                    longer needed.
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
                            <TableCell class="font-medium">{{
                                token.name
                            }}</TableCell>
                            <TableCell class="text-muted-foreground">
                                <RelativeTime
                                    :date="token.last_used_at"
                                    fallback="Never"
                                />
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                <RelativeTime :date="token.created_at" />
                            </TableCell>
                            <TableCell class="text-right">
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="revokeToken(token.id)"
                                >
                                    Revoke
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <div v-else class="py-6 text-center text-muted-foreground">
                    You have not generated any API tokens yet.
                </div>
            </CardContent>
        </Card>
    </div>
</template>
