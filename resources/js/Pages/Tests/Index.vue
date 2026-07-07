<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, ClipboardList, ListChecks } from 'lucide-vue-next';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    course: {
        type: Object,
        required: true,
    },
    tests: {
        type: Array,
        default: () => [],
    },
});

const destroy = async (test) => {
    const confirmed = await confirm({
        title: 'Delete test',
        description: `Delete "${test.title}"? This cannot be undone.`,
        confirmText: 'Delete',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(route('tests.destroy', test.id), { preserveScroll: true });
    }
};

const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Tests — ${course.title}`" />

        <PageHeader :title="`Tests — ${course.title}`" subtitle="Create and manage assessments for this course.">
            <template #actions>
                <Button as-child class="bg-amber-500 text-white hover:bg-amber-600">
                    <Link :href="route('tests.create', course.slug)">
                        <Plus class="size-4" />
                        New test
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="tests.length === 0"
            class="rounded-2xl border border-dashed bg-card p-12 text-center"
        >
            <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600">
                <ClipboardList class="size-6" />
            </div>
            <p class="mt-4 font-medium text-foreground">No tests yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Add your first test to this course.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                        <TableHead>Title</TableHead>
                        <TableHead class="w-28 text-center">Attempts</TableHead>
                        <TableHead class="w-32 text-center">Passing %</TableHead>
                        <TableHead class="w-36">Due</TableHead>
                        <TableHead class="w-28 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="test in tests" :key="test.id">
                        <TableCell class="font-semibold text-foreground">{{ test.title }}</TableCell>
                        <TableCell class="text-center">{{ test.max_attempts }}</TableCell>
                        <TableCell class="text-center">{{ test.passing_score ?? '—' }}</TableCell>
                        <TableCell>{{ formatDate(test.due_at) }}</TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-1">
                                <Button as-child variant="ghost" size="icon" class="size-8">
                                    <Link :href="route('questions.index', test.id)">
                                        <ListChecks class="size-4" />
                                        <span class="sr-only">Manage questions</span>
                                    </Link>
                                </Button>
                                <Button as-child variant="ghost" size="icon" class="size-8">
                                    <Link :href="route('tests.edit', test.id)">
                                        <Pencil class="size-4" />
                                        <span class="sr-only">Edit test</span>
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon" class="size-8" @click="destroy(test)">
                                    <Trash2 class="size-4" />
                                    <span class="sr-only">Delete test</span>
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AuthenticatedLayout>
</template>
