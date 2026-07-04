<script setup>
import { Link } from '@inertiajs/vue3';
import { HoverCard, HoverCardContent, HoverCardTrigger } from '@/Components/ui/hover-card';
import { Button } from '@/Components/ui/button';
import UserAvatar from '@/Components/UserAvatar.vue';
import { UserRound } from 'lucide-vue-next';

const props = defineProps({
    // User summary: { id, name, role, avatar_thumb, avatar_preview }
    user: { type: Object, required: true },
    // Avatar size used in the inline trigger.
    size: { type: String, default: 'md' },
    // Classes applied to the inline name label.
    nameClass: { type: String, default: 'text-sm font-semibold text-foreground' },
    // Hide the inline name (e.g. show avatar only).
    showName: { type: Boolean, default: true },
});
</script>

<template>
    <HoverCard>
        <HoverCardTrigger
            class="inline-flex cursor-default items-center gap-2 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <UserAvatar :user="user" :size="size" />
            <span v-if="showName" :class="nameClass">{{ user.name }}</span>
        </HoverCardTrigger>

        <HoverCardContent>
            <div class="flex flex-col items-center text-center">
                <UserAvatar :user="user" size="xl" />
                <p class="mt-3 font-display font-bold leading-tight text-foreground">{{ user.name }}</p>
                <p class="mt-0.5 text-xs font-medium capitalize text-muted-foreground">{{ user.role }}</p>

                <Button as-child variant="outline" size="sm" class="mt-4 w-full">
                    <Link :href="route('users.show', user.id)">
                        <UserRound class="size-4" />
                        Go to profile
                    </Link>
                </Button>
            </div>
        </HoverCardContent>
    </HoverCard>
</template>
