<script setup lang="ts">
// =============================================================================
// AssessmentActionsCard Component
// Quick actions sidebar for assessment management
// =============================================================================

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Link, router } from '@inertiajs/vue3';
import { PlayCircle, Pencil, Eye, Archive, Trash2 } from 'lucide-vue-next';
import type { AssessmentStatus } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface Permissions {
    update: boolean;
    delete: boolean;
    publish: boolean;
    attempt: boolean;
}

interface Props {
    courseId: number;
    assessmentId: number;
    status: AssessmentStatus;
    can: Permissions;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

// =============================================================================
// Methods
// =============================================================================

const handlePublish = () => {
    if (confirm('Apakah Anda yakin ingin mempublikasikan penilaian ini?')) {
        router.post(`/courses/${props.courseId}/assessments/${props.assessmentId}/publish`);
    }
};

const handleUnpublish = () => {
    if (confirm('Apakah Anda yakin ingin membatalkan publikasi penilaian ini?')) {
        router.post(`/courses/${props.courseId}/assessments/${props.assessmentId}/unpublish`);
    }
};

const handleArchive = () => {
    if (confirm('Apakah Anda yakin ingin mengarsipkan penilaian ini?')) {
        router.post(`/courses/${props.courseId}/assessments/${props.assessmentId}/archive`);
    }
};

const handleDelete = () => {
    if (confirm('Apakah Anda yakin ingin menghapus penilaian ini? Tindakan ini tidak dapat dibatalkan.')) {
        router.delete(`/courses/${props.courseId}/assessments/${props.assessmentId}`);
    }
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Aksi Cepat</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
            <Link v-if="can.attempt" :href="`/courses/${courseId}/assessments/${assessmentId}/start`" class="w-full block">
                <Button class="w-full gap-2 bg-green-600 hover:bg-green-700">
                    <PlayCircle class="h-4 w-4" />
                    Mulai Penilaian
                </Button>
            </Link>

            <Link v-if="can.update" :href="`/courses/${courseId}/assessments/${assessmentId}/edit`" class="w-full block">
                <Button class="w-full gap-2">
                    <Pencil class="h-4 w-4" />
                    Edit Penilaian
                </Button>
            </Link>

            <Link v-if="can.update" :href="`/courses/${courseId}/assessments/${assessmentId}/questions`" class="w-full block">
                <Button class="w-full gap-2">
                    <Eye class="h-4 w-4" />
                    Lihat Pertanyaan
                </Button>
            </Link>

            <Button
                v-if="can.publish && status === 'draft'"
                type="button"
                class="w-full gap-2 bg-blue-600 hover:bg-blue-700"
                @click="handlePublish"
            >
                <Archive class="h-4 w-4" />
                Publikasikan
            </Button>

            <Button
                v-if="can.publish && status === 'published'"
                type="button"
                class="w-full gap-2 bg-yellow-600 hover:bg-yellow-700"
                @click="handleUnpublish"
            >
                <Archive class="h-4 w-4" />
                Batalkan Publikasi
            </Button>

            <Button
                v-if="can.publish && status !== 'archived'"
                type="button"
                variant="destructive"
                class="w-full gap-2"
                @click="handleArchive"
            >
                <Archive class="h-4 w-4" />
                Arsipkan
            </Button>

            <Button
                v-if="can.delete"
                type="button"
                variant="destructive"
                class="w-full gap-2"
                @click="handleDelete"
            >
                <Trash2 class="h-4 w-4" />
                Hapus Penilaian
            </Button>
        </CardContent>
    </Card>
</template>
