<script setup lang="ts">
// =============================================================================
// LessonOverviewTab Component
// Displays lesson overview information with description and metadata
// =============================================================================

import { Badge } from '@/components/ui/badge';
import { Clock, PlayCircle, FileText, Headphones, FileDown, Video as VideoCall, Youtube } from 'lucide-vue-next';
import { formatDuration, contentTypeLabel } from '@/lib/utils';
import type { ContentType } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface Props {
    /** Lesson description */
    description: string | null;
    /** Estimated duration in minutes */
    estimatedDurationMinutes: number | null;
    /** Content type */
    contentType: ContentType;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

// =============================================================================
// Helpers
// =============================================================================

const lessonTypeIcon = (type: string) => {
    const icons: Record<string, typeof PlayCircle> = {
        video: PlayCircle,
        youtube: Youtube,
        audio: Headphones,
        document: FileDown,
        conference: VideoCall,
        text: FileText,
    };
    return icons[type] || FileText;
};
</script>

<template>
    <div class="space-y-4">
        <div>
            <h2 class="font-semibold mb-2">Tentang Materi Ini</h2>
            <p v-if="description" class="text-muted-foreground">
                {{ description }}
            </p>
            <p v-else class="text-muted-foreground italic">
                Tidak ada deskripsi untuk materi ini.
            </p>
        </div>
        <div class="flex flex-wrap gap-4 pt-2">
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Clock class="h-4 w-4" />
                <span>{{ formatDuration(estimatedDurationMinutes, 'long') }}</span>
            </div>
            <Badge variant="outline" class="gap-1">
                <component :is="lessonTypeIcon(contentType)" class="h-3 w-3" />
                {{ contentTypeLabel(contentType) }}
            </Badge>
        </div>
    </div>
</template>
