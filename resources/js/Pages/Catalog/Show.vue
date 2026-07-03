<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LevelBadge from '@/Components/LevelBadge.vue';
import UserHoverCard from '@/Components/UserHoverCard.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CircleCheck, Circle, Play, BookOpen } from 'lucide-vue-next';

const props = defineProps({
    course: { type: Object, required: true },
    can_learn: { type: Boolean, default: false },
    completed_lesson_ids: { type: Array, default: () => [] },
    first_incomplete_lesson_slug: { type: String, default: null },
    enrollment_status: { type: String, default: null },
});

const isEnrolled = computed(
    () => props.enrollment_status !== null && props.enrollment_status !== 'Dropped',
);

const isComplete = (lesson) => props.completed_lesson_ids.includes(lesson.id);
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="course.title" />

        <!-- Course hero -->
        <section
            class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-emerald-500 to-teal-500 px-6 py-8 text-white shadow-lg sm:px-10 sm:py-10"
        >
            <div class="pointer-events-none absolute -right-16 -top-20 size-64 rounded-full bg-white/10 blur-2xl" />

            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold capitalize backdrop-blur">
                            {{ course.level }}
                        </span>
                        <span
                            v-if="isEnrolled"
                            class="inline-flex items-center rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold backdrop-blur"
                        >
                            Enrolled
                        </span>
                    </div>
                    <h1 class="font-display text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">
                        {{ course.title }}
                    </h1>
                    <UserHoverCard
                        :user="course.instructor"
                        size="sm"
                        name-class="text-sm text-white/85"
                        class="mt-2"
                    />
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2.5">
                    <Button
                        v-if="can_learn && first_incomplete_lesson_slug"
                        as-child
                        class="bg-white text-emerald-700 shadow-sm hover:bg-white/90"
                    >
                        <Link :href="route('lessons.show', [course.slug, first_incomplete_lesson_slug])">
                            <Play class="size-4" />
                            Continue learning
                        </Link>
                    </Button>
                    <span
                        v-else-if="! isEnrolled"
                        class="rounded-full bg-white/15 px-3.5 py-1.5 text-sm font-medium text-white/90 backdrop-blur"
                    >
                        Enrollment is managed by your instructor
                    </span>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Overview -->
            <div class="lg:col-span-1">
                <div class="rounded-2xl border bg-card p-5 shadow-sm">
                    <h2 class="font-display text-base font-bold tracking-tight">About this course</h2>
                    <LevelBadge :level="course.level" class="mt-3" />
                    <p v-if="course.summary" class="mt-3 text-sm font-medium text-foreground">{{ course.summary }}</p>
                    <p v-if="course.description" class="mt-2 whitespace-pre-line text-sm text-muted-foreground">
                        {{ course.description }}
                    </p>
                </div>
            </div>

            <!-- Syllabus -->
            <div class="lg:col-span-2">
                <div class="mb-3 flex items-center gap-2">
                    <BookOpen class="size-5 text-emerald-600" />
                    <h2 class="font-display text-lg font-bold tracking-tight">Syllabus</h2>
                </div>

                <div v-if="course.modules.length === 0" class="rounded-2xl border border-dashed bg-card p-8 text-center text-sm text-muted-foreground">
                    No modules yet.
                </div>

                <ol v-else class="space-y-4">
                    <li
                        v-for="(module, index) in course.modules"
                        :key="index"
                        class="overflow-hidden rounded-2xl border bg-card shadow-sm"
                    >
                        <div class="flex items-center gap-3 border-b bg-muted/40 px-5 py-3">
                            <span class="flex size-7 items-center justify-center rounded-lg bg-emerald-500/15 text-xs font-bold text-emerald-600">
                                {{ index + 1 }}
                            </span>
                            <h3 class="font-semibold text-foreground">{{ module.title }}</h3>
                        </div>
                        <ul class="divide-y">
                            <li
                                v-for="lesson in module.lessons"
                                :key="lesson.id"
                                class="flex items-center gap-3 px-5 py-2.5 text-sm"
                            >
                                <CircleCheck v-if="isComplete(lesson)" class="size-4 shrink-0 text-emerald-600" />
                                <Circle v-else class="size-4 shrink-0 text-muted-foreground/40" />
                                <Link
                                    v-if="can_learn"
                                    :href="route('lessons.show', [course.slug, lesson.slug])"
                                    class="font-medium text-foreground hover:text-emerald-600 hover:underline"
                                >
                                    {{ lesson.title }}
                                </Link>
                                <span v-else class="text-muted-foreground">{{ lesson.title }}</span>
                            </li>
                        </ul>
                    </li>
                </ol>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
