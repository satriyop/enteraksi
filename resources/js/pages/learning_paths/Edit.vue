<script setup lang="ts">
// =============================================================================
// Learning Path Edit Page
// Edit existing learning path with courses
// =============================================================================

import { show, update } from '@/actions/App/Http/Controllers/LearningPathController';
import PageHeader from '@/components/crud/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import LearningPathObjectivesField from '@/components/learning_paths/LearningPathObjectivesField.vue';
import LearningPathCoursesManager from '@/components/learning_paths/LearningPathCoursesManager.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type DifficultyLevel } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface CoursePivot {
    is_required: boolean;
    min_completion_percentage: number;
    prerequisites: string | null;
}

interface EditableCourse {
    id: number;
    title: string;
    description: string | null;
    slug: string;
    estimated_duration: number;
    difficulty_level: DifficultyLevel;
    thumbnail_url: string | null;
    pivot: CoursePivot;
}

interface EditableLearningPath {
    id: number;
    title: string;
    description: string | null;
    objectives: string[];
    slug: string;
    estimated_duration: number;
    difficulty_level: DifficultyLevel | 'expert';
    thumbnail_url: string | null;
    courses: EditableCourse[];
}

interface SelectedCourse {
    id: number;
    title: string;
    is_required: boolean;
    min_completion_percentage: number;
    prerequisites: string | null;
}

interface Props {
    learningPath: EditableLearningPath;
    availableCourses: EditableCourse[];
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Jalur Pembelajaran', href: '/learning-paths' },
    { title: props.learningPath.title, href: show(props.learningPath.id).url },
];

// =============================================================================
// Form
// =============================================================================

const form = useForm({
    title: props.learningPath.title,
    description: props.learningPath.description ?? '',
    objectives: props.learningPath.objectives || [''],
    estimated_duration: props.learningPath.estimated_duration,
    difficulty_level: props.learningPath.difficulty_level || 'beginner',
    thumbnail: null as File | null,
    courses: [] as SelectedCourse[],
    _method: 'PUT',
});

// =============================================================================
// Course Management
// =============================================================================

const availableCoursesList = ref(
    props.availableCourses
        .filter(course => !props.learningPath.courses.some(lpCourse => lpCourse.id === course.id))
        .map(course => ({ id: course.id, title: course.title }))
);

const selectedCourses = ref<SelectedCourse[]>(
    props.learningPath.courses.map(course => ({
        id: course.id,
        title: course.title,
        is_required: course.pivot.is_required,
        min_completion_percentage: course.pivot.min_completion_percentage || 80,
        prerequisites: course.pivot.prerequisites,
    }))
);

const handleAddCourse = (course: { id: number; title: string }) => {
    selectedCourses.value.push({
        id: course.id,
        title: course.title,
        is_required: true,
        min_completion_percentage: 80,
        prerequisites: null,
    });
    const index = availableCoursesList.value.findIndex(c => c.id === course.id);
    if (index !== -1) availableCoursesList.value.splice(index, 1);
};

const handleRemoveCourse = (course: SelectedCourse) => {
    const originalCourse = props.availableCourses.find(c => c.id === course.id);
    if (originalCourse) {
        availableCoursesList.value.push({ id: originalCourse.id, title: originalCourse.title });
    }
    const index = selectedCourses.value.findIndex(c => c.id === course.id);
    if (index !== -1) selectedCourses.value.splice(index, 1);
};

// =============================================================================
// Submit
// =============================================================================

const submit = () => {
    form.courses = selectedCourses.value;
    form.post(update(props.learningPath.id).url, {
        preserveScroll: true,
        onError: (errors) => console.log('Validation errors:', errors),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit: ${learningPath.title}`" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
            <PageHeader
                title="Edit Jalur Pembelajaran"
                :description="learningPath.title"
                :back-href="show(learningPath.id).url"
                back-label="Kembali"
            />

            <Card>
                <CardHeader>
                    <CardTitle>Informasi Jalur Pembelajaran</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-6" @submit.prevent="submit">
                        <div class="grid gap-2">
                            <Label for="title">Judul *</Label>
                            <Input id="title" v-model="form.title" type="text" required autofocus />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Deskripsi</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                rows="4"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <LearningPathObjectivesField v-model="form.objectives" :error="form.errors.objectives" />

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="estimated_duration">Durasi Perkiraan (menit)</Label>
                                <Input id="estimated_duration" v-model="form.estimated_duration" type="number" min="1" />
                                <InputError :message="form.errors.estimated_duration" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="difficulty_level">Tingkat Kesulitan</Label>
                                <select
                                    id="difficulty_level"
                                    v-model="form.difficulty_level"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="beginner">Pemula</option>
                                    <option value="intermediate">Menengah</option>
                                    <option value="advanced">Lanjutan</option>
                                    <option value="expert">Ahli</option>
                                </select>
                                <InputError :message="form.errors.difficulty_level" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="thumbnail">Thumbnail</Label>
                            <Input
                                id="thumbnail"
                                type="file"
                                accept="image/*"
                                @input="form.thumbnail = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                            <InputError :message="form.errors.thumbnail" />
                        </div>

                        <LearningPathCoursesManager
                            :available-courses="availableCoursesList"
                            v-model:selected-courses="selectedCourses"
                            @add-course="handleAddCourse"
                            @remove-course="handleRemoveCourse"
                        />

                        <div class="flex justify-end gap-4 mt-6">
                            <Link :href="show(learningPath.id).url">
                                <Button type="button" variant="outline">Batal</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                                Perbarui Jalur Pembelajaran
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
