<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <h1 class="mb-6 text-lg font-semibold">Log in</h1>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

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

            <div>
                <label for="password" class="block text-sm font-medium">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    class="mt-1 block w-full rounded border-gray-300 shadow-sm"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.remember" type="checkbox" class="rounded border-gray-300" />
                Remember me
            </label>

            <div class="flex items-center justify-between">
                <Link :href="route('password.request')" class="text-sm text-blue-600 hover:underline">
                    Forgot password?
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Log in
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
