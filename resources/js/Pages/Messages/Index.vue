<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    conversations: { type: Array, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Messages" />
        <div class="mx-auto max-w-2xl p-4">
            <h1 class="mb-4 text-xl font-semibold">Messages</h1>
            <ul class="divide-y">
                <li v-for="c in conversations" :key="c.id">
                    <Link :href="route('conversations.show', c.id)" class="flex items-center gap-3 py-3">
                        <UserAvatar :user="c.other" class="size-10" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ c.other.name }}</p>
                            <p class="truncate text-sm text-gray-500">{{ c.last_message ?? 'No messages yet' }}</p>
                        </div>
                        <span v-if="c.unread_count > 0" class="rounded-full bg-red-500 px-2 text-xs text-white">{{ c.unread_count }}</span>
                    </Link>
                </li>
                <li v-if="conversations.length === 0" class="py-6 text-center text-gray-500">No conversations yet.</li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
