<script setup lang="ts">
// =============================================================================
// CoursePrerequisiteSelector Component
// User-friendly prerequisite selection for learning path courses
// =============================================================================

import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Lock } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Course {
    id: number;
    title: string;
}

interface Props {
    /** Current course ID */
    courseId: number;
    /** All courses in the learning path (in order) */
    allCourses: Course[];
    /** Index of current course in the list */
    currentIndex: number;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

// Selected prerequisite course IDs
const selectedIds = defineModel<number[]>({ default: () => [] });

// =============================================================================
// Computed
// =============================================================================

/**
 * Only courses BEFORE the current course can be prerequisites
 */
const availableCourses = computed(() => {
    return props.allCourses.slice(0, props.currentIndex);
});

/**
 * Check if a course is selected as prerequisite
 */
function isSelected(courseId: number): boolean {
    return selectedIds.value.includes(courseId);
}

/**
 * Toggle a course as prerequisite
 */
function toggle(courseId: number) {
    if (isSelected(courseId)) {
        selectedIds.value = selectedIds.value.filter(id => id !== courseId);
    } else {
        selectedIds.value = [...selectedIds.value, courseId];
    }
}
</script>

<template>
    <div class="space-y-2">
        <Label class="flex items-center gap-2">
            <Lock class="h-4 w-4 text-muted-foreground" />
            Prasyarat
        </Label>

        <!-- No available prerequisites (first course) -->
        <div
            v-if="availableCourses.length === 0"
            class="text-sm text-muted-foreground italic py-2"
        >
            Kursus pertama tidak memiliki prasyarat
        </div>

        <!-- Prerequisite checkboxes -->
        <div v-else class="space-y-2 rounded-md border p-3 bg-muted/30">
            <p class="text-xs text-muted-foreground mb-2">
                Pilih kursus yang harus diselesaikan terlebih dahulu:
            </p>
            <div
                v-for="course in availableCourses"
                :key="course.id"
                class="flex items-center gap-2"
            >
                <Checkbox
                    :id="`prereq-${courseId}-${course.id}`"
                    :checked="isSelected(course.id)"
                    @update:checked="toggle(course.id)"
                />
                <Label
                    :for="`prereq-${courseId}-${course.id}`"
                    class="text-sm font-normal cursor-pointer"
                >
                    {{ course.title }}
                </Label>
            </div>
        </div>

        <!-- Selected summary -->
        <div v-if="selectedIds.length > 0" class="text-xs text-muted-foreground">
            {{ selectedIds.length }} kursus prasyarat dipilih
        </div>
    </div>
</template>
