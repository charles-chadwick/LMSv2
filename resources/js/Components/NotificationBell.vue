<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';

const page = usePage();
const count = ref(page.props.auth.user?.unread_notifications_count ?? 0);

const userId = page.props.auth.user?.id;
onMounted(() => {
    if (!userId || !window.Echo) {
        return;
    }
    window.Echo.private(`App.Models.User.${userId}`)
        .notification(() => { count.value += 1; });
});
onUnmounted(() => {
    if (userId && window.Echo) {
        window.Echo.leave(`App.Models.User.${userId}`);
    }
});
</script>

<template>
    <Link :href="route('notifications.index')" class="relative inline-flex items-center" aria-label="Notifications">
        <Bell class="size-5" />
        <span v-if="count > 0" class="absolute -right-2 -top-2 rounded-full bg-red-500 px-1.5 text-xs text-white">{{ count }}</span>
    </Link>
</template>
