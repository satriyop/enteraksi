<script setup lang="ts">
// =============================================================================
// CourseEnrollmentCard Component
// Displays enrollment status and actions in course detail sidebar
// =============================================================================

import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ProgressBar } from '@/components/features/shared';
import { CheckCircle, Eye } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface UserEnrollment {
    id: number;
    status: string;
    enrolled_at: string;
    progress_percentage: number;
}

interface Props {
    /** Course ID */
    courseId: number;
    /** User enrollment (null if not enrolled) */
    enrollment?: UserEnrollment | null;
    /** Whether user can enroll */
    canEnroll?: boolean;
    /** Number of preview lessons available */
    previewLessonsCount?: number;
    /** First lesson ID for "Continue Learning" button */
    firstLessonId?: number | null;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    enrollment: null,
    canEnroll: true,
    previewLessonsCount: 0,
    firstLessonId: null,
});

// =============================================================================
// State
// =============================================================================

const isEnrolling = ref(false);

// =============================================================================
// Computed
// =============================================================================

const isEnrolled = props.enrollment && props.enrollment.status === 'active';

// =============================================================================
// Methods
// =============================================================================

const handleEnroll = () => {
    isEnrolling.value = true;
    router.post(`/courses/${props.courseId}/enroll`, {}, {
        onFinish: () => {
            isEnrolling.value = false;
        },
    });
};

const handleUnenroll = () => {
    if (!confirm('Apakah Anda yakin ingin membatalkan pendaftaran dari kursus ini?')) {
        return;
    }
    router.delete(`/courses/${props.courseId}/unenroll`);
};
</script>

<template>
    <Card>
        <CardContent class="p-6">
            <!-- Already Enrolled -->
            <div v-if="isEnrolled" class="space-y-4">
                <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                    <CheckCircle class="h-5 w-5" />
                    <span class="font-medium">Anda sudah terdaftar</span>
                </div>
                <div class="text-sm text-muted-foreground">
                    Progress: {{ enrollment?.progress_percentage || 0 }}%
                </div>
                <ProgressBar
                    :value="enrollment?.progress_percentage || 0"
                    size="sm"
                />
                <Link
                    v-if="firstLessonId"
                    :href="`/courses/${courseId}/lessons/${firstLessonId}`"
                    class="block"
                >
                    <Button class="w-full" size="lg">
                        Lanjutkan Belajar
                    </Button>
                </Link>
                <Button
                    variant="outline"
                    class="w-full"
                    size="sm"
                    @click="handleUnenroll"
                >
                    Batalkan Pendaftaran
                </Button>
            </div>

            <!-- Not Enrolled -->
            <div v-else class="space-y-4">
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary mb-1">Gratis</div>
                    <p class="text-sm text-muted-foreground">Akses penuh ke semua materi</p>
                </div>
                <Button
                    v-if="canEnroll"
                    class="w-full"
                    size="lg"
                    @click="handleEnroll"
                    :disabled="isEnrolling"
                >
                    {{ isEnrolling ? 'Mendaftar...' : 'Daftar Sekarang' }}
                </Button>
                <p v-else class="text-sm text-center text-muted-foreground">
                    Kursus ini tidak tersedia untuk pendaftaran.
                </p>
                <div v-if="previewLessonsCount > 0" class="flex items-center gap-2 text-sm text-muted-foreground pt-2 border-t">
                    <Eye class="h-4 w-4 text-green-600" />
                    <span>{{ previewLessonsCount }} materi dapat dipreview gratis</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
