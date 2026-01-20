<script setup lang="ts">
// =============================================================================
// CourseInvitationCard Component
// Displays a course invitation with accept/decline actions
// =============================================================================

import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Clock,
    BookOpen,
    Mail,
    Check,
    X,
    Eye,
    AlertCircle,
    Loader2,
} from 'lucide-vue-next';
import { useTimeAgo } from '@vueuse/core';
import { formatDuration, difficultyLabel, difficultyColor } from '@/lib/utils';
import type { DifficultyLevel } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface InvitedCourse {
    id: number; // invitation_id
    course_id: number;
    title: string;
    slug: string;
    short_description: string;
    thumbnail_path: string | null;
    difficulty_level: DifficultyLevel;
    duration: number;
    instructor: string;
    category: string | null;
    lessons_count: number;
    invited_by: string;
    message: string | null;
    invited_at: string;
    expires_at: string | null;
}

interface Props {
    /** The invitation data */
    invitation: InvitedCourse;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

// =============================================================================
// State
// =============================================================================

const isProcessing = ref(false);

// =============================================================================
// Computed
// =============================================================================

const formatRelativeTime = (dateString: string) => {
    return useTimeAgo(new Date(dateString)).value;
};

const getDaysUntilExpiry = (expiresAt: string | null): number | null => {
    if (!expiresAt) return null;
    const expiry = new Date(expiresAt);
    const now = new Date();
    const diffTime = expiry.getTime() - now.getTime();
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
};

const isExpiringSoon = (expiresAt: string | null): boolean => {
    const days = getDaysUntilExpiry(expiresAt);
    return days !== null && days <= 7 && days >= 0;
};

// =============================================================================
// Methods
// =============================================================================

const acceptInvitation = () => {
    isProcessing.value = true;
    router.post(
        `/invitations/${props.invitation.id}/accept`,
        {},
        {
            preserveScroll: true,
            onFinish: () => (isProcessing.value = false),
        },
    );
};

const declineInvitation = () => {
    if (!confirm('Yakin ingin menolak undangan ini?')) return;

    isProcessing.value = true;
    router.post(
        `/invitations/${props.invitation.id}/decline`,
        {},
        {
            preserveScroll: true,
            onFinish: () => (isProcessing.value = false),
        },
    );
};
</script>

<template>
    <Card class="overflow-hidden border-2 border-primary/30 bg-gradient-to-r from-primary/5 to-transparent">
        <!-- Header -->
        <div class="flex items-center gap-2 bg-primary/10 px-4 py-2">
            <Mail class="h-4 w-4 text-primary" />
            <span class="text-sm">
                Diundang oleh <strong>{{ invitation.invited_by }}</strong>
            </span>
            <span class="ml-auto text-xs text-muted-foreground">
                {{ formatRelativeTime(invitation.invited_at) }}
            </span>
        </div>

        <!-- Body -->
        <CardContent class="p-4">
            <div class="flex gap-4">
                <!-- Thumbnail -->
                <Link :href="`/courses/${invitation.course_id}`" class="shrink-0">
                    <div class="h-20 w-32 overflow-hidden rounded-lg bg-muted">
                        <img
                            v-if="invitation.thumbnail_path"
                            :src="invitation.thumbnail_path"
                            :alt="invitation.title"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <BookOpen class="h-8 w-8 text-muted-foreground" />
                        </div>
                    </div>
                </Link>

                <!-- Details -->
                <div class="min-w-0 flex-1">
                    <Link :href="`/courses/${invitation.course_id}`">
                        <h3 class="line-clamp-1 font-semibold hover:text-primary">
                            {{ invitation.title }}
                        </h3>
                    </Link>
                    <p class="text-sm text-muted-foreground">{{ invitation.instructor }}</p>
                    <div class="mt-2 flex flex-wrap gap-3 text-xs text-muted-foreground">
                        <span class="flex items-center gap-1">
                            <Clock class="h-3 w-3" />
                            {{ formatDuration(invitation.duration, 'long') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <BookOpen class="h-3 w-3" />
                            {{ invitation.lessons_count }} materi
                        </span>
                        <Badge :class="difficultyColor(invitation.difficulty_level)" class="text-xs">
                            {{ difficultyLabel(invitation.difficulty_level) }}
                        </Badge>
                    </div>
                </div>
            </div>

            <!-- Message -->
            <div
                v-if="invitation.message"
                class="mt-3 rounded-lg border-l-4 border-primary/50 bg-muted/50 p-3"
            >
                <p class="text-sm italic text-muted-foreground">"{{ invitation.message }}"</p>
            </div>

            <!-- Expiration Warning -->
            <div
                v-if="invitation.expires_at && isExpiringSoon(invitation.expires_at)"
                class="mt-3 flex items-center gap-2 text-amber-600 dark:text-amber-500"
            >
                <AlertCircle class="h-4 w-4" />
                <span class="text-sm font-medium">
                    Berakhir dalam {{ getDaysUntilExpiry(invitation.expires_at) }} hari
                </span>
            </div>
        </CardContent>

        <!-- Actions -->
        <div class="flex gap-2 border-t bg-muted/30 px-4 py-3">
            <Button
                @click="acceptInvitation"
                :disabled="isProcessing"
                class="flex-1"
            >
                <Loader2
                    v-if="isProcessing"
                    class="mr-1 h-4 w-4 animate-spin"
                />
                <Check v-else class="mr-1 h-4 w-4" />
                Terima Undangan
            </Button>
            <Button
                @click="declineInvitation"
                :disabled="isProcessing"
                variant="outline"
            >
                <X class="mr-1 h-4 w-4" />
                Tolak
            </Button>
            <Link :href="`/courses/${invitation.course_id}`">
                <Button variant="ghost" size="icon">
                    <Eye class="h-4 w-4" />
                </Button>
            </Link>
        </div>
    </Card>
</template>
