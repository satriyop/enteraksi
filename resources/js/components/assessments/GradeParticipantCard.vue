<script setup lang="ts">
// =============================================================================
// GradeParticipantCard Component
// Displays participant information for grading
// =============================================================================

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { User, FileText, Clock } from 'lucide-vue-next';

// =============================================================================
// Types
// =============================================================================

interface AttemptUser {
    id: number;
    name: string;
    email: string;
}

interface Props {
    user: AttemptUser;
    attemptNumber: number;
    submittedAt: string;
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

// =============================================================================
// Methods
// =============================================================================

const formatDateTime = (date: string): string => {
    return new Date(date).toLocaleString('id-ID');
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Informasi Peserta</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex items-center gap-3">
                <User class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Nama</p>
                    <p class="font-medium">{{ user.name }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <FileText class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Email</p>
                    <p class="font-medium">{{ user.email }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Clock class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Percobaan Ke-</p>
                    <p class="font-medium">{{ attemptNumber }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Clock class="h-5 w-5 text-muted-foreground" />
                <div>
                    <p class="text-sm text-muted-foreground">Diserahkan</p>
                    <p class="font-medium">{{ formatDateTime(submittedAt) }}</p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
