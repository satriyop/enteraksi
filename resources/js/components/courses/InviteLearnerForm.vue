<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import LearnerSearchCombobox from './LearnerSearchCombobox.vue';
import { UserPlus, Calendar } from 'lucide-vue-next';

interface Props {
    courseId: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    success: [];
}>();

const selectedUserId = ref<number | null>(null);
const message = ref('');
const expiresAt = ref('');
const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});

const submitInvitation = () => {
    if (!selectedUserId.value) {
        errors.value = { user_id: 'Silakan pilih peserta terlebih dahulu' };
        return;
    }

    isSubmitting.value = true;
    errors.value = {};

    router.post(
        `/courses/${props.courseId}/invitations`,
        {
            user_id: selectedUserId.value,
            message: message.value || null,
            expires_at: expiresAt.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedUserId.value = null;
                message.value = '';
                expiresAt.value = '';
                emit('success');
            },
            onError: (formErrors) => {
                errors.value = formErrors as Record<string, string>;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        }
    );
};

const minDate = new Date().toISOString().split('T')[0];
</script>

<template>
    <div class="space-y-6">
        <div>
            <h3 class="mb-4 text-lg font-semibold">Undang Peserta Baru</h3>
            <form class="space-y-4" @submit.prevent="submitInvitation">
                <div>
                    <LearnerSearchCombobox
                        v-model="selectedUserId"
                        :course-id="courseId"
                        label="Peserta"
                    />
                    <InputError :message="errors.user_id" class="mt-1" />
                </div>

                <div>
                    <Label for="message">Pesan (Opsional)</Label>
                    <Textarea
                        id="message"
                        v-model="message"
                        placeholder="Tambahkan pesan pribadi untuk undangan ini..."
                        class="mt-2 min-h-[100px]"
                        :aria-invalid="!!errors.message"
                    />
                    <InputError :message="errors.message" class="mt-1" />
                </div>

                <div>
                    <Label for="expires_at" class="mb-2 flex items-center gap-2">
                        <Calendar class="h-4 w-4" />
                        Masa Berlaku Undangan (Opsional)
                    </Label>
                    <Input
                        id="expires_at"
                        v-model="expiresAt"
                        type="date"
                        :min="minDate"
                        class="mt-2"
                        :aria-invalid="!!errors.expires_at"
                    />
                    <p class="mt-1 text-sm text-muted-foreground">
                        Kosongkan untuk undangan tanpa batas waktu
                    </p>
                    <InputError :message="errors.expires_at" class="mt-1" />
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="isSubmitting || !selectedUserId">
                        <UserPlus class="h-4 w-4" />
                        {{ isSubmitting ? 'Mengirim...' : 'Kirim Undangan' }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
