<script setup lang="ts">
// =============================================================================
// LearningPathCourseList Component
// Draggable list of selected courses with settings
// =============================================================================

import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Draggable from 'vuedraggable';
import CoursePrerequisiteSelector from './CoursePrerequisiteSelector.vue';

// =============================================================================
// Types
// =============================================================================

interface CoursePrerequisites {
    completed_courses: number[];
}

interface SelectedCourse {
    id: number;
    title: string;
    is_required: boolean;
    min_completion_percentage: number;
    prerequisites: CoursePrerequisites | null;
}

// =============================================================================
// Component Setup
// =============================================================================

const courses = defineModel<SelectedCourse[]>({ required: true });

const emit = defineEmits<{
    remove: [course: SelectedCourse];
}>();

// =============================================================================
// Prerequisite Helpers
// =============================================================================

/**
 * Get prerequisite IDs for a course
 */
function getPrerequisiteIds(course: SelectedCourse): number[] {
    return course.prerequisites?.completed_courses ?? [];
}

/**
 * Update prerequisites for a course
 */
function updatePrerequisites(course: SelectedCourse, ids: number[]) {
    course.prerequisites = ids.length > 0 ? { completed_courses: ids } : null;
}

/**
 * Get courses available for selection (for prerequisite selector)
 */
const coursesForSelector = computed(() =>
    courses.value.map(c => ({ id: c.id, title: c.title }))
);
</script>

<template>
    <div>
        <Label>Kursus Terpilih (Seret untuk mengurutkan)</Label>
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div v-if="courses.length === 0" class="text-gray-500 dark:text-gray-400 text-center py-4">
                Belum ada kursus yang dipilih
            </div>
            <Draggable
                v-else
                v-model="courses"
                item-key="id"
                class="space-y-4"
            >
                <template #item="{ element, index }">
                    <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-900">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ index + 1 }}. {{ element.title }}
                                </h4>
                                <div class="mt-2 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            v-model:checked="element.is_required"
                                            :id="`required-${element.id}`"
                                        />
                                        <Label :for="`required-${element.id}`">Wajib</Label>
                                    </div>
                                    <div>
                                        <Label :for="`min_completion-${element.id}`">Kelulusan Minimum (%)</Label>
                                        <Input
                                            :id="`min_completion-${element.id}`"
                                            v-model="element.min_completion_percentage"
                                            type="number"
                                            class="w-24"
                                            min="1"
                                            max="100"
                                        />
                                    </div>
                                    <!-- Prerequisite Selector -->
                                    <CoursePrerequisiteSelector
                                        :course-id="element.id"
                                        :all-courses="coursesForSelector"
                                        :current-index="index"
                                        :model-value="getPrerequisiteIds(element)"
                                        @update:model-value="updatePrerequisites(element, $event)"
                                    />
                                </div>
                            </div>
                            <button
                                type="button"
                                class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                @click="emit('remove', element)"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </template>
            </Draggable>
        </div>
    </div>
</template>
