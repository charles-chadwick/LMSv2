<script setup>
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { getInitials, avatarColor } from '@/lib/user';
import { cn } from '@/lib/utils';

const props = defineProps({
    // User summary: { id, name, avatar_thumb, avatar_preview, ... }
    user: { type: Object, required: true },
    size: { type: String, default: 'md' }, // sm | md | lg | xl
});

const SIZES = {
    sm: 'size-7 text-[0.65rem]',
    md: 'size-8 text-xs',
    lg: 'size-10 text-sm',
    xl: 'size-20 text-2xl',
};

// Larger renders use the preview conversion; smaller ones the thumb.
const image_src = computed(() =>
    props.size === 'lg' || props.size === 'xl'
        ? props.user.avatar_preview ?? props.user.avatar_thumb
        : props.user.avatar_thumb ?? props.user.avatar_preview,
);

const initials = computed(() => getInitials(props.user.name));
const fallback_color = computed(() => avatarColor(props.user));
</script>

<template>
    <Avatar :class="cn(SIZES[size] ?? SIZES.md)">
        <AvatarImage v-if="image_src" :src="image_src" :alt="user.name" />
        <AvatarFallback :class="cn('font-bold', fallback_color)">
            {{ initials }}
        </AvatarFallback>
    </Avatar>
</template>
