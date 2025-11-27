<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next';

interface Props {
    currentPage: number;
    totalPages: number;
    canGoNext: boolean;
    canGoPrev: boolean;
    progressPercentage: number;
    isSaving?: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    'prev': [];
    'next': [];
    'first': [];
    'last': [];
}>();
</script>

<template>
    <div class="space-y-3">
        <!-- Navigation Controls -->
        <div class="flex items-center justify-between gap-2">
            <!-- Previous buttons -->
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    class="h-8 w-8 hidden sm:flex"
                    :disabled="currentPage === 1"
                    @click="emit('first')"
                    title="Halaman pertama (Home)"
                >
                    <ChevronsLeft class="h-4 w-4" />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1"
                    :disabled="!canGoPrev"
                    @click="emit('prev')"
                >
                    <ChevronLeft class="h-4 w-4" />
                    <span class="hidden sm:inline">Sebelumnya</span>
                </Button>
            </div>

            <!-- Page indicator -->
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <span class="font-medium text-foreground">{{ currentPage }}</span>
                <span>/</span>
                <span>{{ totalPages }}</span>
                <span v-if="isSaving" class="text-xs text-muted-foreground ml-2">
                    Menyimpan...
                </span>
            </div>

            <!-- Next buttons -->
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1"
                    :disabled="!canGoNext"
                    @click="emit('next')"
                >
                    <span class="hidden sm:inline">Selanjutnya</span>
                    <ChevronRight class="h-4 w-4" />
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    class="h-8 w-8 hidden sm:flex"
                    :disabled="currentPage === totalPages"
                    @click="emit('last')"
                    title="Halaman terakhir (End)"
                >
                    <ChevronsRight class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="space-y-1">
            <div class="flex items-center justify-between text-xs text-muted-foreground">
                <span>Progress halaman</span>
                <span>{{ progressPercentage }}%</span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                <div
                    class="h-full bg-primary rounded-full transition-all duration-300"
                    :style="{ width: `${progressPercentage}%` }"
                />
            </div>
        </div>

        <!-- Keyboard shortcuts hint (desktop only) -->
        <div class="hidden sm:flex items-center justify-center gap-4 text-xs text-muted-foreground">
            <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-muted rounded text-[10px] font-mono">←</kbd>
                <span>Sebelumnya</span>
            </span>
            <span class="flex items-center gap-1">
                <kbd class="px-1.5 py-0.5 bg-muted rounded text-[10px] font-mono">→</kbd>
                <span>Selanjutnya</span>
            </span>
        </div>
    </div>
</template>
