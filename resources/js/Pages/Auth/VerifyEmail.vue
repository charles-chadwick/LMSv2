<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <GuestLayout>
        <Head title="Verify email" />

        <h1 class="mb-2 text-lg font-semibold">Verify your email</h1>
        <p class="mb-6 text-sm text-gray-600">
            Before continuing, please verify your email by clicking the link we just sent you.
            If you didn't receive it, we'll gladly send another.
        </p>

        <div v-if="verificationLinkSent" class="mb-4 text-sm font-medium text-green-600">
            A new verification link has been sent to your email address.
        </div>

        <div class="flex items-center justify-between">
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="text-sm text-red-600 hover:underline"
            >
                Log out
            </Link>
            <button
                type="button"
                :disabled="form.processing"
                class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                @click="submit"
            >
                Resend verification email
            </button>
        </div>
    </GuestLayout>
</template>
