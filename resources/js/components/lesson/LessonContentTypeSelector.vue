<script setup lang="ts">
// =============================================================================
// LessonContentTypeSelector Component
// Radio button grid for selecting lesson content type
// =============================================================================

import {
    FileText,
    PlayCircle,
    Youtube,
    Headphones,
    FileDown,
    Video as VideoCall,
} from 'lucide-vue-next';
import type { ContentType } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface ContentTypeOption {
    value: ContentType;
    label: string;
    icon: typeof FileText;
    description: string;
}

// =============================================================================
// Component Setup
// =============================================================================

const modelValue = defineModel<ContentType>({ required: true });

// =============================================================================
// Data
// =============================================================================

const contentTypes: ContentTypeOption[] = [
    { value: 'text', label: 'Teks', icon: FileText, description: 'Konten berbasis teks dengan rich editor' },
    { value: 'video', label: 'Video', icon: PlayCircle, description: 'Upload video dari perangkat' },
    { value: 'youtube', label: 'YouTube', icon: Youtube, description: 'Embed video dari YouTube' },
    { value: 'audio', label: 'Audio', icon: Headphones, description: 'Upload file audio' },
    { value: 'document', label: 'Dokumen', icon: FileDown, description: 'Upload PDF atau dokumen lainnya' },
    { value: 'conference', label: 'Konferensi', icon: VideoCall, description: 'Sesi live meeting' },
];
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <label
            v-for="type in contentTypes"
            :key="type.value"
            class="group relative flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 p-5 transition-all hover:border-primary/50 hover:bg-muted/30"
            :class="modelValue === type.value ? 'border-primary bg-primary/5 shadow-sm' : 'border-transparent bg-muted/20'"
        >
            <input
                type="radio"
                v-model="modelValue"
                :value="type.value"
                class="sr-only"
            />
            <div
                class="flex h-12 w-12 items-center justify-center rounded-xl transition-colors"
                :class="modelValue === type.value
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary'"
            >
                <component :is="type.icon" class="h-6 w-6" />
            </div>
            <span class="text-sm font-semibold">{{ type.label }}</span>
            <span class="text-center text-xs text-muted-foreground leading-relaxed">{{ type.description }}</span>
            <div
                v-if="modelValue === type.value"
                class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
            >
                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
            </div>
        </label>
    </div>
</template>
