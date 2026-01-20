<script setup lang="ts">
// =============================================================================
// AttemptCertificateCard Component
// Certificate display for passed attempts
// =============================================================================

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Award, Trophy } from 'lucide-vue-next';
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Sertifikat</CardTitle>
        </CardHeader>
        <CardContent class="flex flex-col items-center justify-center gap-4 py-8">
            <Award class="h-16 w-16 text-yellow-600" />
            <p class="text-center text-muted-foreground">
                Anda telah berhasil menyelesaikan penilaian ini dan memenuhi syarat untuk sertifikat.
            </p>
            <Button class="gap-2" variant="outline">
                <Trophy class="h-4 w-4" />
                Dapatkan Sertifikat
            </Button>
        </CardContent>
    </Card>
</template>
