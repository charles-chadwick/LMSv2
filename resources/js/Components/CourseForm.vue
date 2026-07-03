<script setup>
defineProps({
    form: {
        type: Object,
        required: true,
    },
    levels: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div class="space-y-4">
        <div>
            <label for="title" class="block text-sm font-medium">Title</label>
            <input
                id="title"
                v-model="form.title"
                type="text"
                required
                class="mt-1 block w-full rounded border-gray-300 shadow-sm"
            />
            <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
        </div>

        <div>
            <label for="level" class="block text-sm font-medium">Level</label>
            <select
                id="level"
                v-model="form.level"
                required
                class="mt-1 block w-full rounded border-gray-300 shadow-sm"
            >
                <option value="" disabled>Select a level</option>
                <option v-for="level in levels" :key="level.value" :value="level.value">
                    {{ level.label }}
                </option>
            </select>
            <p v-if="form.errors.level" class="mt-1 text-sm text-red-600">{{ form.errors.level }}</p>
        </div>

        <div>
            <label for="summary" class="block text-sm font-medium">Summary</label>
            <input
                id="summary"
                v-model="form.summary"
                type="text"
                class="mt-1 block w-full rounded border-gray-300 shadow-sm"
            />
            <p v-if="form.errors.summary" class="mt-1 text-sm text-red-600">{{ form.errors.summary }}</p>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium">Description</label>
            <textarea
                id="description"
                v-model="form.description"
                rows="6"
                class="mt-1 block w-full rounded border-gray-300 shadow-sm"
            />
            <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
        </div>
    </div>
</template>
