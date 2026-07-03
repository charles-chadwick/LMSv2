<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth.user);
const canCreateCourses = computed(() => user.value.can?.create_courses ?? false);
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900">
        <nav class="border-b bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <Link :href="route('dashboard')" class="font-semibold">LMS</Link>
                <Link
                    v-if="canCreateCourses"
                    :href="route('courses.index')"
                    class="text-sm text-gray-600 hover:underline"
                >
                    Courses
                </Link>

                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">{{ user.name }}</span>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="text-sm text-red-600 hover:underline"
                    >
                        Log out
                    </Link>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <slot />
        </main>
    </div>
</template>
