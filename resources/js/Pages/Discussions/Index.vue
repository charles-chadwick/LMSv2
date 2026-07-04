<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    course: { type: Object, required: true },
    discussions: { type: Object, required: true },
});

const form = useForm({ title: '', body: '' });
const submit = () => form.post(route('discussions.store', props.course.slug), {
    onSuccess: () => form.reset(),
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Discussions" />
        <div class="mx-auto max-w-3xl p-4">
            <h1 class="mb-4 text-xl font-semibold">{{ course.title }} — Discussions</h1>

            <form class="mb-6 space-y-2 rounded border p-4" @submit.prevent="submit">
                <input v-model="form.title" type="text" placeholder="Question title" class="w-full rounded border p-2" />
                <textarea v-model="form.body" placeholder="What's your question?" rows="3" class="w-full rounded border p-2" />
                <button type="submit" class="rounded bg-amber-500 px-3 py-1.5 text-white hover:bg-amber-600" :disabled="form.processing">Ask</button>
            </form>

            <ul class="divide-y">
                <li v-for="d in discussions.data" :key="d.id" class="flex items-center gap-3 py-3">
                    <UserAvatar :user="d.author" class="size-9" />
                    <div class="min-w-0 flex-1">
                        <Link :href="route('discussions.show', d.id)" class="font-medium hover:underline">
                            <span v-if="d.is_pinned" class="mr-1 text-amber-600">📌</span>{{ d.title }}
                            <span v-if="d.is_locked" class="ml-1 text-gray-400">🔒</span>
                        </Link>
                        <p class="truncate text-sm text-gray-500">by {{ d.author.name }} · {{ d.replies_count }} replies</p>
                    </div>
                </li>
                <li v-if="discussions.data.length === 0" class="py-6 text-center text-gray-500">No discussions yet.</li>
            </ul>

            <Pagination :paginator="discussions" class="mt-4" />
        </div>
    </AuthenticatedLayout>
</template>
