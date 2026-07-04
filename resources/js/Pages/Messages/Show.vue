<script setup>
import { onMounted, onUnmounted, reactive, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    conversation: { type: Object, required: true },
});

const currentUserId = usePage().props.auth.user?.id;
const state = reactive({ messages: props.conversation.messages ?? [] });

const relativeFormatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
const DIVISIONS = [
    { amount: 60, unit: 'second' },
    { amount: 60, unit: 'minute' },
    { amount: 24, unit: 'hour' },
    { amount: 7, unit: 'day' },
    { amount: 4.34524, unit: 'week' },
    { amount: 12, unit: 'month' },
    { amount: Number.POSITIVE_INFINITY, unit: 'year' },
];

// Ticking clock so relative timestamps stay fresh without a page reload.
const now = ref(Date.now());
let clock;

const relativeTime = (iso) => {
    if (!iso) {
        return '';
    }
    let duration = (new Date(iso).getTime() - now.value) / 1000;
    for (const division of DIVISIONS) {
        if (Math.abs(duration) < division.amount) {
            return relativeFormatter.format(Math.round(duration), division.unit);
        }
        duration /= division.amount;
    }
    return '';
};

const absoluteTime = (iso) => (iso ? new Date(iso).toLocaleString() : '');

const form = useForm({ body: '' });
const submit = () => form.post(route('messages.store', props.conversation.id), {
    preserveScroll: true,
    onSuccess: () => form.reset('body'),
});

const appendMessage = (message) => {
    if (!state.messages.some((m) => m.id === message.id)) {
        state.messages.push(message);
    }
};

onMounted(() => {
    clock = setInterval(() => { now.value = Date.now(); }, 30000);
    if (window.Echo) {
        window.Echo.private(`conversations.${props.conversation.id}`)
            .listen('MessageSent', (message) => appendMessage(message));
    }
});
onUnmounted(() => {
    clearInterval(clock);
    if (window.Echo) {
        window.Echo.leave(`conversations.${props.conversation.id}`);
    }
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Chat with ${conversation.other.name}`" />
        <div class="mx-auto flex max-w-2xl flex-col p-4">
            <div class="mb-4 flex items-center gap-2">
                <UserAvatar :user="conversation.other" class="size-9" />
                <h1 class="font-semibold">{{ conversation.other.name }}</h1>
            </div>

            <div class="space-y-2">
                <div
                    v-for="m in state.messages"
                    :key="m.id"
                    class="flex flex-col"
                    :class="m.sender.id === currentUserId ? 'items-end' : 'items-start'"
                >
                    <p
                        class="max-w-[75%] rounded-lg px-3 py-2 text-sm"
                        :class="m.sender.id === currentUserId ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-900'"
                    >
                        {{ m.body }}
                    </p>
                    <time
                        :datetime="m.created_at"
                        :title="absoluteTime(m.created_at)"
                        class="mt-0.5 cursor-default px-1 text-xs text-muted-foreground"
                    >
                        {{ relativeTime(m.created_at) }}
                    </time>
                </div>
            </div>

            <form class="mt-4 flex gap-2" @submit.prevent="submit">
                <input v-model="form.body" type="text" placeholder="Type a message…" class="flex-1 rounded border p-2" />
                <button type="submit" class="rounded bg-amber-500 px-4 py-2 text-white hover:bg-amber-600" :disabled="form.processing">Send</button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
