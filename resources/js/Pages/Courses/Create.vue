<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CourseForm from '@/Components/CourseForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    levels: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    title: '',
    summary: '',
    description: '',
    level: '',
});

const submit = () => {
    form.post(route('courses.store'));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="New course" />

        <h1 class="mb-6 text-2xl font-semibold">New course</h1>

        <form class="max-w-2xl" @submit.prevent="submit">
            <CourseForm :form="form" :levels="levels" />

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Create course
                </button>
                <Link :href="route('courses.index')" class="text-sm text-gray-600 hover:underline">Cancel</Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
