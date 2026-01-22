<script setup lang="ts">
// =============================================================================
// Admin Create User Page
// Create a new user with name, email, password, and role
// =============================================================================

import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import PageHeader from '@/components/crud/PageHeader.vue';
import FormSection from '@/components/crud/FormSection.vue';
import InputError from '@/components/InputError.vue';
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
import type { BreadcrumbItem, UserRole } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

// =============================================================================
// Component Setup
// =============================================================================

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Pengguna', href: UserController.index().url },
    { title: 'Tambah Pengguna', href: UserController.create().url },
];

// =============================================================================
// Form State
// =============================================================================

const selectedRole = ref<UserRole>('learner');

const roleOptions = [
    { value: 'learner', label: 'Peserta Didik' },
    { value: 'content_manager', label: 'Pengelola Konten' },
    { value: 'trainer', label: 'Pelatih' },
    { value: 'lms_admin', label: 'Admin LMS' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Tambah Pengguna Baru" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                title="Tambah Pengguna Baru"
                description="Buat akun pengguna baru untuk mengakses LMS"
                :back-href="UserController.index().url"
                back-label="Kembali ke Daftar Pengguna"
            />

            <Form
                v-bind="UserController.store.form()"
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
                                placeholder="Contoh: john@example.com"
                                class="h-11"
                                required
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password" class="text-sm font-medium">
                                Password <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="Minimal 8 karakter"
                                class="h-11"
                                required
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation" class="text-sm font-medium">
                                Konfirmasi Password <span class="text-destructive">*</span>
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                placeholder="Ketik ulang password"
                                class="h-11"
                                required
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="role" class="text-sm font-medium">
                                Peran <span class="text-destructive">*</span>
                            </Label>
                            <input type="hidden" name="role" :value="selectedRole" />
                            <Select v-model="selectedRole">
                                <SelectTrigger class="h-11">
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
                            <p class="text-sm text-muted-foreground">
                                Peran menentukan akses dan kemampuan pengguna dalam sistem.
                            </p>
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
                        {{ processing ? 'Menyimpan...' : 'Simpan Pengguna' }}
                    </Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
