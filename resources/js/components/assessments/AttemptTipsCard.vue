<script setup lang="ts">
// =============================================================================
// AttemptTipsCard Component
// Displays helpful tips for completing the assessment
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Check, Clock, Upload } from 'lucide-vue-next';
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Petunjuk</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
            <div class="flex items-start gap-3">
                <Check class="h-5 w-5 text-green-600 mt-0.5" />
                <div>
                    <p class="text-sm font-medium">Jawab semua pertanyaan</p>
                    <p class="text-sm text-muted-foreground">
                        Pastikan Anda menjawab semua pertanyaan sebelum menyerahkan.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <Clock class="h-5 w-5 text-blue-600 mt-0.5" />
                <div>
                    <p class="text-sm font-medium">Kelola waktu dengan baik</p>
                    <p class="text-sm text-muted-foreground">
                        Perhatikan batas waktu jika ada.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <Upload class="h-5 w-5 text-purple-600 mt-0.5" />
                <div>
                    <p class="text-sm font-medium">Simpan pekerjaan Anda</p>
                    <p class="text-sm text-muted-foreground">
                        Gunakan fitur simpan draft untuk menghindari kehilangan data.
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
