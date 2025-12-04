<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTimeAgo } from '@vueuse/core';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    User,
    Mail,
    Clock,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Trash2,
    Calendar,
} from 'lucide-vue-next';

interface Invitation {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    status: 'pending' | 'accepted' | 'declined' | 'expired';
    message: string | null;
    invited_by: string;
    invited_at: string;
    expires_at: string | null;
    responded_at: string | null;
}

interface Props {
    courseId: number;
    invitations: Invitation[];
    canDelete?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canDelete: true,
});

const emit = defineEmits<{
    deleted: [invitationId: number];
}>();

const statusConfig = (status: Invitation['status']) => {
    switch (status) {
        case 'accepted':
            return {
                label: 'Diterima',
                variant: 'default' as const,
                class: 'bg-emerald-500 hover:bg-emerald-500',
                icon: CheckCircle,
            };
        case 'declined':
            return {
                label: 'Ditolak',
                variant: 'destructive' as const,
                class: '',
                icon: XCircle,
            };
        case 'expired':
            return {
                label: 'Kedaluwarsa',
                variant: 'outline' as const,
                class: '',
                icon: Clock,
            };
        case 'pending':
        default:
            return {
                label: 'Menunggu',
                variant: 'secondary' as const,
                class: '',
                icon: Clock,
            };
    }
};

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const isExpiringSoon = (expiresAt: string | null) => {
    if (!expiresAt) return false;
    const expiryDate = new Date(expiresAt);
    const now = new Date();
    const daysUntilExpiry = Math.floor(
        (expiryDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24)
    );
    return daysUntilExpiry <= 3 && daysUntilExpiry > 0;
};

const isExpired = (expiresAt: string | null) => {
    if (!expiresAt) return false;
    return new Date(expiresAt) < new Date();
};

const cancelInvitation = (invitation: Invitation) => {
    if (
        confirm(
            `Apakah Anda yakin ingin membatalkan undangan untuk ${invitation.user.name}?`
        )
    ) {
        router.delete(`/courses/${props.courseId}/invitations/${invitation.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('deleted', invitation.id);
            },
        });
    }
};

const sortedInvitations = computed(() => {
    return [...props.invitations].sort((a, b) => {
        const statusOrder = { pending: 0, accepted: 1, declined: 2, expired: 3 };
        const statusDiff = statusOrder[a.status] - statusOrder[b.status];
        if (statusDiff !== 0) return statusDiff;
        return new Date(b.invited_at).getTime() - new Date(a.invited_at).getTime();
    });
});
</script>

<template>
    <div class="space-y-4">
        <div v-if="invitations.length === 0" class="py-12 text-center">
            <Mail class="mx-auto h-12 w-12 text-muted-foreground/50" />
            <p class="mt-4 text-muted-foreground">Belum ada undangan yang dikirim</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="invitation in sortedInvitations"
                :key="invitation.id"
                class="rounded-lg border bg-card p-4 transition-colors hover:bg-accent/50"
            >
                <div class="flex items-start gap-4">
                    <Avatar class="h-10 w-10">
                        <AvatarFallback class="bg-primary/10 text-sm font-medium text-primary">
                            {{ getInitials(invitation.user.name) }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h4 class="truncate font-semibold">
                                        {{ invitation.user.name }}
                                    </h4>
                                    <Badge
                                        :variant="statusConfig(invitation.status).variant"
                                        :class="statusConfig(invitation.status).class"
                                        class="shrink-0"
                                    >
                                        <component
                                            :is="statusConfig(invitation.status).icon"
                                            class="mr-1 h-3 w-3"
                                        />
                                        {{ statusConfig(invitation.status).label }}
                                    </Badge>
                                </div>
                                <p class="mt-0.5 truncate text-sm text-muted-foreground">
                                    {{ invitation.user.email }}
                                </p>
                            </div>

                            <Button
                                v-if="canDelete && invitation.status === 'pending'"
                                variant="ghost"
                                size="sm"
                                class="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="cancelInvitation(invitation)"
                            >
                                <Trash2 class="h-4 w-4" />
                                <span class="sr-only">Batalkan undangan</span>
                            </Button>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground">
                            <span class="flex items-center gap-1.5">
                                <User class="h-3.5 w-3.5" />
                                Diundang oleh {{ invitation.invited_by }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <Clock class="h-3.5 w-3.5" />
                                {{ useTimeAgo(invitation.invited_at).value }}
                            </span>
                            <span v-if="invitation.expires_at" class="flex items-center gap-1.5">
                                <Calendar class="h-3.5 w-3.5" />
                                <span
                                    :class="{
                                        'text-amber-600 dark:text-amber-400':
                                            isExpiringSoon(invitation.expires_at) &&
                                            !isExpired(invitation.expires_at),
                                        'text-destructive': isExpired(invitation.expires_at),
                                    }"
                                >
                                    <template v-if="isExpired(invitation.expires_at)">
                                        Kedaluwarsa
                                    </template>
                                    <template
                                        v-else-if="isExpiringSoon(invitation.expires_at)"
                                    >
                                        Berlaku hingga {{ formatDate(invitation.expires_at) }}
                                    </template>
                                    <template v-else>
                                        Berlaku hingga {{ formatDate(invitation.expires_at) }}
                                    </template>
                                </span>
                            </span>
                        </div>

                        <div
                            v-if="isExpiringSoon(invitation.expires_at) && invitation.status === 'pending'"
                            class="mt-3 flex items-start gap-2 rounded-md bg-amber-50 p-2 text-xs text-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
                        >
                            <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <span>Undangan ini akan segera kedaluwarsa</span>
                        </div>

                        <div
                            v-if="invitation.message"
                            class="mt-3 rounded-md bg-muted/50 p-3 text-sm"
                        >
                            <p class="whitespace-pre-wrap text-muted-foreground">
                                {{ invitation.message }}
                            </p>
                        </div>

                        <div
                            v-if="invitation.responded_at"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            <span v-if="invitation.status === 'accepted'">
                                Diterima {{ useTimeAgo(invitation.responded_at).value }}
                            </span>
                            <span v-else-if="invitation.status === 'declined'">
                                Ditolak {{ useTimeAgo(invitation.responded_at).value }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
