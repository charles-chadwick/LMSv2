<script setup>
import { onMounted, onUnmounted, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    conversation: { type: Object, required: true },
});

const currentUserId = usePage().props.auth.user?.id;
const state = reactive({ messages: props.conversation.messages ?? [] });

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
    if (window.Echo) {
        window.Echo.private(`conversations.${props.conversation.id}`)
            .listen('MessageSent', (message) => appendMessage(message));
    }
});
onUnmounted(() => {
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
                    class="flex"
                    :class="m.sender.id === currentUserId ? 'justify-end' : 'justify-start'"
                >
                    <p
                        class="max-w-[75%] rounded-lg px-3 py-2 text-sm"
                        :class="m.sender.id === currentUserId ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-900'"
                    >
                        {{ m.body }}
                    </p>
                </div>
            </div>

            <form class="mt-4 flex gap-2" @submit.prevent="submit">
                <input v-model="form.body" type="text" placeholder="Type a message…" class="flex-1 rounded border p-2" />
                <button type="submit" class="rounded bg-amber-500 px-4 py-2 text-white hover:bg-amber-600" :disabled="form.processing">Send</button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
