# Phase 3: Component Architecture

## Overview

This phase addresses the critical issue of monolithic components in the codebase. Components like `lessons/Show.vue` (859 lines), `courses/Edit.vue` (857 lines), and `courses/Detail.vue` (812 lines) are too large to maintain, test, and understand effectively.

**Duration:** 3-4 weeks
**Risk Level:** Medium
**Dependencies:** Phase 1 (Type System), Phase 2 (Utilities)

---

## Current State Analysis

### Monolithic Components Identified

| Component | Lines | Issues |
|-----------|-------|--------|
| `pages/lessons/Show.vue` | 859 | Video player, progress tracking, content display, navigation all mixed |
| `pages/courses/Edit.vue` | 857 | Multi-step form, media uploads, section management combined |
| `pages/courses/Detail.vue` | 812 | Course info, enrollment, reviews, curriculum all in one |
| `pages/assessments/Take.vue` | 650+ | Timer, questions, answers, submission mixed |
| `pages/dashboard/Index.vue` | 500+ | Stats, progress, activity feed combined |
| `pages/admin/users/Index.vue` | 450+ | Table, filters, actions combined |

### Problems with Current Architecture

1. **Testability**: Can't unit test individual features
2. **Reusability**: Same UI patterns reimplemented multiple times
3. **Cognitive Load**: 800+ lines requires significant mental overhead
4. **Code Ownership**: Hard to assign ownership to specific features
5. **Bundle Size**: Can't lazy load sub-features

---

## Target Architecture

### Component Size Guidelines

| Component Type | Max Lines | Purpose |
|----------------|-----------|---------|
| Page Component | 200 | Orchestration, layout, data fetching |
| Feature Component | 150 | Single feature (form section, card) |
| UI Component | 100 | Reusable presentation (button, input) |
| Composable | 100 | Reusable logic |

### Directory Structure
```
resources/js/
├── components/
│   ├── ui/                          # shadcn-vue (existing)
│   └── features/                    # Feature-specific components
│       ├── course/
│       │   ├── CourseCard.vue
│       │   ├── CourseHeader.vue
│       │   ├── CourseCurriculum.vue
│       │   ├── CourseForm/
│       │   │   ├── index.vue        # Main form orchestrator
│       │   │   ├── BasicInfoStep.vue
│       │   │   ├── CurriculumStep.vue
│       │   │   ├── SettingsStep.vue
│       │   │   └── MediaUploadStep.vue
│       │   └── CourseStats.vue
│       ├── lesson/
│       │   ├── LessonCard.vue
│       │   ├── LessonContent/
│       │   │   ├── index.vue        # Content type router
│       │   │   ├── TextContent.vue
│       │   │   ├── VideoContent.vue
│       │   │   ├── YouTubeContent.vue
│       │   │   ├── AudioContent.vue
│       │   │   └── DocumentContent.vue
│       │   ├── LessonNavigation.vue
│       │   └── LessonProgress.vue
│       ├── assessment/
│       │   ├── AssessmentCard.vue
│       │   ├── QuestionRenderer/
│       │   │   ├── index.vue
│       │   │   ├── MultipleChoice.vue
│       │   │   ├── TrueFalse.vue
│       │   │   ├── ShortAnswer.vue
│       │   │   ├── Essay.vue
│       │   │   └── Matching.vue
│       │   ├── AssessmentTimer.vue
│       │   └── AssessmentResults.vue
│       └── shared/
│           ├── ProgressBar.vue
│           ├── StatusBadge.vue
│           ├── EmptyState.vue
│           └── LoadingState.vue
├── pages/                           # Thin orchestration pages
│   ├── courses/
│   ├── lessons/
│   └── assessments/
└── layouts/
```

---

## Implementation Steps

### Step 1: Create Shared Components

**File: `components/features/shared/StatusBadge.vue`**
```vue
<script setup lang="ts">
import { computed } from 'vue';
import type { CourseStatus, EnrollmentStatus, DifficultyLevel } from '@/types';
import {
    COURSE_STATUS_COLORS,
    ENROLLMENT_STATUS_COLORS,
    DIFFICULTY_COLORS
} from '@/lib/constants';
import {
    courseStatusLabel,
    enrollmentStatusLabel,
    difficultyLabel
} from '@/lib/formatters';

type StatusType = 'course' | 'enrollment' | 'difficulty';
type StatusValue = CourseStatus | EnrollmentStatus | DifficultyLevel;

interface Props {
    type: StatusType;
    status: StatusValue;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
});

const colorClasses = computed(() => {
    switch (props.type) {
        case 'course':
            return COURSE_STATUS_COLORS[props.status as CourseStatus];
        case 'enrollment':
            return ENROLLMENT_STATUS_COLORS[props.status as EnrollmentStatus];
        case 'difficulty':
            return DIFFICULTY_COLORS[props.status as DifficultyLevel];
        default:
            return { bg: 'bg-gray-100', text: 'text-gray-700' };
    }
});

const label = computed(() => {
    switch (props.type) {
        case 'course':
            return courseStatusLabel(props.status as CourseStatus);
        case 'enrollment':
            return enrollmentStatusLabel(props.status as EnrollmentStatus);
        case 'difficulty':
            return difficultyLabel(props.status as DifficultyLevel);
        default:
            return props.status;
    }
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm': return 'px-2 py-0.5 text-xs';
        case 'lg': return 'px-4 py-1.5 text-sm';
        default: return 'px-3 py-1 text-xs';
    }
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-full font-medium',
            colorClasses.bg,
            colorClasses.text,
            sizeClasses
        ]"
    >
        {{ label }}
    </span>
</template>
```

**File: `components/features/shared/EmptyState.vue`**
```vue
<script setup lang="ts">
import { type LucideIcon, Inbox } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

interface Props {
    title: string;
    description?: string;
    icon?: LucideIcon;
    actionLabel?: string;
    actionHref?: string;
}

const props = withDefaults(defineProps<Props>(), {
    icon: () => Inbox,
});

const emit = defineEmits<{
    action: [];
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center py-12 text-center">
        <div class="rounded-full bg-muted p-4 mb-4">
            <component :is="icon" class="h-8 w-8 text-muted-foreground" />
        </div>
        <h3 class="text-lg font-medium text-foreground mb-1">{{ title }}</h3>
        <p v-if="description" class="text-sm text-muted-foreground max-w-sm mb-4">
            {{ description }}
        </p>
        <Button
            v-if="actionLabel"
            :href="actionHref"
            @click="!actionHref && emit('action')"
        >
            {{ actionLabel }}
        </Button>
    </div>
</template>
```

**File: `components/features/shared/LoadingState.vue`**
```vue
<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next';

interface Props {
    text?: string;
    size?: 'sm' | 'md' | 'lg';
    inline?: boolean;
}

withDefaults(defineProps<Props>(), {
    text: 'Memuat...',
    size: 'md',
    inline: false,
});

const sizeClasses = {
    sm: 'h-4 w-4',
    md: 'h-6 w-6',
    lg: 'h-8 w-8',
};
</script>

<template>
    <div
        :class="[
            'flex items-center gap-2 text-muted-foreground',
            inline ? 'inline-flex' : 'flex-col justify-center py-8'
        ]"
    >
        <Loader2 :class="['animate-spin', sizeClasses[size]]" />
        <span v-if="text" class="text-sm">{{ text }}</span>
    </div>
</template>
```

### Step 2: Decompose Course Edit Form

**File: `components/features/course/CourseForm/index.vue`**
```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { Course, Category, Tag, CreateCourseData } from '@/types';
import { store } from '@/actions/App/Http/Controllers/CourseController';
import BasicInfoStep from './BasicInfoStep.vue';
import CurriculumStep from './CurriculumStep.vue';
import SettingsStep from './SettingsStep.vue';
import MediaUploadStep from './MediaUploadStep.vue';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';

interface Props {
    course?: Course;
    categories: Category[];
    tags: Tag[];
}

const props = defineProps<Props>();

const isEditing = computed(() => !!props.course?.id);

const form = useForm<CreateCourseData>({
    title: props.course?.title ?? '',
    description: props.course?.description ?? '',
    short_description: props.course?.short_description ?? '',
    category_id: props.course?.category_id ?? undefined,
    tag_ids: props.course?.tags?.map(t => t.id) ?? [],
    difficulty_level: props.course?.difficulty_level ?? 'beginner',
    price: props.course?.price ?? 0,
    thumbnail: null,
});

const activeTab = ref('basic');

const tabs = [
    { value: 'basic', label: 'Informasi Dasar' },
    { value: 'curriculum', label: 'Kurikulum' },
    { value: 'media', label: 'Media' },
    { value: 'settings', label: 'Pengaturan' },
];

function handleSubmit() {
    if (isEditing.value) {
        form.put(`/courses/${props.course!.id}`);
    } else {
        form.post(store.url());
    }
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-6">
        <Tabs v-model="activeTab" class="w-full">
            <TabsList class="grid w-full grid-cols-4">
                <TabsTrigger
                    v-for="tab in tabs"
                    :key="tab.value"
                    :value="tab.value"
                >
                    {{ tab.label }}
                </TabsTrigger>
            </TabsList>

            <TabsContent value="basic" class="mt-6">
                <BasicInfoStep
                    v-model:title="form.title"
                    v-model:description="form.description"
                    v-model:short-description="form.short_description"
                    :errors="form.errors"
                />
            </TabsContent>

            <TabsContent value="curriculum" class="mt-6">
                <CurriculumStep
                    v-if="isEditing"
                    :course-id="course!.id"
                    :sections="course?.sections ?? []"
                />
                <div v-else class="text-center py-8 text-muted-foreground">
                    Simpan kursus terlebih dahulu untuk menambah kurikulum
                </div>
            </TabsContent>

            <TabsContent value="media" class="mt-6">
                <MediaUploadStep
                    v-model:thumbnail="form.thumbnail"
                    :current-thumbnail="course?.thumbnail"
                    :errors="form.errors"
                />
            </TabsContent>

            <TabsContent value="settings" class="mt-6">
                <SettingsStep
                    v-model:category-id="form.category_id"
                    v-model:tag-ids="form.tag_ids"
                    v-model:difficulty-level="form.difficulty_level"
                    v-model:price="form.price"
                    :categories="categories"
                    :tags="tags"
                    :errors="form.errors"
                />
            </TabsContent>
        </Tabs>

        <div class="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" @click="$inertia.visit('/courses')">
                Batal
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Menyimpan...' : (isEditing ? 'Perbarui' : 'Simpan') }}
            </Button>
        </div>
    </form>
</template>
```

**File: `components/features/course/CourseForm/BasicInfoStep.vue`**
```vue
<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    errors?: Record<string, string>;
}

defineProps<Props>();

const title = defineModel<string>('title', { required: true });
const description = defineModel<string | null>('description');
const shortDescription = defineModel<string | null>('shortDescription');
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Informasi Dasar</CardTitle>
            <CardDescription>
                Berikan informasi dasar tentang kursus Anda
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="space-y-2">
                <Label for="title">Judul Kursus *</Label>
                <Input
                    id="title"
                    v-model="title"
                    placeholder="Masukkan judul kursus"
                    :class="{ 'border-destructive': errors?.title }"
                />
                <p v-if="errors?.title" class="text-sm text-destructive">
                    {{ errors.title }}
                </p>
            </div>

            <div class="space-y-2">
                <Label for="short_description">Deskripsi Singkat</Label>
                <Textarea
                    id="short_description"
                    v-model="shortDescription"
                    placeholder="Deskripsi singkat untuk preview"
                    rows="2"
                    :class="{ 'border-destructive': errors?.short_description }"
                />
                <p class="text-xs text-muted-foreground">
                    Maksimal 200 karakter
                </p>
            </div>

            <div class="space-y-2">
                <Label for="description">Deskripsi Lengkap</Label>
                <Textarea
                    id="description"
                    v-model="description"
                    placeholder="Jelaskan apa yang akan dipelajari peserta"
                    rows="6"
                    :class="{ 'border-destructive': errors?.description }"
                />
            </div>
        </CardContent>
    </Card>
</template>
```

**File: `components/features/course/CourseForm/SettingsStep.vue`**
```vue
<script setup lang="ts">
import type { Category, Tag, DifficultyLevel } from '@/types';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { X } from 'lucide-vue-next';

interface Props {
    categories: Category[];
    tags: Tag[];
    errors?: Record<string, string>;
}

defineProps<Props>();

const categoryId = defineModel<number | undefined>('categoryId');
const tagIds = defineModel<number[]>('tagIds', { default: () => [] });
const difficultyLevel = defineModel<DifficultyLevel>('difficultyLevel');
const price = defineModel<number>('price');

const difficultyOptions = [
    { value: 'beginner', label: 'Pemula' },
    { value: 'intermediate', label: 'Menengah' },
    { value: 'advanced', label: 'Lanjutan' },
];

function toggleTag(tagId: number) {
    const index = tagIds.value.indexOf(tagId);
    if (index > -1) {
        tagIds.value.splice(index, 1);
    } else {
        tagIds.value.push(tagId);
    }
}

function removeTag(tagId: number) {
    const index = tagIds.value.indexOf(tagId);
    if (index > -1) {
        tagIds.value.splice(index, 1);
    }
}
</script>

<template>
    <div class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Kategorisasi</CardTitle>
                <CardDescription>
                    Pilih kategori dan tag untuk membantu peserta menemukan kursus Anda
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="category">Kategori</Label>
                    <Select v-model="categoryId">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih kategori" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label>Tag</Label>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <Badge
                            v-for="tagId in tagIds"
                            :key="tagId"
                            variant="secondary"
                            class="cursor-pointer"
                            @click="removeTag(tagId)"
                        >
                            {{ tags.find(t => t.id === tagId)?.name }}
                            <X class="h-3 w-3 ml-1" />
                        </Badge>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="tag in tags.filter(t => !tagIds.includes(t.id))"
                            :key="tag.id"
                            variant="outline"
                            class="cursor-pointer hover:bg-secondary"
                            @click="toggleTag(tag.id)"
                        >
                            {{ tag.name }}
                        </Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Tingkat & Harga</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="difficulty">Tingkat Kesulitan</Label>
                    <Select v-model="difficultyLevel">
                        <SelectTrigger>
                            <SelectValue placeholder="Pilih tingkat" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in difficultyOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="price">Harga (Rp)</Label>
                    <Input
                        id="price"
                        v-model="price"
                        type="number"
                        min="0"
                        step="1000"
                        placeholder="0 untuk gratis"
                        :class="{ 'border-destructive': errors?.price }"
                    />
                    <p class="text-xs text-muted-foreground">
                        Masukkan 0 untuk kursus gratis
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
```

### Step 3: Decompose Lesson Content Display

**File: `components/features/lesson/LessonContent/index.vue`**
```vue
<script setup lang="ts">
import type { Lesson, LessonContent } from '@/types';
import {
    isTextContent,
    isVideoContent,
    isYouTubeContent,
    isAudioContent,
    isDocumentContent,
    isConferenceContent,
} from '@/types/guards';
import TextContentView from './TextContent.vue';
import VideoContentView from './VideoContent.vue';
import YouTubeContentView from './YouTubeContent.vue';
import AudioContentView from './AudioContent.vue';
import DocumentContentView from './DocumentContent.vue';
import ConferenceContentView from './ConferenceContent.vue';
import EmptyState from '@/components/features/shared/EmptyState.vue';
import { FileX } from 'lucide-vue-next';

interface Props {
    lesson: Lesson;
    onProgress?: (percentage: number, position?: number) => void;
}

defineProps<Props>();

defineEmits<{
    complete: [];
}>();
</script>

<template>
    <div class="lesson-content">
        <template v-if="lesson.content">
            <TextContentView
                v-if="isTextContent(lesson.content)"
                :content="lesson.content"
                @complete="$emit('complete')"
            />

            <VideoContentView
                v-else-if="isVideoContent(lesson.content)"
                :content="lesson.content"
                :lesson-id="lesson.id"
                @progress="onProgress?.($event.percentage, $event.position)"
                @complete="$emit('complete')"
            />

            <YouTubeContentView
                v-else-if="isYouTubeContent(lesson.content)"
                :content="lesson.content"
                @progress="onProgress?.($event)"
                @complete="$emit('complete')"
            />

            <AudioContentView
                v-else-if="isAudioContent(lesson.content)"
                :content="lesson.content"
                @progress="onProgress?.($event.percentage, $event.position)"
                @complete="$emit('complete')"
            />

            <DocumentContentView
                v-else-if="isDocumentContent(lesson.content)"
                :content="lesson.content"
                @complete="$emit('complete')"
            />

            <ConferenceContentView
                v-else-if="isConferenceContent(lesson.content)"
                :content="lesson.content"
            />
        </template>

        <EmptyState
            v-else
            :icon="FileX"
            title="Konten tidak tersedia"
            description="Konten untuk materi ini belum ditambahkan"
        />
    </div>
</template>
```

**File: `components/features/lesson/LessonContent/VideoContent.vue`**
```vue
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import type { VideoContent } from '@/types';
import { formatPlaybackTime } from '@/lib/formatters';
import { STORAGE_KEYS, DEBOUNCE } from '@/lib/constants';
import { debounce, safeJsonParse } from '@/lib/utils';

interface Props {
    content: VideoContent;
    lessonId: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    progress: [{ percentage: number; position: number }];
    complete: [];
}>();

const videoRef = ref<HTMLVideoElement | null>(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);

// Save progress to local storage
const savedProgressKey = `${STORAGE_KEYS.videoProgress}-${props.lessonId}`;

// Load saved position on mount
onMounted(() => {
    const saved = safeJsonParse<{ position: number }>(
        localStorage.getItem(savedProgressKey) || '{}',
        { position: 0 }
    );

    if (videoRef.value && saved.position > 0) {
        videoRef.value.currentTime = saved.position;
    }
});

// Save position on unmount
onUnmounted(() => {
    saveProgress();
});

// Debounced progress save
const saveProgress = debounce(() => {
    localStorage.setItem(
        savedProgressKey,
        JSON.stringify({ position: currentTime.value })
    );
}, DEBOUNCE.autosave);

function handleTimeUpdate() {
    if (!videoRef.value) return;

    currentTime.value = videoRef.value.currentTime;
    duration.value = videoRef.value.duration;

    const percentage = duration.value > 0
        ? (currentTime.value / duration.value) * 100
        : 0;

    emit('progress', {
        percentage,
        position: currentTime.value,
    });

    saveProgress();
}

function handleEnded() {
    localStorage.removeItem(savedProgressKey);
    emit('complete');
}

function togglePlay() {
    if (!videoRef.value) return;

    if (isPlaying.value) {
        videoRef.value.pause();
    } else {
        videoRef.value.play();
    }
}

function seek(seconds: number) {
    if (!videoRef.value) return;
    videoRef.value.currentTime = Math.max(0, Math.min(
        videoRef.value.currentTime + seconds,
        duration.value
    ));
}
</script>

<template>
    <div class="video-player relative bg-black rounded-lg overflow-hidden">
        <video
            ref="videoRef"
            :src="content.url"
            :poster="content.thumbnail"
            class="w-full aspect-video"
            preload="metadata"
            @timeupdate="handleTimeUpdate"
            @play="isPlaying = true"
            @pause="isPlaying = false"
            @ended="handleEnded"
            @loadedmetadata="duration = $event.target.duration"
        >
            <source :src="content.url" type="video/mp4" />
            Browser Anda tidak mendukung pemutar video.
        </video>

        <!-- Custom Controls -->
        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-4">
            <!-- Progress Bar -->
            <div class="mb-2">
                <input
                    type="range"
                    :value="currentTime"
                    :max="duration"
                    class="w-full h-1 bg-white/30 rounded-lg appearance-none cursor-pointer"
                    @input="videoRef && (videoRef.currentTime = Number($event.target.value))"
                />
            </div>

            <div class="flex items-center justify-between text-white">
                <div class="flex items-center gap-2">
                    <button
                        class="p-2 hover:bg-white/20 rounded-full transition"
                        @click="togglePlay"
                    >
                        <span v-if="isPlaying">⏸</span>
                        <span v-else>▶</span>
                    </button>

                    <button
                        class="p-1 hover:bg-white/20 rounded transition text-sm"
                        @click="seek(-10)"
                    >
                        -10s
                    </button>

                    <button
                        class="p-1 hover:bg-white/20 rounded transition text-sm"
                        @click="seek(10)"
                    >
                        +10s
                    </button>

                    <span class="text-sm">
                        {{ formatPlaybackTime(currentTime) }} / {{ formatPlaybackTime(duration) }}
                    </span>
                </div>

                <button
                    class="p-2 hover:bg-white/20 rounded-full transition"
                    @click="videoRef?.requestFullscreen()"
                >
                    ⛶
                </button>
            </div>
        </div>
    </div>
</template>
```

### Step 4: Decompose Assessment Question Renderer

**File: `components/features/assessment/QuestionRenderer/index.vue`**
```vue
<script setup lang="ts">
import type { Question } from '@/types';
import MultipleChoiceQuestion from './MultipleChoice.vue';
import TrueFalseQuestion from './TrueFalse.vue';
import ShortAnswerQuestion from './ShortAnswer.vue';
import EssayQuestion from './Essay.vue';
import MatchingQuestion from './Matching.vue';
import OrderingQuestion from './Ordering.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    question: Question;
    questionNumber: number;
    readonly?: boolean;
    showFeedback?: boolean;
}

defineProps<Props>();

const answer = defineModel<string | string[] | Record<string, string>>('answer');

const componentMap = {
    multiple_choice: MultipleChoiceQuestion,
    true_false: TrueFalseQuestion,
    short_answer: ShortAnswerQuestion,
    essay: EssayQuestion,
    matching: MatchingQuestion,
    ordering: OrderingQuestion,
};
</script>

<template>
    <Card class="question-card">
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between">
                <CardTitle class="text-base font-medium">
                    <span class="text-muted-foreground mr-2">{{ questionNumber }}.</span>
                    {{ question.question_text }}
                </CardTitle>
                <span class="text-sm text-muted-foreground whitespace-nowrap ml-4">
                    {{ question.points }} poin
                </span>
            </div>
        </CardHeader>

        <CardContent>
            <component
                :is="componentMap[question.type]"
                v-model="answer"
                :question="question"
                :readonly="readonly"
                :show-feedback="showFeedback"
            />

            <!-- Feedback Section -->
            <div
                v-if="showFeedback && question.explanation"
                class="mt-4 p-3 bg-muted rounded-lg"
            >
                <p class="text-sm font-medium mb-1">Penjelasan:</p>
                <p class="text-sm text-muted-foreground">{{ question.explanation }}</p>
            </div>
        </CardContent>
    </Card>
</template>
```

**File: `components/features/assessment/QuestionRenderer/MultipleChoice.vue`**
```vue
<script setup lang="ts">
import { computed } from 'vue';
import type { Question, QuestionOption } from '@/types';
import { cn } from '@/lib/utils';
import { CheckCircle, XCircle } from 'lucide-vue-next';

interface Props {
    question: Question;
    readonly?: boolean;
    showFeedback?: boolean;
}

const props = defineProps<Props>();

const answer = defineModel<string>('modelValue');

const options = computed<QuestionOption[]>(() => props.question.options ?? []);

function isSelected(optionId: string): boolean {
    return answer.value === optionId;
}

function selectOption(optionId: string) {
    if (props.readonly) return;
    answer.value = optionId;
}

function getOptionClasses(option: QuestionOption): string {
    const base = 'p-4 rounded-lg border cursor-pointer transition-all';

    if (props.showFeedback) {
        if (option.is_correct) {
            return cn(base, 'border-green-500 bg-green-50');
        }
        if (isSelected(option.id) && !option.is_correct) {
            return cn(base, 'border-red-500 bg-red-50');
        }
    }

    if (isSelected(option.id)) {
        return cn(base, 'border-primary bg-primary/5');
    }

    return cn(base, 'border-border hover:border-primary/50');
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="option in options"
            :key="option.id"
            :class="getOptionClasses(option)"
            @click="selectOption(option.id)"
        >
            <div class="flex items-center gap-3">
                <div
                    :class="[
                        'w-5 h-5 rounded-full border-2 flex items-center justify-center',
                        isSelected(option.id) ? 'border-primary bg-primary' : 'border-muted-foreground'
                    ]"
                >
                    <div
                        v-if="isSelected(option.id)"
                        class="w-2 h-2 rounded-full bg-white"
                    />
                </div>

                <span class="flex-1">{{ option.text }}</span>

                <template v-if="showFeedback">
                    <CheckCircle
                        v-if="option.is_correct"
                        class="h-5 w-5 text-green-500"
                    />
                    <XCircle
                        v-else-if="isSelected(option.id) && !option.is_correct"
                        class="h-5 w-5 text-red-500"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
```

### Step 5: Refactored Page Component Example

**File: `pages/lessons/Show.vue` (Refactored - ~150 lines)**
```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import type { LessonShowResponse } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import LessonContent from '@/components/features/lesson/LessonContent/index.vue';
import LessonNavigation from '@/components/features/lesson/LessonNavigation.vue';
import LessonProgress from '@/components/features/lesson/LessonProgress.vue';
import CourseSidebar from '@/components/features/course/CourseSidebar.vue';
import { useLessonProgress } from '@/composables/features/useLessonProgress';

interface Props extends LessonShowResponse {}

const props = defineProps<Props>();

const {
    updateProgress,
    markCompleted,
    isCompleted,
    progressPercentage,
} = useLessonProgress(props.enrollment, props.lesson.id);

const pageTitle = computed(() =>
    `${props.lesson.title} - ${props.course.title}`
);

function handleProgress(percentage: number, position?: number) {
    updateProgress(percentage, position);
}

function handleComplete() {
    if (!isCompleted.value) {
        markCompleted();
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout>
        <div class="flex h-[calc(100vh-64px)]">
            <!-- Sidebar with course curriculum -->
            <CourseSidebar
                :course="course"
                :enrollment="enrollment"
                :current-lesson-id="lesson.id"
                class="w-80 border-r hidden lg:block overflow-y-auto"
            />

            <!-- Main content area -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Top navigation -->
                <LessonNavigation
                    :lesson="lesson"
                    :course="course"
                    :previous-lesson="previousLesson"
                    :next-lesson="nextLesson"
                    class="border-b px-6 py-3"
                />

                <!-- Lesson content -->
                <div class="flex-1 overflow-y-auto p-6">
                    <LessonContent
                        :lesson="lesson"
                        :on-progress="handleProgress"
                        @complete="handleComplete"
                    />
                </div>

                <!-- Bottom progress bar -->
                <LessonProgress
                    :progress="progressPercentage"
                    :is-completed="isCompleted"
                    :next-lesson="nextLesson"
                    class="border-t px-6 py-4"
                />
            </div>
        </div>
    </AppLayout>
</template>
```

---

## Component Guidelines

### Naming Conventions
| Type | Convention | Example |
|------|------------|---------|
| Page | `{Resource}/Index.vue`, `{Resource}/Show.vue` | `courses/Index.vue` |
| Feature | `{Resource}{Feature}.vue` | `CourseCard.vue` |
| Compound | `{Component}/{Part}.vue` | `CourseForm/BasicInfoStep.vue` |
| Shared | Descriptive name | `StatusBadge.vue`, `EmptyState.vue` |

### Props Guidelines
```typescript
// Good: Typed, documented, with defaults
interface Props {
    /** The course to display */
    course: Course;
    /** Whether the card is compact */
    compact?: boolean;
    /** Called when card is clicked */
    onSelect?: (course: Course) => void;
}

withDefaults(defineProps<Props>(), {
    compact: false,
});

// Bad: No types, no documentation
const props = defineProps(['course', 'compact', 'onSelect']);
```

### Event Guidelines
```typescript
// Good: Typed emit definitions
const emit = defineEmits<{
    select: [course: Course];
    delete: [id: number];
    update: [data: UpdateCourseData];
}>();

// Bad: No type definitions
const emit = defineEmits(['select', 'delete', 'update']);
```

---

## Checklist

### Shared Components
- [ ] Create `StatusBadge.vue`
- [ ] Create `EmptyState.vue`
- [ ] Create `LoadingState.vue`
- [ ] Create `ProgressBar.vue`

### Course Components
- [ ] Create `CourseCard.vue`
- [ ] Create `CourseHeader.vue`
- [ ] Create `CourseCurriculum.vue`
- [ ] Create `CourseSidebar.vue`
- [ ] Decompose `CourseForm` into steps

### Lesson Components
- [ ] Create `LessonContent/index.vue`
- [ ] Create `LessonContent/TextContent.vue`
- [ ] Create `LessonContent/VideoContent.vue`
- [ ] Create `LessonContent/YouTubeContent.vue`
- [ ] Create `LessonNavigation.vue`
- [ ] Create `LessonProgress.vue`

### Assessment Components
- [ ] Create `QuestionRenderer/index.vue`
- [ ] Create `QuestionRenderer/MultipleChoice.vue`
- [ ] Create `QuestionRenderer/TrueFalse.vue`
- [ ] Create `AssessmentTimer.vue`
- [ ] Create `AssessmentResults.vue`

### Page Refactoring
- [ ] Refactor `pages/courses/Edit.vue` (857 → ~150 lines)
- [ ] Refactor `pages/lessons/Show.vue` (859 → ~150 lines)
- [ ] Refactor `pages/courses/Detail.vue` (812 → ~150 lines)
- [ ] Refactor `pages/assessments/Take.vue` (650 → ~150 lines)
- [ ] Refactor `pages/dashboard/Index.vue` (500 → ~150 lines)

---

## Success Criteria

| Metric | Before | After |
|--------|--------|-------|
| Largest component | 859 lines | <200 lines |
| Average page size | 320 lines | <150 lines |
| Feature components | ~20 | 50+ |
| Component test coverage | 0% | 60%+ |

---

## Next Phase

After completing Component Architecture, proceed to [Phase 4: Composables Strategy](./04-COMPOSABLES.md).
