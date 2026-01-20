<script setup lang="ts">
// =============================================================================
// DocumentPreview Component
// Displays a preview card for non-PDF documents with download option
// =============================================================================

import type { Media } from '@/types';
import { Button } from '@/components/ui/button';
import { FileDown, Download } from 'lucide-vue-next';

interface Props {
    /** Document media object */
    media: Media;
}

const props = defineProps<Props>();

/** Get document type label from MIME type */
const getDocumentType = (mimeType: string): string => {
    if (mimeType === 'application/pdf') return 'PDF';
    if (mimeType.includes('word')) return 'Word';
    if (mimeType.includes('powerpoint') || mimeType.includes('presentation')) return 'PowerPoint';
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'Excel';
    return 'Dokumen';
};
</script>

<template>
    <div class="min-h-[50vh] flex items-center justify-center">
        <div class="rounded-xl border bg-muted/20 p-8 max-w-md">
            <div class="flex flex-col items-center gap-4 text-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <FileDown class="h-10 w-10" />
                </div>
                <div>
                    <p class="font-medium text-lg">{{ media.file_name }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ getDocumentType(media.mime_type) }} - {{ media.human_readable_size }}
                    </p>
                </div>
                <a :href="media.url" target="_blank">
                    <Button variant="outline" class="gap-2">
                        <Download class="h-4 w-4" />
                        Buka Dokumen
                    </Button>
                </a>
            </div>
        </div>
    </div>
</template>
