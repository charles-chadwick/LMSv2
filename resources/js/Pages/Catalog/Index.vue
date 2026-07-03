<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import LevelBadge from '@/Components/LevelBadge.vue';
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheckBig, ArrowUpRight, Compass } from 'lucide-vue-next';

defineProps({
    courses: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Browse courses" />

        <PageHeader
            title="Browse courses"
            subtitle="Discover published courses and enroll in your next skill."
        />

        <div
            v-if="courses.length === 0"
            class="rounded-2xl border border-dashed bg-card p-12 text-center"
        >
            <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-600">
                <Compass class="size-6" />
            </div>
            <p class="mt-4 font-medium text-foreground">No published courses yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Check back soon — new courses are on the way.</p>
        </div>

        <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="course in courses"
                :key="course.id"
                :href="route('catalog.show', course.slug)"
                class="group relative flex flex-col overflow-hidden rounded-2xl border bg-card p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md"
            >
                <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-500 opacity-0 transition-opacity group-hover:opacity-100" />

                <div class="mb-3 flex items-center justify-between gap-2">
                    <LevelBadge :level="course.level" />
                    <span
                        v-if="course.is_enrolled"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600"
                    >
                        <CircleCheckBig class="size-3.5" />
                        Enrolled
                    </span>
                </div>

                <h2 class="font-display text-lg font-bold leading-snug tracking-tight text-foreground">
                    {{ course.title }}
                </h2>
                <p class="mt-1.5 line-clamp-2 flex-1 text-sm text-muted-foreground">{{ course.summary }}</p>

                <div class="mt-4 flex items-center justify-between border-t pt-3">
                    <span class="text-xs font-medium text-muted-foreground">{{ course.instructor }}</span>
                    <ArrowUpRight class="size-4 text-emerald-600 opacity-0 transition-opacity group-hover:opacity-100" />
                </div>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
