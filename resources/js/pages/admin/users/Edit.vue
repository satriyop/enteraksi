<script setup lang="ts">
// =============================================================================
// Admin Edit User Page
// Edit user details, change role (if not editing self)
// =============================================================================

import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, UserRole, UserWithDetails } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, BookOpen, GraduationCap, Calendar } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// =============================================================================
// Page-Specific Types
// =============================================================================

interface Props {
    user: UserWithDetails;
    canEditRole: boolean;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Pengguna', href: UserController.index().url },
    { title: 'Edit', href: '#' },
];

// =============================================================================
// Form State
// =============================================================================

const selectedRole = ref<UserRole>(props.user.role);

const roleOptions = [
    { value: 'learner', label: 'Peserta Didik' },
    { value: 'content_manager', label: 'Pengelola Konten' },
    { value: 'trainer', label: 'Pelatih' },
    { value: 'lms_admin', label: 'Admin LMS' },
];

// =============================================================================
// Computed
// =============================================================================

const formattedDate = computed(() => {
    return new Date(props.user.created_at).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit Pengguna: ${user.name}`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Edit Pengguna"
                :description="user.name"
                :back-href="UserController.index().url"
                back-label="Kembali ke Daftar Pengguna"
            />

            <!-- Self-edit warning -->
            <Alert
                v-if="!canEditRole"
                variant="destructive"
                class="mx-auto w-full max-w-2xl border-yellow-500 bg-yellow-50 dark:bg-yellow-950"
            >
                <AlertTriangle class="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                <AlertTitle class="text-yellow-800 dark:text-yellow-200">
                    Mengedit Akun Sendiri
                </AlertTitle>
                <AlertDescription class="text-yellow-700 dark:text-yellow-300">
                    Anda tidak dapat mengubah peran Anda sendiri untuk mencegah kehilangan akses admin.
                </AlertDescription>
            </Alert>

            <Form
                v-bind="UserController.update.form(user.id)"
                class="mx-auto w-full max-w-2xl space-y-6"
                v-slot="{ errors, processing }"
            >
                <FormSection title="Informasi Akun" description="Data dasar pengguna untuk login ke sistem">
                    <div class="space-y-5">
                        <div class="space-y-2">
                            <Label for="name" class="text-sm font-medium">
                                Nama Lengkap <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                :default-value="user.name"
                                placeholder="Contoh: John Doe"
                                class="h-11"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email" class="text-sm font-medium">
                                Email <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                :default-value="user.email"
                                placeholder="Contoh: john@example.com"
                                class="h-11"
                                required
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password" class="text-sm font-medium">
                                Password Baru
                            </Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="Kosongkan jika tidak ingin mengubah"
                                class="h-11"
                            />
                            <InputError :message="errors.password" />
                            <p class="text-sm text-muted-foreground">
                                Kosongkan jika tidak ingin mengubah password.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation" class="text-sm font-medium">
                                Konfirmasi Password Baru
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                placeholder="Ketik ulang password baru"
                                class="h-11"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="role" class="text-sm font-medium">
                                Peran <span class="text-destructive">*</span>
                            </Label>
                            <input type="hidden" name="role" :value="selectedRole" />
                            <Select v-model="selectedRole" :disabled="!canEditRole">
                                <SelectTrigger class="h-11" :class="{ 'opacity-50 cursor-not-allowed': !canEditRole }">
                                    <SelectValue placeholder="Pilih peran pengguna" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in roleOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.role" />
                            <p v-if="canEditRole" class="text-sm text-muted-foreground">
                                Peran menentukan akses dan kemampuan pengguna dalam sistem.
                            </p>
                        </div>
                    </div>
                </FormSection>

                <FormSection title="Aktivitas Pengguna" description="Ringkasan aktivitas pengguna dalam sistem">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="flex items-center gap-3 rounded-lg border p-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <BookOpen class="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ user.courses_count ?? 0 }}</p>
                                <p class="text-sm text-muted-foreground">Kursus Dibuat</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-lg border p-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-500/10">
                                <GraduationCap class="h-5 w-5 text-green-500" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ user.enrollments_count ?? 0 }}</p>
                                <p class="text-sm text-muted-foreground">Kursus Diikuti</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-lg border p-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/10">
                                <Calendar class="h-5 w-5 text-blue-500" />
                            </div>
                            <div>
                                <p class="text-sm font-medium">{{ formattedDate }}</p>
                                <p class="text-sm text-muted-foreground">Terdaftar Sejak</p>
                            </div>
                        </div>
                    </div>
                </FormSection>

                <div class="flex items-center justify-end gap-3">
                    <Link :href="UserController.index().url">
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </Link>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
