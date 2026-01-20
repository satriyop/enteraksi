<script setup lang="ts">
// =============================================================================
// LearningPathCoursePicker Component
// Available courses list with add button
// =============================================================================

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

// =============================================================================
// Types
// =============================================================================

interface AvailableCourse {
    id: number;
    title: string;
}

interface Props {
    courses: AvailableCourse[];
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const emit = defineEmits<{
    add: [course: AvailableCourse];
}>();
</script>

<template>
    <div class="mb-4">
        <Label>Kursus Tersedia</Label>
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div v-if="courses.length === 0" class="text-gray-500 dark:text-gray-400">
                Tidak ada kursus tersedia
            </div>
            <div v-else class="space-y-2">
                <div
                    v-for="course in courses"
                    :key="course.id"
                    class="flex justify-between items-center p-2 border border-gray-200 dark:border-gray-700 rounded"
                >
                    <span>{{ course.title }}</span>
                    <Button size="sm" @click="emit('add', course)">
                        Tambah
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
