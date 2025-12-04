<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Upload, FileText, CheckCircle, XCircle, Calendar, AlertCircle } from 'lucide-vue-next';

interface Props {
    courseId: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    success: [];
}>();

const isOpen = ref(false);
const selectedFile = ref<File | null>(null);
const message = ref('');
const expiresAt = ref('');
const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});
const importResults = ref<{
    success: number;
    failed: number;
    errors?: string[];
} | null>(null);

const fileInputRef = ref<HTMLInputElement | null>(null);

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
            errors.value = { file: 'File harus berformat CSV' };
            selectedFile.value = null;
            return;
        }
        selectedFile.value = file;
        errors.value = {};
    }
};

const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};

const submitImport = () => {
    if (!selectedFile.value) {
        errors.value = { file: 'Silakan pilih file CSV terlebih dahulu' };
        return;
    }

    isSubmitting.value = true;
    errors.value = {};
    importResults.value = null;

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    if (message.value) {
        formData.append('message', message.value);
    }
    if (expiresAt.value) {
        formData.append('expires_at', expiresAt.value);
    }

    router.post(`/courses/${props.courseId}/invitations/bulk`, formData, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: (page: any) => {
            const results = page.props.flash?.importResults;
            if (results) {
                importResults.value = results;
                if (results.failed === 0) {
                    setTimeout(() => {
                        resetForm();
                        isOpen.value = false;
                        emit('success');
                    }, 2000);
                }
            } else {
                resetForm();
                isOpen.value = false;
                emit('success');
            }
        },
        onError: (formErrors) => {
            errors.value = formErrors as Record<string, string>;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const resetForm = () => {
    selectedFile.value = null;
    message.value = '';
    expiresAt.value = '';
    errors.value = {};
    importResults.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const closeDialog = () => {
    if (!isSubmitting.value) {
        resetForm();
        isOpen.value = false;
    }
};

const minDate = computed(() => new Date().toISOString().split('T')[0]);
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button variant="outline">
                <Upload class="h-4 w-4" />
                Import CSV
            </Button>
        </DialogTrigger>
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Import Undangan dari CSV</DialogTitle>
                <DialogDescription>
                    Upload file CSV berisi daftar email peserta untuk diundang ke kursus ini.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitImport">
                <div>
                    <Label for="csv-file">File CSV</Label>
                    <div class="mt-2">
                        <input
                            id="csv-file"
                            ref="fileInputRef"
                            type="file"
                            accept=".csv"
                            class="hidden"
                            @change="handleFileSelect"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full justify-start"
                            :disabled="isSubmitting"
                            @click="fileInputRef?.click()"
                        >
                            <FileText class="h-4 w-4" />
                            {{ selectedFile ? selectedFile.name : 'Pilih file CSV...' }}
                        </Button>
                        <p v-if="selectedFile" class="mt-2 text-sm text-muted-foreground">
                            Ukuran: {{ formatFileSize(selectedFile.size) }}
                        </p>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Format: email,nama (opsional). Contoh: user@example.com,John Doe
                    </p>
                    <InputError :message="errors.file" class="mt-1" />
                </div>

                <div>
                    <Label for="bulk-message">Pesan (Opsional)</Label>
                    <Textarea
                        id="bulk-message"
                        v-model="message"
                        placeholder="Pesan yang akan dikirim ke semua peserta..."
                        class="mt-2 min-h-[80px]"
                        :disabled="isSubmitting"
                        :aria-invalid="!!errors.message"
                    />
                    <InputError :message="errors.message" class="mt-1" />
                </div>

                <div>
                    <Label for="bulk-expires-at" class="mb-2 flex items-center gap-2">
                        <Calendar class="h-4 w-4" />
                        Masa Berlaku (Opsional)
                    </Label>
                    <Input
                        id="bulk-expires-at"
                        v-model="expiresAt"
                        type="date"
                        :min="minDate"
                        class="mt-2"
                        :disabled="isSubmitting"
                        :aria-invalid="!!errors.expires_at"
                    />
                    <InputError :message="errors.expires_at" class="mt-1" />
                </div>

                <div
                    v-if="importResults"
                    class="rounded-lg border p-4"
                    :class="
                        importResults.failed === 0
                            ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30'
                            : 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'
                    "
                >
                    <div class="flex items-start gap-3">
                        <CheckCircle
                            v-if="importResults.failed === 0"
                            class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <AlertCircle
                            v-else
                            class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"
                        />
                        <div class="flex-1">
                            <p class="font-medium" :class="importResults.failed === 0 ? 'text-emerald-900 dark:text-emerald-100' : 'text-amber-900 dark:text-amber-100'">
                                Hasil Import
                            </p>
                            <div class="mt-2 space-y-1 text-sm" :class="importResults.failed === 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                                <p>Berhasil: {{ importResults.success }}</p>
                                <p v-if="importResults.failed > 0">
                                    Gagal: {{ importResults.failed }}
                                </p>
                            </div>
                            <div
                                v-if="importResults.errors && importResults.errors.length > 0"
                                class="mt-3 max-h-32 overflow-auto rounded bg-white/50 p-2 text-xs dark:bg-black/20"
                            >
                                <p class="mb-1 font-medium">Error:</p>
                                <ul class="list-inside list-disc space-y-0.5">
                                    <li v-for="(error, idx) in importResults.errors" :key="idx">
                                        {{ error }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="isSubmitting" @click="closeDialog">
                        Batal
                    </Button>
                    <Button type="submit" :disabled="isSubmitting || !selectedFile">
                        <Upload class="h-4 w-4" />
                        {{ isSubmitting ? 'Mengimport...' : 'Import Undangan' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
