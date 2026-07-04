<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    reply: { type: Object, required: true },
    discussionId: { type: Number, required: true },
    locked: { type: Boolean, default: false },
});

const replying = ref(false);
const form = useForm({ body: '', parent_id: props.reply.id });
const submit = () => form.post(route('discussion-replies.store', props.discussionId), {
    preserveScroll: true,
    onSuccess: () => { form.reset('body'); replying.value = false; },
});
</script>

<template>
    <div class="mt-3">
        <div class="flex items-start gap-2">
            <UserAvatar :user="reply.author" class="size-7" />
            <div class="flex-1">
                <p class="text-sm"><span class="font-medium">{{ reply.author.name }}</span></p>
                <p class="text-sm text-gray-700">{{ reply.body }}</p>
                <button v-if="!locked" class="text-xs text-amber-600" @click="replying = !replying">Reply</button>
                <form v-if="replying" class="mt-2" @submit.prevent="submit">
                    <textarea v-model="form.body" rows="2" class="w-full rounded border p-2 text-sm" placeholder="Reply…" />
                    <button type="submit" class="mt-1 rounded bg-amber-500 px-2 py-1 text-xs text-white" :disabled="form.processing">Post</button>
                </form>
            </div>
        </div>
        <div class="ml-6 border-l pl-3">
            <DiscussionReplyItem
                v-for="child in reply.children ?? []"
                :key="child.id"
                :reply="child"
                :discussion-id="discussionId"
                :locked="locked"
            />
        </div>
    </div>
</template>
