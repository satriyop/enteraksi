<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import FormSection from '@/components/crud/FormSection.vue';
import { Separator } from '@/components/ui/separator';
import InviteLearnerForm from './InviteLearnerForm.vue';
import CsvImportDialog from './CsvImportDialog.vue';
import InvitationsList from './InvitationsList.vue';
import { Mail, Users, Upload } from 'lucide-vue-next';

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
    canInvite?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canInvite: true,
});

const handleInvitationSuccess = () => {
    router.reload({ only: ['invitations'], preserveScroll: true });
};

const handleInvitationDeleted = (invitationId: number) => {
    router.reload({ only: ['invitations'], preserveScroll: true });
};
</script>

<template>
    <div class="space-y-6">
        <FormSection
            v-if="canInvite"
            title="Undang Peserta"
            description="Kirim undangan kepada peserta untuk bergabung dengan kursus ini"
        >
            <div class="space-y-6">
                <InviteLearnerForm :course-id="courseId" @success="handleInvitationSuccess" />

                <div class="flex items-center gap-4">
                    <Separator class="flex-1" />
                    <span class="text-sm text-muted-foreground">atau</span>
                    <Separator class="flex-1" />
                </div>

                <div class="flex items-center justify-between rounded-lg border bg-muted/30 p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                            <Upload class="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <p class="font-medium">Import Massal dari CSV</p>
                            <p class="text-sm text-muted-foreground">
                                Undang banyak peserta sekaligus menggunakan file CSV
                            </p>
                        </div>
                    </div>
                    <CsvImportDialog :course-id="courseId" @success="handleInvitationSuccess" />
                </div>
            </div>
        </FormSection>

        <FormSection
            title="Daftar Undangan"
            :description="`${invitations.length} undangan telah dikirim`"
        >
            <InvitationsList
                :course-id="courseId"
                :invitations="invitations"
                :can-delete="canInvite"
                @deleted="handleInvitationDeleted"
            />
        </FormSection>
    </div>
</template>
