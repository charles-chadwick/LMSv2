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
    test: {
        type: Object,
        required: true,
    },
    lessons: {
        type: Array,
        default: () => [],
    },
});

// datetime-local inputs expect "YYYY-MM-DDTHH:mm"; trim the ISO strings.
const toLocalInput = (value) => (value ? String(value).slice(0, 16) : '');

const form = useForm({
    title: props.test.title,
    lesson_id: props.test.lesson_id,
    description: props.test.description ?? '',
    time_limit_minutes: props.test.time_limit_minutes,
    max_attempts: props.test.max_attempts,
    passing_score: props.test.passing_score,
    available_from: toLocalInput(props.test.available_from),
    due_at: toLocalInput(props.test.due_at),
});

const submit = () => {
    form.put(route('tests.update', props.test.id));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Edit test" />

        <h1 class="mb-1 text-2xl font-semibold">Edit test</h1>
        <p class="mb-6 text-sm text-muted-foreground">{{ course.title }}</p>

        <form class="max-w-2xl" @submit.prevent="submit">
            <Card>
                <CardContent>
                    <TestForm :form="form" :lessons="lessons" />
                </CardContent>
            </Card>

            <div class="mt-6 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save changes</Button>
                <Button as-child variant="ghost">
                    <Link :href="route('tests.index', course.slug)">Cancel</Link>
                </Button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
