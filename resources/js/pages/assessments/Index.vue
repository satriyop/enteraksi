<script setup lang="ts">
import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Search, ListFilter, Clock, CheckCircle, FileText, Users, Eye, Pencil, Trash2, Archive, PlayCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface Assessment {
    id: number;
    title: string;
    description: string;
    status: string;
    visibility: string;
    time_limit_minutes: number;
    passing_score: number;
    max_attempts: number;
    questions_count: number;
    attempts_count: number;
    created_at: string;
    updated_at: string;
}

interface Course {
    id: number;
    title: string;
}

interface Props {
    course: Course;
    assessments: {
        data: Assessment[];
        links: any[];
    };
    filters: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Kursus',
        href: `/courses/${props.course.id}`,
    },
    {
        title: 'Penilaian',
        href: AssessmentController.index().url,
    },
];

const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'published':
            return { text: 'Dipublikasikan', class: 'bg-green-100 text-green-800' };
        case 'draft':
            return { text: 'Draft', class: 'bg-yellow-100 text-yellow-800' };
        case 'archived':
            return { text: 'Diarsipkan', class: 'bg-gray-100 text-gray-800' };
        default:
            return { text: status, class: 'bg-gray-100 text-gray-800' };
    }
};

const getVisibilityBadge = (visibility: string) => {
    switch (visibility) {
        case 'public':
            return { text: 'Publik', class: 'bg-blue-100 text-blue-800' };
        case 'restricted':
            return { text: 'Terbatas', class: 'bg-purple-100 text-purple-800' };
        case 'hidden':
            return { text: 'Tersembunyi', class: 'bg-gray-100 text-gray-800' };
        default:
            return { text: visibility, class: 'bg-gray-100 text-gray-800' };
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Penilaian - ${course.title}`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                :title="`Penilaian - ${course.title}`"
                description="Kelola semua penilaian untuk kursus ini"
                :back-href="`/courses/${course.id}`"
                back-label="Kembali ke Kursus"
            />

            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-1 gap-2">
                        <div class="relative flex-1">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Cari penilaian..."
                                class="w-full pl-10"
                                @input="(e) => {
                                    const value = (e.target as HTMLInputElement).value;
                                    if (value === '') {
                                        delete filters.search;
                                    } else {
                                        filters.search = value;
                                    }
                                }"
                            />
                        </div>
                        <Select v-model="statusFilter">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue placeholder="Filter Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Semua Status</SelectItem>
                                <SelectItem value="published">Dipublikasikan</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="archived">Diarsipkan</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Link :href="`/courses/${course.id}/assessments/create`">
                        <Button class="gap-2">
                            <Plus class="h-4 w-4" />
                            Buat Penilaian
                        </Button>
                    </Link>
                </div>

                <div v-if="assessments.data.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="assessment in assessments.data" :key="assessment.id" class="hover:shadow-md transition-shadow">
                        <CardHeader>
                            <CardTitle class="flex items-center justify-between">
                                <span>{{ assessment.title }}</span>
                                <span :class="`text-xs font-medium px-2 py-1 rounded-full ${getStatusBadge(assessment.status).class}`">
                                    {{ getStatusBadge(assessment.status).text }}
                                </span>
                            </CardTitle>
                            <CardDescription>
                                {{ assessment.description || 'Tidak ada deskripsi' }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-3">
                            <div class="flex items-center gap-2">
                                <FileText class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm">{{ assessment.questions_count }} Pertanyaan</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Users class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm">{{ assessment.attempts_count }} Percobaan</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <Clock class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm">
                                    {{ assessment.time_limit_minutes ? `${assessment.time_limit_minutes} menit` : 'Tidak ada batas waktu' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <CheckCircle class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm">Nilai kelulusan: {{ assessment.passing_score }}%</span>
                            </div>
                        </CardContent>
                        <CardFooter class="flex justify-between gap-2">
                            <Link :href="`/courses/${course.id}/assessments/${assessment.id}`" class="flex-1">
                                <Button variant="outline" class="w-full gap-2">
                                    <Eye class="h-4 w-4" />
                                    Lihat
                                </Button>
                            </Link>
                            <Link v-if="assessment.status !== 'published'" :href="`/courses/${course.id}/assessments/${assessment.id}/edit`" class="flex-1">
                                <Button variant="secondary" class="w-full gap-2">
                                    <Pencil class="h-4 w-4" />
                                    Edit
                                </Button>
                            </Link>
                            <Link v-if="assessment.status === 'published'" :href="`/courses/${course.id}/assessments/${assessment.id}/start`" class="flex-1">
                                <Button class="w-full gap-2 bg-green-600 hover:bg-green-700">
                                    <PlayCircle class="h-4 w-4" />
                                    Mulai
                                </Button>
                            </Link>
                        </CardFooter>
                    </Card>
                </div>

                <div v-else class="flex flex-1 items-center justify-center rounded-lg border border-dashed p-8 text-center">
                    <div class="flex flex-col items-center gap-4">
                        <FileText class="h-12 w-12 text-muted-foreground" />
                        <h3 class="text-lg font-medium">Tidak ada penilaian</h3>
                        <p class="text-muted-foreground">Anda belum memiliki penilaian untuk kursus ini.</p>
                        <Link :href="`/courses/${course.id}/assessments/create`">
                            <Button class="gap-2">
                                <Plus class="h-4 w-4" />
                                Buat Penilaian Baru
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>