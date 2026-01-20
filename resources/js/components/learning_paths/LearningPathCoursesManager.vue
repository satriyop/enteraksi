<script setup lang="ts">
// =============================================================================
// LearningPathCoursesManager Component
// Combines course picker and selected courses list
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LearningPathCoursePicker from './LearningPathCoursePicker.vue';
import LearningPathCourseList from './LearningPathCourseList.vue';

// =============================================================================
// Types
// =============================================================================

interface AvailableCourse {
    id: number;
    title: string;
}

interface SelectedCourse {
    id: number;
    title: string;
    is_required: boolean;
    min_completion_percentage: number;
    prerequisites: string | null;
}

interface Props {
    availableCourses: AvailableCourse[];
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const selectedCourses = defineModel<SelectedCourse[]>('selectedCourses', { required: true });

const emit = defineEmits<{
    addCourse: [course: AvailableCourse];
    removeCourse: [course: SelectedCourse];
}>();
</script>

<template>
    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Kursus dalam Jalur Pembelajaran</CardTitle>
        </CardHeader>
        <CardContent>
            <LearningPathCoursePicker
                :courses="availableCourses"
                @add="emit('addCourse', $event)"
            />

            <LearningPathCourseList
                v-model="selectedCourses"
                @remove="emit('removeCourse', $event)"
            />
        </CardContent>
    </Card>
</template>
