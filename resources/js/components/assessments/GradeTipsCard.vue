<script setup lang="ts">
// =============================================================================
// GradeTipsCard Component
// Displays helpful tips for graders
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Check, Award, AlertTriangle } from 'lucide-vue-next';
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Petunjuk Penilaian</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex items-start gap-3">
                <Check class="h-5 w-5 text-green-600 mt-0.5" />
                <div>
                    <p class="text-sm font-medium">Berikan umpan balik yang konstruktif</p>
                    <p class="text-sm text-muted-foreground">
                        Berikan umpan balik yang jelas dan bermanfaat untuk membantu peserta belajar.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <Award class="h-5 w-5 text-yellow-600 mt-0.5" />
                <div>
                    <p class="text-sm font-medium">Nilai dengan adil</p>
                    <p class="text-sm text-muted-foreground">
                        Gunakan kriteria penilaian yang konsisten untuk semua peserta.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <AlertTriangle class="h-5 w-5 text-blue-600 mt-0.5" />
                <div>
                    <p class="text-sm font-medium">Periksa dengan teliti</p>
                    <p class="text-sm text-muted-foreground">
                        Pastikan Anda telah menilai semua pertanyaan sebelum menyimpan.
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
