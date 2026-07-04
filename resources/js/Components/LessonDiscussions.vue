<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import UserAvatar from '@/Components/UserAvatar.vue';

const props = defineProps({
    course: { type: Object, required: true },
    lesson: { type: Object, required: true },
    discussions: { type: Array, required: true },
});

const form = useForm({ title: '', body: '', lesson_id: props.lesson.id });

const submit = () => form.post(route('discussions.store', props.course.slug), {
    preserveScroll: true,
    onSuccess: () => form.reset('title', 'body'),
});
</script>

<template>
    <section class="mt-8 border-t pt-6">
        <h2 class="mb-4 text-lg font-semibold">Questions about this lesson</h2>

        <form class="mb-6 space-y-2" @submit.prevent="submit">
            <input v-model="form.title" type="text" placeholder="Title" class="w-full rounded border p-2" />
            <textarea v-model="form.body" placeholder="Ask a question…" class="w-full rounded border p-2" rows="3" />
            <button type="submit" class="rounded bg-amber-500 px-3 py-1.5 text-white hover:bg-amber-600" :disabled="form.processing">Post question</button>
        </form>

        <ul class="space-y-3">
            <li v-for="d in discussions" :key="d.id" class="flex items-center gap-3">
                <UserAvatar :user="d.author" class="size-8" />
                <Link :href="route('discussions.show', d.id)" class="text-sm font-medium hover:underline">{{ d.title }}</Link>
                <span class="text-xs text-gray-500">{{ d.replies_count }} replies</span>
            </li>
            <li v-if="discussions.length === 0" class="text-sm text-gray-500">No questions yet — ask the first one.</li>
        </ul>
    </section>
</template>
