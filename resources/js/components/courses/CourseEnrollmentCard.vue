<script setup lang="ts">
// =============================================================================
// CourseEnrollmentCard Component
// Displays enrollment status and actions in course detail sidebar
// =============================================================================

import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ProgressBar } from '@/components/features/shared';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { CheckCircle, ClipboardCheck, Eye, RotateCcw, Trophy, XCircle } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface UserEnrollment {
    id: number;
    status: 'active' | 'completed' | 'dropped';
    enrolled_at: string;
    progress_percentage: number;
}

interface AssessmentStats {
    total: number;
    passed: number;
    pending: number;
    required_total: number;
    required_passed: number;
    required_pending: number;
    all_required_passed: boolean;
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
    /** Assessment completion stats for enrolled users */
    assessmentStats?: AssessmentStats | null;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    enrollment: null,
    canEnroll: true,
    previewLessonsCount: 0,
    firstLessonId: null,
    assessmentStats: null,
});

// =============================================================================
// State
// =============================================================================

const isEnrolling = ref(false);
const isReenrolling = ref(false);
const showReenrollDialog = ref(false);

// =============================================================================
// Computed
// =============================================================================

const isActive = computed(() => props.enrollment?.status === 'active');
const isCompleted = computed(() => props.enrollment?.status === 'completed');
const isDropped = computed(() => props.enrollment?.status === 'dropped');
const hasProgress = computed(() => (props.enrollment?.progress_percentage ?? 0) > 0);
const hasPendingAssessments = computed(() =>
    props.assessmentStats && props.assessmentStats.required_pending > 0
);
const hasAssessments = computed(() =>
    props.assessmentStats && props.assessmentStats.required_total > 0
);

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

const handleReenroll = (preserveProgress: boolean) => {
    isReenrolling.value = true;
    showReenrollDialog.value = false;
    router.post(`/courses/${props.courseId}/reenroll`, {
        preserve_progress: preserveProgress,
    }, {
        onFinish: () => {
            isReenrolling.value = false;
        },
    });
};

const openReenrollDialog = () => {
    if (hasProgress.value) {
        // Show dialog to choose preserve/reset
        showReenrollDialog.value = true;
    } else {
        // No progress to preserve, just re-enroll
        handleReenroll(false);
    }
};
</script>

<template>
    <Card>
        <CardContent class="p-6">
            <!-- Active Enrollment -->
            <div v-if="isActive" class="space-y-4">
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
                <!-- Assessment Status -->
                <div v-if="hasAssessments" class="rounded-lg bg-muted/50 p-3 text-sm">
                    <div class="flex items-center gap-2 mb-2">
                        <ClipboardCheck class="h-4 w-4 text-muted-foreground" />
                        <span class="font-medium">Assessment</span>
                    </div>
                    <div v-if="hasPendingAssessments" class="text-orange-600 dark:text-orange-400">
                        {{ assessmentStats?.required_pending }} assessment wajib belum selesai
                    </div>
                    <div v-else class="text-green-600 dark:text-green-400">
                        Semua assessment wajib sudah lulus
                    </div>
                    <div class="text-xs text-muted-foreground mt-1">
                        {{ assessmentStats?.required_passed }}/{{ assessmentStats?.required_total }} assessment wajib lulus
                    </div>
                </div>
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

            <!-- Completed Enrollment -->
            <div v-else-if="isCompleted" class="space-y-4">
                <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                    <Trophy class="h-5 w-5" />
                    <span class="font-medium">Kursus Selesai!</span>
                </div>
                <div class="text-sm text-muted-foreground">
                    Anda telah menyelesaikan kursus ini dengan progress 100%
                </div>
                <ProgressBar :value="100" size="sm" />
                <Link
                    v-if="firstLessonId"
                    :href="`/courses/${courseId}/lessons/${firstLessonId}`"
                    class="block"
                >
                    <Button class="w-full" size="lg" variant="secondary">
                        Tinjau Kembali Materi
                    </Button>
                </Link>
            </div>

            <!-- Dropped Enrollment -->
            <div v-else-if="isDropped" class="space-y-4">
                <div class="flex items-center gap-2 text-orange-600 dark:text-orange-400">
                    <XCircle class="h-5 w-5" />
                    <span class="font-medium">Pendaftaran Dibatalkan</span>
                </div>
                <div v-if="hasProgress" class="text-sm text-muted-foreground">
                    Progress sebelumnya: {{ enrollment?.progress_percentage }}%
                </div>
                <ProgressBar
                    v-if="hasProgress"
                    :value="enrollment?.progress_percentage || 0"
                    size="sm"
                    class="opacity-50"
                />
                <Button
                    class="w-full"
                    size="lg"
                    @click="openReenrollDialog"
                    :disabled="isReenrolling"
                >
                    <RotateCcw class="mr-2 h-4 w-4" />
                    {{ isReenrolling ? 'Mendaftar Ulang...' : 'Lanjutkan Belajar' }}
                </Button>
                <p class="text-xs text-center text-muted-foreground">
                    {{ hasProgress ? 'Anda dapat melanjutkan atau memulai dari awal' : 'Mulai belajar lagi' }}
                </p>
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

    <!-- Re-enrollment Dialog -->
    <Dialog v-model:open="showReenrollDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Lanjutkan Belajar</DialogTitle>
                <DialogDescription>
                    Anda memiliki progress {{ enrollment?.progress_percentage }}% di kursus ini.
                    Pilih bagaimana Anda ingin melanjutkan:
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-3 py-4">
                <Button
                    class="w-full justify-start h-auto py-3"
                    variant="outline"
                    @click="handleReenroll(true)"
                >
                    <div class="text-left">
                        <div class="font-medium">Lanjutkan dari Progress Sebelumnya</div>
                        <div class="text-xs text-muted-foreground">
                            Mulai dari {{ enrollment?.progress_percentage }}% progress yang sudah ada
                        </div>
                    </div>
                </Button>
                <Button
                    class="w-full justify-start h-auto py-3"
                    variant="outline"
                    @click="handleReenroll(false)"
                >
                    <div class="text-left">
                        <div class="font-medium">Mulai dari Awal</div>
                        <div class="text-xs text-muted-foreground">
                            Reset progress dan mulai dari 0%
                        </div>
                    </div>
                </Button>
            </div>
            <DialogFooter>
                <Button variant="ghost" @click="showReenrollDialog = false">
                    Batal
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
