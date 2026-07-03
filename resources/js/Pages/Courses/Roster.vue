<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ProgressBar from '@/Components/ProgressBar.vue';
import StudentSearch from '@/Components/StudentSearch.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Users, UserPlus } from 'lucide-vue-next';

const props = defineProps({
    course: { type: Object, required: true },
    students: { type: Array, required: true },
});

const selected_student = ref(null);

const form = useForm({
    student_id: '',
});

const enroll = () => {
    if (! selected_student.value) {
        return;
    }

    form.student_id = selected_student.value.id;
    form.post(route('courses.roster.store', props.course.slug), {
        preserveScroll: true,
        onSuccess: () => {
            selected_student.value = null;
            form.reset();
        },
    });
};

const remove = (student) => {
    if (! confirm(`Remove ${student.name} from "${props.course.title}"?`)) {
        return;
    }
    router.delete(route('enrollments.destroy', student.id), { preserveScroll: true });
};

const initialsFor = (name) => {
    return (name || '?')
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Roster — ${course.title}`" />

        <PageHeader
            :title="course.title"
            eyebrow="Roster"
            :subtitle="`${students.length} ${students.length === 1 ? 'student' : 'students'} enrolled`"
        />

        <!-- Add-student toolbar (kept out of the header so its results dropdown isn't clipped) -->
        <div class="mb-6 flex flex-wrap items-start justify-end gap-2.5">
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2.5">
                    <StudentSearch
                        v-model:selected="selected_student"
                        :course-slug="course.slug"
                        :invalid="Boolean(form.errors.student_id)"
                    />
                    <Button
                        type="button"
                        class="bg-amber-500 text-white hover:bg-amber-600"
                        :disabled="! selected_student || form.processing"
                        @click="enroll"
                    >
                        <UserPlus class="size-4" />
                        Enroll
                    </Button>
                </div>
                <p v-if="form.errors.student_id" class="text-sm text-destructive">{{ form.errors.student_id }}</p>
            </div>
        </div>

        <div
            v-if="students.length === 0"
            class="rounded-2xl border border-dashed bg-card p-12 text-center"
        >
            <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600">
                <Users class="size-6" />
            </div>
            <p class="mt-4 font-medium text-foreground">No students enrolled yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Use “Enroll” above to add students to this course.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                        <TableHead>Student</TableHead>
                        <TableHead class="w-32">Status</TableHead>
                        <TableHead class="w-56">Progress</TableHead>
                        <TableHead class="w-36">Enrolled</TableHead>
                        <TableHead class="w-24 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="student in students" :key="student.id">
                        <TableCell>
                            <div class="flex items-center gap-3">
                                <Avatar class="size-8">
                                    <AvatarFallback class="bg-amber-500/15 text-xs font-bold text-amber-700">
                                        {{ initialsFor(student.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <span class="font-semibold text-foreground">{{ student.name }}</span>
                            </div>
                        </TableCell>
                        <TableCell>
                            <StatusBadge :status="student.status" />
                        </TableCell>
                        <TableCell>
                            <ProgressBar :value="student.progress_percentage" />
                        </TableCell>
                        <TableCell class="text-sm text-muted-foreground">{{ student.enrolled_at }}</TableCell>
                        <TableCell class="text-right">
                            <Button
                                v-if="student.status === 'Active'"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                @click="remove(student)"
                            >
                                Remove
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AuthenticatedLayout>
</template>
