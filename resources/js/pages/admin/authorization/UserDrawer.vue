<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Shield, ShieldCheck, ShieldOff, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import InputError from '@/components/InputError.vue';
import { store, update } from '@/actions/App/Http/Controllers/Admin/Authorization/UserController';

const props = defineProps<{
    open: boolean;
    user: any | null; // null for create
    roles: Array<{id: number, name: string}>;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'request-delete', user: any): void;
}>();

const form = useForm({
    name: '',
    email: '',
    role: '',
    is_active: true,
});

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        form.clearErrors();
        if (props.user) {
            form.name = props.user.name;
            form.email = props.user.email;
            form.role = props.user.roles?.[0]?.name ?? '';
            form.is_active = props.user.is_active;
        } else {
            form.reset();
        }
    }
});

function submit() {
    if (props.user) {
        form.put(update.url(props.user.id), {
            onSuccess: () => emit('update:open', false),
        });
    } else {
        form.post(store.url(), {
            onSuccess: () => emit('update:open', false),
        });
    }
}
</script>

<template>
    <Drawer :open="open" @update:open="emit('update:open', $event)">
        <DrawerContent class="md:inset-x-auto md:right-2 md:left-64">
            <div class="w-full">
                <DrawerHeader>
                    <DrawerTitle>{{ user ? 'Edit user access' : 'Invite an admin' }}</DrawerTitle>
                    <DrawerDescription>
                        {{ user ? `Update ${user.name}'s role and access level.` : 'Create an administrator account and assign a role.' }}
                    </DrawerDescription>
                </DrawerHeader>

                <div class="grid gap-5 px-4 pb-2 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="admin-name">Name</Label>
                        <Input id="admin-name" v-model="form.name" placeholder="Full name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="admin-email">Email</Label>
                        <Input id="admin-email" type="email" v-model="form.email" placeholder="name@company.com" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label class="flex items-center gap-2"><ShieldCheck class="text-muted-foreground size-4" />Role</Label>
                        <Combobox v-model="form.role">
                            <ComboboxAnchor>
                                <ComboboxInput aria-label="Select role" :display-value="(value) => String(value || 'Choose a role')" placeholder="Select role" />
                            </ComboboxAnchor>
                            <ComboboxList side="top" align="start">
                                <ComboboxEmpty>No roles found.</ComboboxEmpty>
                                <ComboboxItem v-for="r in roles" :key="r.id" :value="r.name">
                                    {{ r.name }}
                                </ComboboxItem>
                            </ComboboxList>
                        </Combobox>
                        <InputError :message="form.errors.role" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Status</Label>
                        <Combobox :model-value="form.is_active ? 'Active' : 'Inactive'" @update:model-value="form.is_active = $event === 'Active'">
                            <ComboboxAnchor>
                                <ComboboxInput aria-label="Select status" :display-value="(value) => String(value || 'Active')" placeholder="Select status" />
                            </ComboboxAnchor>
                            <ComboboxList side="top" align="start">
                                <ComboboxItem value="Active">Active</ComboboxItem>
                                <ComboboxItem value="Inactive">Inactive</ComboboxItem>
                            </ComboboxList>
                        </Combobox>
                        <InputError :message="form.errors.is_active" />
                    </div>

                    <!-- 2FA status in drawer -->
                    <div v-if="user" class="grid gap-2 sm:col-span-2">
                        <Label class="flex items-center gap-2">
                            <Shield class="text-muted-foreground size-4" />
                            Two-Factor Authentication
                        </Label>
                        <div class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm" :class="user.passkeys?.length ? 'bg-blue-500/5 border-blue-500/20' : 'bg-amber-500/5 border-amber-500/20'">
                            <ShieldCheck v-if="user.passkeys?.length" class="size-4 text-blue-500 shrink-0" />
                            <ShieldOff v-else class="size-4 text-amber-500 shrink-0" />
                            <span>{{ user.passkeys?.length ? 'Passkey / 2FA is active' : 'No 2FA configured — security risk' }}</span>
                        </div>
                    </div>
                </div>

                <DrawerFooter class="px-4 flex flex-row items-center justify-between gap-2">
                    <Button
                        v-if="user"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive hover:bg-destructive/10 gap-1.5 cursor-pointer"
                        @click="emit('request-delete', user)"
                    >
                        <Trash2 class="size-4" />
                        Delete user
                    </Button>
                    <span v-else />

                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" @click="emit('update:open', false)">Cancel</Button>
                        <Button size="sm" @click="submit" :disabled="form.processing">
                            {{ user ? 'Save changes' : 'Send invite' }}
                        </Button>
                    </div>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
