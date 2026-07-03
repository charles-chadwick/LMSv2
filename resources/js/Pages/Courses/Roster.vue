<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    course: { type: Object, required: true },
    students: { type: Array, required: true },
});

const remove = (student) => {
    if (! confirm(`Remove ${student.name} from "${props.course.title}"?`)) {
        return;
    }
    router.delete(route('enrollments.destroy', student.id), { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Roster — ${course.title}`" />

        <h1 class="mb-6 text-2xl font-semibold">Roster — {{ course.title }}</h1>

        <div v-if="students.length === 0" class="rounded border border-dashed p-8 text-center text-gray-500">
            No students enrolled yet.
        </div>

        <table v-else class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b text-gray-500">
                    <th class="py-2">Student</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Progress</th>
                    <th class="py-2">Enrolled</th>
                    <th class="py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="student in students" :key="student.id" class="border-b">
                    <td class="py-3 font-medium">{{ student.name }}</td>
                    <td class="py-3">
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ student.status }}</span>
                    </td>
                    <td class="py-3">{{ student.progress_percentage }}%</td>
                    <td class="py-3">{{ student.enrolled_at }}</td>
                    <td class="py-3 text-right">
                        <button
                            v-if="student.status === 'Active'"
                            type="button"
                            class="text-red-600 hover:underline"
                            @click="remove(student)"
                        >
                            Remove
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </AuthenticatedLayout>
</template>
