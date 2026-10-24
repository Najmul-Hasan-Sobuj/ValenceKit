<script setup lang="ts">
import { Blobatar } from '@blobatar/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/lib/utils';
import type { Admin, User } from '@/types';

type Props = {
    user: User | Admin;
    showEmail?: boolean;
    blobatar?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    blobatar: false,
});

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage
            v-if="showAvatar"
            :src="user.avatar as string"
            :alt="user.name"
        />
        <Blobatar
            v-else-if="blobatar"
            :name="user.email || user.name"
            :size="32"
            animate="hover"
            class="h-full w-full object-cover"
        />
        <AvatarFallback v-else class="rounded-lg text-black dark:text-white">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
    </div>
</template>
