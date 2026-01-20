<script setup lang="ts">
// =============================================================================
// CourseInfoForm Component
// Form for editing course metadata and settings
// =============================================================================

import { ref } from 'vue';
import { update as updateCourse } from '@/actions/App/Http/Controllers/CourseController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Form, Link } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { formatDuration } from '@/lib/utils';
import type {
    Category,
    Tag,
    DifficultyLevel,
    CourseVisibility,
} from '@/types';

// =============================================================================
// Types
// =============================================================================

interface CourseData {
    id: number;
    title: string;
    short_description: string;
    long_description: string | null;
    objectives: string[];
    prerequisites: string[];
    category_id: number | null;
    difficulty_level: DifficultyLevel;
    visibility: CourseVisibility;
    manual_duration_minutes: number | null;
    estimated_duration_minutes: number;
    tags: Tag[];
}

interface Props {
    /** Course data to edit */
    course: CourseData;
    /** Available categories */
    categories: Category[];
    /** Available tags */
    tags: Tag[];
    /** Cancel URL */
    cancelUrl: string;
    /** Whether form can be edited */
    editable?: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    editable: true,
});

// =============================================================================
// State
// =============================================================================

const objectives = ref<string[]>(
    props.course.objectives?.length > 0 ? [...props.course.objectives] : ['']
);
const prerequisites = ref<string[]>(
    props.course.prerequisites?.length > 0 ? [...props.course.prerequisites] : ['']
);
const selectedTagIds = ref<number[]>(props.course.tags?.map(t => t.id) ?? []);

// =============================================================================
// Methods
// =============================================================================

const toggleTag = (tagId: number) => {
    const idx = selectedTagIds.value.indexOf(tagId);
    if (idx === -1) {
        selectedTagIds.value.push(tagId);
    } else {
        selectedTagIds.value.splice(idx, 1);
    }
};

const addObjective = () => objectives.value.push('');
const removeObjective = (idx: number) => {
    if (objectives.value.length > 1) objectives.value.splice(idx, 1);
};

const addPrerequisite = () => prerequisites.value.push('');
const removePrerequisite = (idx: number) => {
    if (prerequisites.value.length > 1) prerequisites.value.splice(idx, 1);
};
</script>

<template>
    <Form
        v-bind="updateCourse.form(course.id)"
        class="grid gap-6 lg:grid-cols-3"
        v-slot="{ errors, processing }"
    >
        <input type="hidden" name="_method" value="PUT" />

        <div class="space-y-6 lg:col-span-2">
            <!-- Basic Info -->
            <Card>
                <CardHeader>
                    <CardTitle>Informasi Dasar</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="title">Judul Kursus *</Label>
                        <Input
                            id="title"
                            name="title"
                            :default-value="course.title"
                            placeholder="Masukkan judul kursus"
                            required
                            :disabled="!editable"
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="short_description">Deskripsi Singkat *</Label>
                        <textarea
                            id="short_description"
                            name="short_description"
                            class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Deskripsi singkat tentang kursus"
                            required
                            :disabled="!editable"
                        >{{ course.short_description }}</textarea>
                        <InputError :message="errors.short_description" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="long_description">Deskripsi Lengkap</Label>
                        <textarea
                            id="long_description"
                            name="long_description"
                            class="flex min-h-32 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Deskripsi lengkap tentang kursus"
                            :disabled="!editable"
                        >{{ course.long_description }}</textarea>
                        <InputError :message="errors.long_description" />
                    </div>
                </CardContent>
            </Card>

            <!-- Objectives -->
            <Card>
                <CardHeader>
                    <CardTitle>Tujuan Pembelajaran</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="(objective, idx) in objectives" :key="idx" class="flex gap-2">
                        <Input
                            v-model="objectives[idx]"
                            :name="`objectives[${idx}]`"
                            :placeholder="`Tujuan ${idx + 1}`"
                            :disabled="!editable"
                        />
                        <Button
                            v-if="editable"
                            type="button"
                            variant="ghost"
                            size="icon"
                            @click="removeObjective(idx)"
                            :disabled="objectives.length === 1"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                    <Button v-if="editable" type="button" variant="outline" size="sm" @click="addObjective">
                        <Plus class="mr-2 h-4 w-4" />
                        Tambah Tujuan
                    </Button>
                </CardContent>
            </Card>

            <!-- Prerequisites -->
            <Card>
                <CardHeader>
                    <CardTitle>Prasyarat</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="(prereq, idx) in prerequisites" :key="idx" class="flex gap-2">
                        <Input
                            v-model="prerequisites[idx]"
                            :name="`prerequisites[${idx}]`"
                            :placeholder="`Prasyarat ${idx + 1}`"
                            :disabled="!editable"
                        />
                        <Button
                            v-if="editable"
                            type="button"
                            variant="ghost"
                            size="icon"
                            @click="removePrerequisite(idx)"
                            :disabled="prerequisites.length === 1"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                    <Button v-if="editable" type="button" variant="outline" size="sm" @click="addPrerequisite">
                        <Plus class="mr-2 h-4 w-4" />
                        Tambah Prasyarat
                    </Button>
                </CardContent>
            </Card>
        </div>

        <div class="space-y-6">
            <!-- Settings -->
            <Card>
                <CardHeader>
                    <CardTitle>Pengaturan</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="category_id">Kategori</Label>
                        <select
                            id="category_id"
                            name="category_id"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!editable"
                        >
                            <option value="">Pilih Kategori</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                                :selected="category.id === course.category_id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="difficulty_level">Tingkat Kesulitan *</Label>
                        <select
                            id="difficulty_level"
                            name="difficulty_level"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            required
                            :disabled="!editable"
                        >
                            <option value="beginner" :selected="course.difficulty_level === 'beginner'">Pemula</option>
                            <option value="intermediate" :selected="course.difficulty_level === 'intermediate'">Menengah</option>
                            <option value="advanced" :selected="course.difficulty_level === 'advanced'">Lanjutan</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="visibility">Visibilitas *</Label>
                        <select
                            id="visibility"
                            name="visibility"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            required
                            :disabled="!editable"
                        >
                            <option value="public" :selected="course.visibility === 'public'">Publik</option>
                            <option value="restricted" :selected="course.visibility === 'restricted'">Terbatas</option>
                            <option value="hidden" :selected="course.visibility === 'hidden'">Tersembunyi</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="manual_duration_minutes">Durasi Manual (menit)</Label>
                        <Input
                            id="manual_duration_minutes"
                            name="manual_duration_minutes"
                            type="number"
                            min="0"
                            :default-value="course.manual_duration_minutes ?? undefined"
                            placeholder="Kosongkan untuk hitung otomatis"
                            :disabled="!editable"
                        />
                        <p class="text-xs text-muted-foreground">
                            Durasi terhitung: {{ formatDuration(course.estimated_duration_minutes, 'long') }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Tags -->
            <Card>
                <CardHeader>
                    <CardTitle>Tag</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="tag in tags"
                            :key="tag.id"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition-colors"
                            :class="[
                                selectedTagIds.includes(tag.id) ? 'border-primary bg-primary/10' : '',
                                !editable ? 'cursor-not-allowed opacity-50' : ''
                            ]"
                            @click="editable && toggleTag(tag.id)"
                        >
                            <input
                                type="checkbox"
                                name="tag_ids[]"
                                :value="tag.id"
                                :checked="selectedTagIds.includes(tag.id)"
                                :disabled="!editable"
                                class="sr-only"
                            />
                            {{ tag.name }}
                        </label>
                    </div>
                </CardContent>
            </Card>

            <!-- Actions -->
            <div v-if="editable" class="flex gap-2">
                <Link :href="cancelUrl" class="flex-1">
                    <Button type="button" variant="outline" class="w-full">
                        Batal
                    </Button>
                </Link>
                <Button type="submit" class="flex-1" :disabled="processing">
                    {{ processing ? 'Menyimpan...' : 'Simpan' }}
                </Button>
            </div>
        </div>
    </Form>
</template>
