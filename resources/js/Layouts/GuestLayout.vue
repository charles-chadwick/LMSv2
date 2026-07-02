<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4 text-gray-900">
        <Link href="/" class="mb-6 text-xl font-semibold">LMS</Link>

        <div class="w-full max-w-md rounded-lg bg-white p-8 shadow">
            <slot />
        </div>
    </div>
</template>
