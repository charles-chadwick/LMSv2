<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: String,
});

const form = useForm({ email: '' });

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot password" />

        <h1 class="mb-2 text-lg font-semibold">Forgot password</h1>
        <p class="mb-6 text-sm text-gray-600">
            Enter your email and we'll send you a password reset link.
        </p>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">{{ status }}</div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    class="mt-1 block w-full rounded border-gray-300 shadow-sm"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div class="flex items-center justify-between">
                <Link :href="route('login')" class="text-sm text-blue-600 hover:underline">Back to login</Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Email reset link
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
