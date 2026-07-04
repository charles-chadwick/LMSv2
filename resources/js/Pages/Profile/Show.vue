<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Upload, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    profile: { type: Object, required: true },
    can_edit: { type: Boolean, default: false },
    can_message: { type: Boolean, default: false },
    form: { type: Object, default: null },
});

const profileForm = useForm({
    first_name: props.form?.first_name ?? '',
    last_name: props.form?.last_name ?? '',
    email: props.form?.email ?? '',
});

const avatarForm = useForm({
    avatar: null,
});

const messageForm = useForm({
    user_id: props.profile.id,
});

const startConversation = () => messageForm.post(route('conversations.store'));

const file_input = ref(null);

const submitProfile = () => {
    profileForm.patch(route('users.update', props.profile.id), {
        preserveScroll: true,
    });
};

const onAvatarSelected = (event) => {
    const file = event.target.files?.[0];
    if (! file) {
        return;
    }

    avatarForm.avatar = file;
    avatarForm.post(route('users.avatar.store', props.profile.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => avatarForm.reset(),
        onFinish: () => {
            if (file_input.value) {
                file_input.value.value = '';
            }
        },
    });
};

const removeAvatar = () => {
    router.delete(route('users.avatar.destroy', props.profile.id), { preserveScroll: true });
};

const hasAvatar = () => Boolean(props.profile.avatar_preview || props.profile.avatar_thumb);
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="profile.name" />

        <PageHeader title="Profile" :subtitle="can_edit ? 'Manage how you appear across the platform.' : profile.name" />

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Identity card -->
            <div class="lg:col-span-1">
                <div class="flex flex-col items-center rounded-2xl border bg-card p-6 text-center shadow-sm">
                    <UserAvatar :user="profile" size="xl" />
                    <p class="mt-4 font-display text-lg font-bold tracking-tight text-foreground">{{ profile.name }}</p>
                    <p class="mt-0.5 text-sm font-medium capitalize text-muted-foreground">{{ profile.role }}</p>

                    <Button
                        v-if="can_message"
                        type="button"
                        size="sm"
                        class="mt-5 w-full"
                        :disabled="messageForm.processing"
                        @click="startConversation"
                    >
                        Message
                    </Button>

                    <div v-if="can_edit" class="mt-5 flex w-full flex-col gap-2">
                        <input
                            ref="file_input"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="onAvatarSelected"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="avatarForm.processing"
                            @click="file_input?.click()"
                        >
                            <Upload class="size-4" />
                            {{ hasAvatar() ? 'Change photo' : 'Upload photo' }}
                        </Button>
                        <Button
                            v-if="hasAvatar()"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                            @click="removeAvatar"
                        >
                            <Trash2 class="size-4" />
                            Remove photo
                        </Button>
                        <p v-if="avatarForm.errors.avatar" class="text-sm text-destructive">
                            {{ avatarForm.errors.avatar }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Edit form (owner only) -->
            <div v-if="can_edit" class="lg:col-span-2">
                <form class="rounded-2xl border bg-card p-6 shadow-sm" @submit.prevent="submitProfile">
                    <h2 class="font-display text-base font-bold tracking-tight">Account details</h2>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div class="flex flex-col gap-1.5">
                            <Label for="first_name">First name</Label>
                            <Input id="first_name" v-model="profileForm.first_name" type="text" />
                            <p v-if="profileForm.errors.first_name" class="text-sm text-destructive">
                                {{ profileForm.errors.first_name }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label for="last_name">Last name</Label>
                            <Input id="last_name" v-model="profileForm.last_name" type="text" />
                            <p v-if="profileForm.errors.last_name" class="text-sm text-destructive">
                                {{ profileForm.errors.last_name }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <Label for="email">Email</Label>
                            <Input id="email" v-model="profileForm.email" type="email" />
                            <p v-if="profileForm.errors.email" class="text-sm text-destructive">
                                {{ profileForm.errors.email }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center gap-3">
                        <Button type="submit" :disabled="profileForm.processing">Save changes</Button>
                        <transition
                            enter-active-class="transition ease-out duration-300"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in duration-200"
                            leave-to-class="opacity-0"
                        >
                            <p v-if="profileForm.recentlySuccessful" class="text-sm text-emerald-600">Saved.</p>
                        </transition>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
