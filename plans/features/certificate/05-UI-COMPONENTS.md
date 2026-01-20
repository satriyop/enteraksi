# Phase 5: UI Components

> **Phase**: 5 of 5
> **Estimated Effort**: Medium
> **Prerequisites**: Phase 1-4

---

## Objectives

- Create Vue pages for certificate management
- Build reusable certificate components
- Implement public verification page
- Add certificate section to existing pages

---

## 5.1 Page Structure

```
resources/js/pages/
├── certificates/
│   ├── Index.vue           # Learner's certificate list
│   ├── Show.vue            # Certificate detail view
│   ├── Verify.vue          # Public verification page
│   └── VerifyNotFound.vue  # Certificate not found
└── admin/
    └── certificates/
        ├── Index.vue       # Admin certificate list
        ├── Create.vue      # Manual issuance form
        └── Show.vue        # Admin certificate detail
```

---

## 5.2 Learner Certificate List Page

### File: `resources/js/pages/certificates/Index.vue`

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Download, ExternalLink, Award, Calendar, CheckCircle, XCircle } from 'lucide-vue-next'
import { index as certificatesIndex, download } from '@/actions/App/Http/Controllers/CertificateController'

interface Certificate {
    id: number
    certificate_number: string
    course: {
        title: string
        slug: string
        thumbnail_url: string | null
    }
    issued_at: string
    formatted_issue_date: string
    expires_at: string | null
    is_valid: boolean
    is_expired: boolean
    verification_url: string
}

defineProps<{
    certificates: Certificate[]
}>()
</script>

<template>
    <Head title="Sertifikat Saya" />

    <AppLayout>
        <div class="container mx-auto px-4 py-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <Award class="h-7 w-7 text-primary" />
                    Sertifikat Saya
                </h1>
                <p class="mt-1 text-gray-600 dark:text-gray-400">
                    Daftar sertifikat yang telah Anda peroleh
                </p>
            </div>

            <!-- Empty State -->
            <div
                v-if="certificates.length === 0"
                class="text-center py-16 bg-gray-50 dark:bg-gray-800 rounded-lg"
            >
                <Award class="h-16 w-16 mx-auto text-gray-400 mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                    Belum Ada Sertifikat
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Selesaikan kursus untuk mendapatkan sertifikat
                </p>
                <Link href="/courses">
                    <Button>Jelajahi Kursus</Button>
                </Link>
            </div>

            <!-- Certificate Grid -->
            <div
                v-else
                class="grid gap-6 md:grid-cols-2 lg:grid-cols-3"
            >
                <Card
                    v-for="cert in certificates"
                    :key="cert.id"
                    class="overflow-hidden hover:shadow-lg transition-shadow"
                >
                    <!-- Course Thumbnail -->
                    <div class="relative h-32 bg-gradient-to-r from-primary/80 to-primary">
                        <img
                            v-if="cert.course.thumbnail_url"
                            :src="cert.course.thumbnail_url"
                            :alt="cert.course.title"
                            class="w-full h-full object-cover opacity-50"
                        />
                        <div class="absolute inset-0 flex items-center justify-center">
                            <Award class="h-12 w-12 text-white" />
                        </div>

                        <!-- Validity Badge -->
                        <Badge
                            :variant="cert.is_valid ? 'default' : 'destructive'"
                            class="absolute top-2 right-2"
                        >
                            <CheckCircle v-if="cert.is_valid" class="h-3 w-3 mr-1" />
                            <XCircle v-else class="h-3 w-3 mr-1" />
                            {{ cert.is_valid ? 'Valid' : (cert.is_expired ? 'Kadaluarsa' : 'Dicabut') }}
                        </Badge>
                    </div>

                    <CardContent class="p-4">
                        <!-- Course Title -->
                        <h3 class="font-semibold text-gray-900 dark:text-white mb-2 line-clamp-2">
                            {{ cert.course.title }}
                        </h3>

                        <!-- Certificate Number -->
                        <p class="text-sm text-gray-500 dark:text-gray-400 font-mono mb-2">
                            {{ cert.certificate_number }}
                        </p>

                        <!-- Issue Date -->
                        <div class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 mb-4">
                            <Calendar class="h-4 w-4" />
                            <span>Diterbitkan: {{ cert.formatted_issue_date }}</span>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2">
                            <Link :href="download.url(cert.id)" class="flex-1">
                                <Button variant="default" class="w-full" size="sm">
                                    <Download class="h-4 w-4 mr-1" />
                                    Unduh PDF
                                </Button>
                            </Link>
                            <a
                                :href="cert.verification_url"
                                target="_blank"
                                class="flex-shrink-0"
                            >
                                <Button variant="outline" size="sm">
                                    <ExternalLink class="h-4 w-4" />
                                </Button>
                            </a>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
```

---

## 5.3 Public Verification Page

### File: `resources/js/pages/certificates/Verify.vue`

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { CheckCircle, XCircle, Award, Calendar, User, BookOpen, AlertTriangle } from 'lucide-vue-next'
import Navbar from '@/components/home/Navbar.vue'
import Footer from '@/components/home/Footer.vue'

interface CertificateData {
    certificate_number: string
    learner_name: string
    course_title: string
    issued_at: string
    formatted_issue_date: string
    expires_at: string | null
    formatted_expiry_date: string | null
    is_valid: boolean
    revocation_reason: string | null
}

defineProps<{
    certificate: CertificateData
}>()
</script>

<template>
    <Head :title="`Verifikasi Sertifikat - ${certificate.certificate_number}`" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <Navbar />

        <main class="container mx-auto px-4 py-16">
            <div class="max-w-2xl mx-auto">
                <!-- Verification Result Card -->
                <Card class="overflow-hidden">
                    <!-- Status Banner -->
                    <div
                        :class="[
                            'py-6 px-8 text-center',
                            certificate.is_valid
                                ? 'bg-green-500 text-white'
                                : 'bg-red-500 text-white'
                        ]"
                    >
                        <div class="flex items-center justify-center gap-3 mb-2">
                            <CheckCircle v-if="certificate.is_valid" class="h-10 w-10" />
                            <XCircle v-else class="h-10 w-10" />
                            <span class="text-2xl font-bold">
                                {{ certificate.is_valid ? 'Sertifikat Valid' : 'Sertifikat Tidak Valid' }}
                            </span>
                        </div>
                        <p class="text-white/90">
                            {{ certificate.certificate_number }}
                        </p>
                    </div>

                    <CardContent class="p-8">
                        <!-- Revocation Warning -->
                        <div
                            v-if="certificate.revocation_reason"
                            class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg"
                        >
                            <div class="flex items-start gap-3">
                                <AlertTriangle class="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5" />
                                <div>
                                    <h4 class="font-medium text-red-800 dark:text-red-200">
                                        Sertifikat Dicabut
                                    </h4>
                                    <p class="text-sm text-red-600 dark:text-red-300 mt-1">
                                        Alasan: {{ certificate.revocation_reason }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Certificate Details -->
                        <div class="space-y-6">
                            <!-- Learner Name -->
                            <div class="flex items-start gap-4">
                                <div class="p-2 bg-primary/10 rounded-lg">
                                    <User class="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Diberikan Kepada
                                    </p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ certificate.learner_name }}
                                    </p>
                                </div>
                            </div>

                            <!-- Course Title -->
                            <div class="flex items-start gap-4">
                                <div class="p-2 bg-primary/10 rounded-lg">
                                    <BookOpen class="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Kursus
                                    </p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ certificate.course_title }}
                                    </p>
                                </div>
                            </div>

                            <!-- Issue Date -->
                            <div class="flex items-start gap-4">
                                <div class="p-2 bg-primary/10 rounded-lg">
                                    <Calendar class="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Tanggal Terbit
                                    </p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ certificate.formatted_issue_date }}
                                    </p>
                                </div>
                            </div>

                            <!-- Expiry Date (if applicable) -->
                            <div v-if="certificate.expires_at" class="flex items-start gap-4">
                                <div class="p-2 bg-orange-100 dark:bg-orange-900/20 rounded-lg">
                                    <Calendar class="h-6 w-6 text-orange-500" />
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Berlaku Hingga
                                    </p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ certificate.formatted_expiry_date }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Verification Footer -->
                        <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                <Award class="h-4 w-4" />
                                <span>Diverifikasi oleh Enteraksi LMS</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Back Link -->
                <div class="text-center mt-6">
                    <a
                        href="/"
                        class="text-primary hover:underline"
                    >
                        ← Kembali ke Beranda
                    </a>
                </div>
            </div>
        </main>

        <Footer />
    </div>
</template>
```

---

## 5.4 Certificate Not Found Page

### File: `resources/js/pages/certificates/VerifyNotFound.vue`

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { XCircle, Search } from 'lucide-vue-next'
import Navbar from '@/components/home/Navbar.vue'
import Footer from '@/components/home/Footer.vue'

defineProps<{
    certificateNumber: string
}>()
</script>

<template>
    <Head title="Sertifikat Tidak Ditemukan" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <Navbar />

        <main class="container mx-auto px-4 py-16">
            <div class="max-w-md mx-auto text-center">
                <Card>
                    <CardContent class="p-8">
                        <div class="p-4 bg-red-100 dark:bg-red-900/20 rounded-full w-fit mx-auto mb-6">
                            <XCircle class="h-12 w-12 text-red-500" />
                        </div>

                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                            Sertifikat Tidak Ditemukan
                        </h1>

                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Nomor sertifikat yang Anda masukkan tidak terdaftar dalam sistem kami.
                        </p>

                        <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-lg font-mono text-sm mb-6">
                            {{ certificateNumber }}
                        </div>

                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                            Pastikan Anda memasukkan nomor sertifikat dengan benar.
                            Jika Anda yakin nomor tersebut valid, silakan hubungi administrator.
                        </p>

                        <a href="/">
                            <Button class="w-full">
                                Kembali ke Beranda
                            </Button>
                        </a>
                    </CardContent>
                </Card>
            </div>
        </main>

        <Footer />
    </div>
</template>
```

---

## 5.5 Certificate Download Button Component

### File: `resources/js/components/certificates/DownloadCertificateButton.vue`

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Download, Loader2 } from 'lucide-vue-next'
import { download } from '@/actions/App/Http/Controllers/CertificateController'

const props = defineProps<{
    certificateId: number
    variant?: 'default' | 'outline' | 'ghost'
    size?: 'default' | 'sm' | 'lg'
}>()

const isDownloading = ref(false)

const handleDownload = async () => {
    isDownloading.value = true

    try {
        // Open in new tab for download
        window.open(download.url(props.certificateId), '_blank')
    } finally {
        setTimeout(() => {
            isDownloading.value = false
        }, 1000)
    }
}
</script>

<template>
    <Button
        :variant="variant ?? 'default'"
        :size="size ?? 'default'"
        :disabled="isDownloading"
        @click="handleDownload"
    >
        <Loader2 v-if="isDownloading" class="h-4 w-4 mr-2 animate-spin" />
        <Download v-else class="h-4 w-4 mr-2" />
        Unduh Sertifikat
    </Button>
</template>
```

---

## 5.6 Add Certificate to Course Completion

### Update: `resources/js/pages/courses/Detail.vue`

Add certificate section when course is completed:

```vue
<!-- Add to course detail page when enrollment is completed -->
<template>
    <!-- ... existing content ... -->

    <!-- Certificate Section (show when completed) -->
    <Card v-if="enrollment?.status === 'completed' && certificate" class="mt-6">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Award class="h-5 w-5 text-yellow-500" />
                Sertifikat Anda
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-mono text-sm text-gray-600 dark:text-gray-400">
                        {{ certificate.certificate_number }}
                    </p>
                    <p class="text-sm text-gray-500">
                        Diterbitkan: {{ certificate.formatted_issue_date }}
                    </p>
                </div>
                <DownloadCertificateButton :certificate-id="certificate.id" />
            </div>
        </CardContent>
    </Card>
</template>
```

---

## 5.7 Admin Pages (Summary)

### Admin Certificate Index

```vue
<!-- resources/js/pages/admin/certificates/Index.vue -->
<!-- Similar to other admin list pages with:
  - Search by certificate number, learner name, course
  - Filter by status (valid, revoked, expired)
  - Filter by course
  - Pagination
  - Bulk actions (if needed)
-->
```

### Admin Certificate Create

```vue
<!-- resources/js/pages/admin/certificates/Create.vue -->
<!-- Manual certificate issuance form:
  - Learner search/select (with autocomplete)
  - Course select
  - Optional expiry date
  - Optional metadata
-->
```

### Admin Certificate Show

```vue
<!-- resources/js/pages/admin/certificates/Show.vue -->
<!-- Certificate detail with:
  - Full certificate info
  - Learner details
  - Course details
  - Enrollment info (if linked)
  - Revoke button (with confirmation dialog)
  - Download button
  - Audit log (issued_at, revoked_at, etc.)
-->
```

---

## 5.8 Add to Navigation

### Update Learner Sidebar/Menu

```vue
<!-- Add to learner navigation -->
<NavLink :href="route('certificates.index')" :active="route().current('certificates.*')">
    <Award class="h-4 w-4 mr-2" />
    Sertifikat Saya
</NavLink>
```

### Update Admin Sidebar

```vue
<!-- Add to admin navigation -->
<NavLink :href="route('admin.certificates.index')" :active="route().current('admin.certificates.*')">
    <Award class="h-4 w-4 mr-2" />
    Sertifikat
</NavLink>
```

---

## Implementation Checklist

- [ ] Create `certificates/Index.vue` page
- [ ] Create `certificates/Show.vue` page
- [ ] Create `certificates/Verify.vue` page
- [ ] Create `certificates/VerifyNotFound.vue` page
- [ ] Create `DownloadCertificateButton.vue` component
- [ ] Create `admin/certificates/Index.vue` page
- [ ] Create `admin/certificates/Create.vue` page
- [ ] Create `admin/certificates/Show.vue` page
- [ ] Add certificate section to course detail page
- [ ] Update learner navigation
- [ ] Update admin navigation
- [ ] Test responsive design
- [ ] Test dark mode support

---

## Next Phase

Continue to [Phase 6: Test Plan](./06-TEST-PLAN.md)
