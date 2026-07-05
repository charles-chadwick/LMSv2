<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    roleOptions: {
        type: Array,
        required: true,
    },
    canEditRole: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    first_name: props.user.first_name,
    last_name: props.user.last_name,
    email: props.user.email,
    role: props.user.role,
});

const submit = () => {
    form.put(route('users.management.update', props.user.id));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Edit ${user.name}`" />

        <h1 class="mb-6 text-2xl font-semibold">Edit user</h1>

        <form class="max-w-2xl" @submit.prevent="submit">
            <Card>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="block text-sm font-medium">First name</label>
                            <Input id="first_name" v-model="form.first_name" class="mt-1" />
                            <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium">Last name</label>
                            <Input id="last_name" v-model="form.last_name" class="mt-1" />
                            <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">{{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium">Email</label>
                        <Input id="email" v-model="form.email" type="email" class="mt-1" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div v-if="canEditRole">
                        <label for="role" class="block text-sm font-medium">Role</label>
                        <select
                            id="role"
                            v-model="form.role"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm"
                        >
                            <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.role" class="mt-1 text-sm text-red-600">{{ form.errors.role }}</p>
                    </div>
                    <div v-else>
                        <p class="text-sm text-muted-foreground">Role: {{ user.role }}</p>
                    </div>
                </CardContent>
            </Card>

            <div class="mt-6 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save changes</Button>
                <Button as-child variant="ghost">
                    <Link :href="route('users.index')">Cancel</Link>
                </Button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
