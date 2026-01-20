<script setup lang="ts">
// =============================================================================
// AssessmentSettingsCard Component
// Displays assessment configuration settings
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Clock, CheckCircle, FileText, Users, Check, X } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface Props {
    passingScore: number;
    maxAttempts: number;
    timeLimitMinutes: number | null;
    questionsCount: number;
    shuffleQuestions: boolean;
    showCorrectAnswers: boolean;
    allowReview: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Pengaturan Penilaian</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex items-center gap-3">
                    <CheckCircle class="h-5 w-5 text-muted-foreground" />
                    <div>
                        <p class="text-sm text-muted-foreground">Nilai Kelulusan</p>
                        <p class="font-medium">{{ passingScore }}%</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Users class="h-5 w-5 text-muted-foreground" />
                    <div>
                        <p class="text-sm text-muted-foreground">Jumlah Percobaan</p>
                        <p class="font-medium">{{ maxAttempts }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Clock class="h-5 w-5 text-muted-foreground" />
                    <div>
                        <p class="text-sm text-muted-foreground">Batas Waktu</p>
                        <p class="font-medium">
                            {{ timeLimitMinutes ? `${timeLimitMinutes} menit` : 'Tidak ada batas waktu' }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <FileText class="h-5 w-5 text-muted-foreground" />
                    <div>
                        <p class="text-sm text-muted-foreground">Jumlah Pertanyaan</p>
                        <p class="font-medium">{{ questionsCount }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-6 w-6 items-center justify-center rounded-full border">
                        <Check v-if="shuffleQuestions" class="h-4 w-4 text-green-600" />
                        <X v-else class="h-4 w-4 text-red-600" />
                    </div>
                    <div>
                        <p class="text-sm font-medium">Acak Pertanyaan</p>
                        <p class="text-sm text-muted-foreground">
                            {{ shuffleQuestions ? 'Aktif' : 'Nonaktif' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex h-6 w-6 items-center justify-center rounded-full border">
                        <Check v-if="showCorrectAnswers" class="h-4 w-4 text-green-600" />
                        <X v-else class="h-4 w-4 text-red-600" />
                    </div>
                    <div>
                        <p class="text-sm font-medium">Tampilkan Jawaban Benar</p>
                        <p class="text-sm text-muted-foreground">
                            {{ showCorrectAnswers ? 'Aktif' : 'Nonaktif' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex h-6 w-6 items-center justify-center rounded-full border">
                        <Check v-if="allowReview" class="h-4 w-4 text-green-600" />
                        <X v-else class="h-4 w-4 text-red-600" />
                    </div>
                    <div>
                        <p class="text-sm font-medium">Izinkan Review</p>
                        <p class="text-sm text-muted-foreground">
                            {{ allowReview ? 'Aktif' : 'Nonaktif' }}
                        </p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
