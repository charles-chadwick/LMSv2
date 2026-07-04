<script setup>
import { onMounted, onUnmounted, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DiscussionReplyItem from '@/Components/DiscussionReplyItem.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    discussion: { type: Object, required: true },
});

const state = reactive({ replies: props.discussion.replies ?? [] });

const form = useForm({ body: '', parent_id: null });
const submit = () => form.post(route('discussion-replies.store', props.discussion.id), {
    preserveScroll: true,
    onSuccess: () => form.reset('body'),
});

const insertReply = (reply) => {
    // Avoid duplicating a reply we already have (e.g. our own, added on reload).
    const exists = (nodes) => nodes.some((n) => n.id === reply.id || (n.children && exists(n.children)));
    if (exists(state.replies)) {
        return;
    }
    if (reply.parent_id === null) {
        state.replies.push(reply);
        return;
    }
    const attach = (nodes) => nodes.forEach((n) => {
        if (n.id === reply.parent_id) {
            n.children = [...(n.children ?? []), reply];
        } else if (n.children) {
            attach(n.children);
        }
    });
    attach(state.replies);
};

let channel = null;
onMounted(() => {
    channel = window.Echo.private(`discussions.${props.discussion.id}`)
        .listen('DiscussionReplyPosted', (reply) => insertReply(reply));
});
onUnmounted(() => {
    window.Echo.leave(`discussions.${props.discussion.id}`);
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="discussion.title" />
        <div class="mx-auto max-w-3xl p-4">
            <div class="flex items-start gap-3">
                <UserAvatar :user="discussion.author" class="size-10" />
                <div>
                    <h1 class="text-xl font-semibold">{{ discussion.title }}</h1>
                    <p class="text-sm text-gray-500">by {{ discussion.author.name }}</p>
                    <p class="mt-2 whitespace-pre-line">{{ discussion.body }}</p>
                </div>
            </div>

            <div class="mt-6">
                <h2 class="mb-2 font-semibold">Replies</h2>
                <DiscussionReplyItem
                    v-for="reply in state.replies"
                    :key="reply.id"
                    :reply="reply"
                    :discussion-id="discussion.id"
                    :locked="discussion.is_locked"
                />
            </div>

            <form v-if="!discussion.is_locked" class="mt-6" @submit.prevent="submit">
                <textarea v-model="form.body" rows="3" class="w-full rounded border p-2" placeholder="Write a reply…" />
                <button type="submit" class="mt-2 rounded bg-amber-500 px-3 py-1.5 text-white hover:bg-amber-600" :disabled="form.processing">Reply</button>
            </form>
            <p v-else class="mt-6 rounded bg-gray-100 p-3 text-sm text-gray-500">This discussion is locked.</p>
        </div>
    </AuthenticatedLayout>
</template>
