<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TestForm from '@/Components/TestForm.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    course: {
        type: Object,
        required: true,
    },
    lessons: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    title: '',
    lesson_id: null,
    description: '',
    time_limit_minutes: null,
    max_attempts: 1,
    passing_score: null,
    available_from: '',
    due_at: '',
});

const submit = () => {
    form.post(route('tests.store', props.course.slug));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="New test" />

        <h1 class="mb-1 text-2xl font-semibold">New test</h1>
        <p class="mb-6 text-sm text-muted-foreground">{{ course.title }}</p>

        <form class="max-w-2xl" @submit.prevent="submit">
            <Card>
                <CardContent>
                    <TestForm :form="form" :lessons="lessons" />
                </CardContent>
            </Card>

            <div class="mt-6 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Create test</Button>
                <Button as-child variant="ghost">
                    <Link :href="route('tests.index', course.slug)">Cancel</Link>
                </Button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
