<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, ListChecks, ArrowLeft } from 'lucide-vue-next';

const props = defineProps({
    course: {
        type: Object,
        required: true,
    },
    test: {
        type: Object,
        required: true,
    },
    questions: {
        type: Array,
        default: () => [],
    },
});

const destroy = (question) => {
    if (confirm('Delete this question?')) {
        router.delete(route('questions.destroy', question.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Questions — ${test.title}`" />

        <PageHeader :title="`Questions — ${test.title}`" subtitle="Add and order the questions for this test.">
            <template #actions>
                <Button as-child variant="ghost">
                    <Link :href="route('tests.index', course.slug)">
                        <ArrowLeft class="size-4" />
                        Back to tests
                    </Link>
                </Button>
                <Button as-child class="bg-amber-500 text-white hover:bg-amber-600">
                    <Link :href="route('questions.create', test.id)">
                        <Plus class="size-4" />
                        New question
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="questions.length === 0"
            class="rounded-2xl border border-dashed bg-card p-12 text-center"
        >
            <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600">
                <ListChecks class="size-6" />
            </div>
            <p class="mt-4 font-medium text-foreground">No questions yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Add your first question to this test.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                        <TableHead class="w-12 text-center">#</TableHead>
                        <TableHead>Prompt</TableHead>
                        <TableHead class="w-40">Type</TableHead>
                        <TableHead class="w-24 text-center">Points</TableHead>
                        <TableHead class="w-28 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(question, index) in questions" :key="question.id">
                        <TableCell class="text-center text-muted-foreground">{{ index + 1 }}</TableCell>
                        <TableCell class="font-medium text-foreground">{{ question.prompt }}</TableCell>
                        <TableCell><Badge variant="secondary">{{ question.type }}</Badge></TableCell>
                        <TableCell class="text-center">{{ question.points }}</TableCell>
                        <TableCell class="text-right">
                            <div class="flex justify-end gap-1">
                                <Button as-child variant="ghost" size="icon" class="size-8">
                                    <Link :href="route('questions.edit', question.id)">
                                        <Pencil class="size-4" />
                                        <span class="sr-only">Edit question</span>
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon" class="size-8" @click="destroy(question)">
                                    <Trash2 class="size-4" />
                                    <span class="sr-only">Delete question</span>
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AuthenticatedLayout>
</template>
