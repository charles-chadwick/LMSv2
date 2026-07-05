<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Pagination from '@/Components/Pagination.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Head, Link } from '@inertiajs/vue3';
import { Plus, UsersRound } from 'lucide-vue-next';

defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '' }),
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Users" />

        <PageHeader title="Users" subtitle="Create and manage instructor and student accounts.">
            <template #actions>
                <Button as-child class="bg-rose-600 text-white hover:bg-rose-700">
                    <Link :href="route('users.create')">
                        <Plus class="size-4" />
                        New user
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="mb-4">
            <SearchInput :initial="filters.search ?? ''" placeholder="Search users…" />
        </div>

        <div
            v-if="users.total === 0"
            class="rounded-2xl border border-dashed bg-card p-12 text-center"
        >
            <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-rose-500/15 text-rose-600">
                <UsersRound class="size-6" />
            </div>
            <p class="mt-4 font-medium text-foreground">No users yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Create your first user to get started.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                        <TableHead>Name</TableHead>
                        <TableHead class="w-56">Email</TableHead>
                        <TableHead class="w-32">Role</TableHead>
                        <TableHead class="w-28">Status</TableHead>
                        <TableHead class="w-20 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="row in users.data" :key="row.id">
                        <TableCell>
                            <div class="flex items-center gap-2.5">
                                <UserAvatar :user="row" class="size-8" />
                                <span class="font-semibold text-foreground">{{ row.name }}</span>
                            </div>
                        </TableCell>
                        <TableCell class="text-muted-foreground">{{ row.email }}</TableCell>
                        <TableCell>{{ row.role }}</TableCell>
                        <TableCell>
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="row.status === 'Active' ? 'bg-emerald-500/10 text-emerald-700' : 'bg-amber-500/10 text-amber-700'"
                            >
                                {{ row.status }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right">
                            <Button as-child variant="ghost" size="sm">
                                <Link :href="route('users.edit', row.id)">Edit</Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :paginator="users" />
    </AuthenticatedLayout>
</template>
