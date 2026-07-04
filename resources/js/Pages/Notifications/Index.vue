<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    notifications: { type: Array, required: true },
});

const markAllRead = () => router.post(route('notifications.read-all'), {}, { preserveScroll: true });
const openNotification = (notification) => {
    router.post(route('notifications.read', notification.id), {}, {
        preserveScroll: true,
        onSuccess: () => router.visit(
            notification.type === 'new_message'
                ? route('conversations.show', notification.conversation_id)
                : route('discussions.show', notification.discussion_id),
        ),
    });
};

const notificationLabel = (type) => {
    if (type === 'new_question') {
        return 'asked a question';
    }
    if (type === 'new_message') {
        return 'sent a message';
    }
    return 'replied';
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Notifications" />
        <div class="mx-auto max-w-2xl p-4">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">Notifications</h1>
                <button class="text-sm text-amber-600" @click="markAllRead">Mark all read</button>
            </div>
            <ul class="divide-y">
                <li v-for="n in notifications" :key="n.id" class="cursor-pointer py-3" :class="{ 'font-semibold': !n.read_at }" @click="openNotification(n)">
                    <p class="text-sm">{{ n.actor_name ?? n.sender_name }} · {{ notificationLabel(n.type) }}</p>
                    <p class="text-sm text-gray-500">{{ n.excerpt }}</p>
                </li>
                <li v-if="notifications.length === 0" class="py-6 text-center text-gray-500">No notifications yet.</li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
