<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ProgressBar from '@/Components/ProgressBar.vue';
import Pagination from '@/Components/Pagination.vue';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Head, Link } from '@inertiajs/vue3';
import { BookMarked } from 'lucide-vue-next';

defineProps({
    enrollments: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="My courses" />

        <PageHeader
            title="My courses"
            subtitle="Track your progress and jump back into learning."
        />

        <div
            v-if="enrollments.total === 0"
            class="rounded-2xl border border-dashed bg-card p-12 text-center"
        >
            <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-sky-500/15 text-sky-600">
                <BookMarked class="size-6" />
            </div>
            <p class="mt-4 font-medium text-foreground">You haven't enrolled in any courses yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Browse the catalog to find your first course.</p>
            <Button as-child class="mt-5 bg-sky-600 text-white hover:bg-sky-700">
                <Link :href="route('catalog.index')">Browse courses</Link>
            </Button>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                        <TableHead>Course</TableHead>
                        <TableHead class="w-32">Status</TableHead>
                        <TableHead class="w-64">Progress</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="enrollment in enrollments.data" :key="enrollment.id">
                        <TableCell>
                            <Link
                                :href="route('catalog.show', enrollment.course_slug)"
                                class="font-semibold text-foreground hover:text-sky-600 hover:underline"
                            >
                                {{ enrollment.course_title }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <StatusBadge :status="enrollment.status" />
                        </TableCell>
                        <TableCell>
                            <ProgressBar :value="enrollment.progress_percentage" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :paginator="enrollments" />
    </AuthenticatedLayout>
</template>
