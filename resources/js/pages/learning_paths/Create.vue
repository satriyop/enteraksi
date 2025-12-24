<script setup lang="ts">
import { store } from '@/actions/App/Http/Controllers/LearningPathController';
import PageHeader from '@/components/crud/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import Draggable from 'vuedraggable';
import { ArrowLeft, Plus, X, GripVertical, Save, Loader2 } from 'lucide-vue-next';

interface Course {
    id: number;
    title: string;
    description: string;
    slug: string;
    estimated_duration: number;
    difficulty_level: string;
    thumbnail_url: string;
    is_required?: boolean;
    min_completion_percentage?: number;
    prerequisites?: string | null;
}

interface Props {
    courses: Course[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Jalur Pembelajaran',
        href: '/learning-paths',
    },
    {
        title: 'Buat Jalur Pembelajaran',
        href: '/learning-paths/create',
    },
];

interface LearningPathForm {
  title: string;
  description: string;
  objectives: string[];
  estimated_duration: number;
  difficulty_level: string;
  thumbnail: File | null;
  courses: Array<{
    id: number;
    title: string;
    is_required: boolean;
    min_completion_percentage: number;
    prerequisites: string | null;
  }>;
}

const form = useForm<LearningPathForm>({
  title: '',
  description: '',
  objectives: [''],
  estimated_duration: 0,
  difficulty_level: 'beginner',
  thumbnail: null,
  courses: [],
});

const availableCourses = ref<Course[]>(props.courses.map(course => ({
  ...course,
  is_required: true,
  min_completion_percentage: 80,
  prerequisites: null
})));

const selectedCourses = ref<Array<{
  id: number;
  title: string;
  is_required: boolean;
  min_completion_percentage: number;
  prerequisites: string | null;
}>>([]);

const addCourse = (course: Course) => {
  selectedCourses.value.push({
    id: course.id,
    title: course.title,
    is_required: true,
    min_completion_percentage: 80,
    prerequisites: null
  });

  // Remove from available
  const index = availableCourses.value.findIndex(c => c.id === course.id);
  if (index !== -1) {
    availableCourses.value.splice(index, 1);
  }
};

const removeCourse = (course: { id: number; title: string }) => {
  // Add back to available
  const originalCourse = props.courses.find(c => c.id === course.id);
  if (originalCourse) {
    const courseWithDefaults = {
      ...originalCourse,
      is_required: true,
      min_completion_percentage: 80,
      prerequisites: null
    } as Course;
    availableCourses.value.push(courseWithDefaults);
  }

  // Remove from selected
  const index = selectedCourses.value.findIndex(c => c.id === course.id);
  if (index !== -1) {
    selectedCourses.value.splice(index, 1);
  }
};

const addObjective = () => {
  form.objectives.push('');
};

const removeObjective = (index: number) => {
  if (form.objectives.length > 1) {
    form.objectives.splice(index, 1);
  }
};

const submit = () => {
  form.courses = selectedCourses.value;
  form.post(store().url, {
    onSuccess: () => {
      form.reset();
      selectedCourses.value = [];
      availableCourses.value = props.courses.map(course => ({
        ...course,
        is_required: true,
        min_completion_percentage: 80,
        prerequisites: null
      }));
    }
  });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Buat Jalur Pembelajaran" />

        <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="'/learning-paths'">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold">Buat Jalur Pembelajaran</h1>
                        </div>
                        <p class="text-muted-foreground">Buat jalur pembelajaran baru</p>
                    </div>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Informasi Jalur Pembelajaran</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid gap-2">
                            <Label for="title">Judul *</Label>
                            <Input
                                id="title"
                                v-model="form.title"
                                type="text"
                                required
                                autofocus
                            />
                            <InputError class="mt-2" :message="form.errors.title" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Deskripsi</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                rows="4"
                            />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>

                        <div class="space-y-3">
                            <Label>Tujuan Pembelajaran</Label>
                            <div v-for="(objective, index) in form.objectives" :key="index" class="flex gap-2">
                                <Input
                                    v-model="form.objectives[index]"
                                    type="text"
                                    class="flex-1"
                                    placeholder="Masukkan tujuan pembelajaran"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    @click="removeObjective(index)"
                                    :disabled="form.objectives.length === 1"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                            <Button type="button" variant="outline" size="sm" @click="addObjective">
                                <Plus class="mr-2 h-4 w-4" />
                                Tambah Tujuan
                            </Button>
                            <InputError class="mt-2" :message="form.errors.objectives" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="estimated_duration">Durasi Perkiraan (menit)</Label>
                                <Input
                                    id="estimated_duration"
                                    v-model="form.estimated_duration"
                                    type="number"
                                    min="1"
                                />
                                <InputError class="mt-2" :message="form.errors.estimated_duration" />
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
                                <InputError class="mt-2" :message="form.errors.difficulty_level" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="thumbnail">Thumbnail</Label>
                            <Input
                                id="thumbnail"
                                type="file"
                                @input="form.thumbnail = $event.target.files[0]"
                                accept="image/*"
                            />
                            <InputError class="mt-2" :message="form.errors.thumbnail" />
                        </div>

                        <Card class="mt-6">
                            <CardHeader>
                                <CardTitle>Kursus dalam Jalur Pembelajaran</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="mb-4">
                                    <Label>Kursus Tersedia</Label>
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <div v-if="availableCourses.length === 0" class="text-gray-500 dark:text-gray-400">
                                            Tidak ada kursus tersedia
                                        </div>
                                        <div v-else class="space-y-2">
                                            <div
                                                v-for="course in availableCourses"
                                                :key="course.id"
                                                class="flex justify-between items-center p-2 border border-gray-200 dark:border-gray-700 rounded"
                                            >
                                                <span>{{ course.title }}</span>
                                                <Button @click="addCourse(course)" size="sm">
                                                    Add
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <Label>Kursus Terpilih (Seret untuk mengurutkan)</Label>
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <Draggable
                                            v-model="selectedCourses"
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
                                                                        id="required"
                                                                    />
                                                                    <Label for="required">Wajib</Label>
                                                                </div>
                                                                <div>
                                                                    <Label for="min_completion">Kelulusan Minimum (%)</Label>
                                                                    <Input
                                                                        id="min_completion"
                                                                        v-model="element.min_completion_percentage"
                                                                        type="number"
                                                                        class="w-24"
                                                                        min="1"
                                                                        max="100"
                                                                    />
                                                                </div>
                                                                <div>
                                                                    <Label for="prerequisites">Prasyarat (JSON)</Label>
                                                                    <Input
                                                                        id="prerequisites"
                                                                        v-model="element.prerequisites"
                                                                        type="text"
                                                                        class="w-full"
                                                                        placeholder='{"completed_courses": [1, 2]}'
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <button
                                                            @click="removeCourse(element)"
                                                            class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                                        >
                                                            Hapus
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </Draggable>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <div class="flex justify-end gap-4 mt-6">
                            <Link :href="'/learning-paths'">
                                <Button type="button" variant="outline">
                                    Batal
                                </Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                                Buat Jalur Pembelajaran
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>