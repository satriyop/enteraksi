<script setup lang="ts">
import AssessmentController from '@/actions/App/Http/Controllers/AssessmentController';
import PageHeader from '@/components/crud/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { Clock, CheckCircle, FileText, AlertTriangle, Check, X, Upload, Type, ListOrdered } from 'lucide-vue-next';
import { ref } from 'vue';

interface Assessment {
    id: number;
    title: string;
    description: string;
    instructions: string;
    time_limit_minutes: number;
    passing_score: number;
    max_attempts: number;
    questions: Question[];
}

interface Question {
    id: number;
    question_text: string;
    question_type: string;
    points: number;
    options: QuestionOption[];
}

interface QuestionOption {
    id: number;
    option_text: string;
    is_correct: boolean;
}

interface Attempt {
    id: number;
    attempt_number: number;
    status: string;
    started_at: string;
}

interface Course {
    id: number;
    title: string;
}

interface Props {
    course: Course;
    assessment: Assessment;
    attempt: Attempt;
    can: {
        submit: boolean;
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
        href: `/courses/${props.course.id}/assessments`,
    },
    {
        title: props.assessment.title,
        href: `/courses/${props.course.id}/assessments/${props.assessment.id}`,
    },
    {
        title: `Percobaan ${props.attempt.attempt_number}`,
        href: AssessmentController.attempt().url,
    },
];

const form = ref({
    answers: props.assessment.questions.map(question => ({
        question_id: question.id,
        answer_text: '',
        file: null as File | null,
    })),
});

const timeLeft = ref('');
const timeElapsed = ref('');

// Calculate time
const calculateTime = () => {
    const startedAt = new Date(props.attempt.started_at);
    const now = new Date();
    const elapsedSeconds = Math.floor((now.getTime() - startedAt.getTime()) / 1000);

    // Time elapsed
    const hoursElapsed = Math.floor(elapsedSeconds / 3600);
    const minutesElapsed = Math.floor((elapsedSeconds % 3600) / 60);
    const secondsElapsed = elapsedSeconds % 60;
    timeElapsed.value = `${hoursElapsed.toString().padStart(2, '0')}:${minutesElapsed.toString().padStart(2, '0')}:${secondsElapsed.toString().padStart(2, '0')}`;

    // Time left (if there's a time limit)
    if (props.assessment.time_limit_minutes) {
        const totalLimitSeconds = props.assessment.time_limit_minutes * 60;
        const remainingSeconds = Math.max(0, totalLimitSeconds - elapsedSeconds);

        const hoursLeft = Math.floor(remainingSeconds / 3600);
        const minutesLeft = Math.floor((remainingSeconds % 3600) / 60);
        const secondsLeft = remainingSeconds % 60;
        timeLeft.value = `${hoursLeft.toString().padStart(2, '0')}:${minutesLeft.toString().padStart(2, '0')}:${secondsLeft.toString().padStart(2, '0')}`;
    }
};

// Update time every second
const timeInterval = setInterval(calculateTime, 1000);
calculateTime();

// Clean up interval on component unmount
onUnmounted(() => {
    clearInterval(timeInterval);
});

const getQuestionTypeLabel = (type: string) => {
    const types: Record<string, string> = {
        multiple_choice: 'Pilihan Ganda',
        true_false: 'Benar/Salah',
        matching: 'Pencocokan',
        short_answer: 'Jawaban Singkat',
        essay: 'Esai',
        file_upload: 'Unggah Berkas',
    };
    return types[type] || type;
};

const handleFileChange = (event: Event, questionIndex: number) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        form.value.answers[questionIndex].file = file;
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Percobaan ${attempt.attempt_number} - ${assessment.title}`" />

        <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
            <PageHeader
                :title="`Percobaan ${attempt.attempt_number} - ${assessment.title}`"
                description="Jawab semua pertanyaan dengan sebaik-baiknya"
                :back-href="`/courses/${course.id}/assessments/${assessment.id}`"
                back-label="Kembali ke Penilaian"
            />

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Instruksi Penilaian</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="assessment.instructions" class="space-y-4">
                                <p class="whitespace-pre-wrap">{{ assessment.instructions }}</p>
                            </div>
                            <div v-else class="text-muted-foreground">
                                Tidak ada instruksi khusus untuk penilaian ini.
                            </div>
                        </CardContent>
                    </Card>

                    <Form
                        v-bind="AssessmentController.submitAttempt.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                        enctype="multipart/form-data"
                    >
                        <Card v-for="(question, qIndex) in assessment.questions" :key="question.id">
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <span>Pertanyaan {{ qIndex + 1 }}</span>
                                    <span class="text-sm bg-primary/10 text-primary px-2 py-1 rounded-full">
                                        {{ getQuestionTypeLabel(question.question_type) }}
                                    </span>
                                    <span class="text-sm text-muted-foreground ml-auto">
                                        {{ question.points }} poin
                                    </span>
                                </CardTitle>
                                <CardDescription>
                                    {{ question.question_text }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Multiple Choice -->
                                <div v-if="question.question_type === 'multiple_choice'" class="space-y-3">
                                    <RadioGroup 
                                        v-model="form.answers[qIndex].answer_text"
                                        :name="`answers[${qIndex}][answer_text]`"
                                    >
                                        <div v-for="(option, oIndex) in question.options" :key="option.id" class="flex items-center space-x-2">
                                            <RadioGroupItem :value="String.fromCharCode(65 + oIndex)" :id="`q${question.id}-opt${oIndex}`" />
                                            <Label :for="`q${question.id}-opt${oIndex}`" class="flex-1 cursor-pointer">
                                                {{ String.fromCharCode(65 + oIndex) }}. {{ option.option_text }}
                                            </Label>
                                        </div>
                                    </RadioGroup>
                                    <InputError :message="errors[`answers.${qIndex}.answer_text`]" />
                                </div>

                                <!-- True/False -->
                                <div v-if="question.question_type === 'true_false'" class="space-y-3">
                                    <RadioGroup 
                                        v-model="form.answers[qIndex].answer_text"
                                        :name="`answers[${qIndex}][answer_text]`"
                                    >
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem value="true" id="true" />
                                            <Label for="true" class="cursor-pointer">Benar</Label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem value="false" id="false" />
                                            <Label for="false" class="cursor-pointer">Salah</Label>
                                        </div>
                                    </RadioGroup>
                                    <InputError :message="errors[`answers.${qIndex}.answer_text`]" />
                                </div>

                                <!-- Short Answer -->
                                <div v-if="question.question_type === 'short_answer'" class="space-y-3">
                                    <Textarea
                                        v-model="form.answers[qIndex].answer_text"
                                        :name="`answers[${qIndex}][answer_text]`"
                                        placeholder="Masukkan jawaban singkat Anda"
                                        rows="3"
                                        class="min-h-[100px]"
                                    />
                                    <InputError :message="errors[`answers.${qIndex}.answer_text`]" />
                                </div>

                                <!-- Essay -->
                                <div v-if="question.question_type === 'essay'" class="space-y-3">
                                    <Textarea
                                        v-model="form.answers[qIndex].answer_text"
                                        :name="`answers[${qIndex}][answer_text]`"
                                        placeholder="Tulis esai Anda di sini..."
                                        rows="6"
                                        class="min-h-[200px]"
                                    />
                                    <InputError :message="errors[`answers.${qIndex}.answer_text`]" />
                                </div>

                                <!-- File Upload -->
                                <div v-if="question.question_type === 'file_upload'" class="space-y-3">
                                    <div class="border-2 border-dashed rounded-lg p-6 text-center">
                                        <Upload class="mx-auto h-8 w-8 text-muted-foreground mb-2" />
                                        <p class="text-sm text-muted-foreground mb-2">
                                            Unggah jawaban Anda dalam format file
                                        </p>
                                        <Input
                                            type="file"
                                            :name="`answers[${qIndex}][file]`"
                                            @change="(e) => handleFileChange(e, qIndex)"
                                            class="hidden"
                                            id="file-upload"
                                        />
                                        <Label for="file-upload" class="cursor-pointer">
                                            <Button type="button" variant="outline" size="sm">
                                                Pilih Berkas
                                            </Button>
                                        </Label>
                                        <p v-if="form.answers[qIndex].file" class="text-sm mt-2">
                                            {{ form.answers[qIndex].file?.name }}
                                        </p>
                                    </div>
                                    <InputError :message="errors[`answers.${qIndex}.file`]" />
                                </div>

                                <!-- Matching (simplified for this example) -->
                                <div v-if="question.question_type === 'matching'" class="space-y-3">
                                    <p class="text-sm text-muted-foreground">
                                        Cocokkan item di kolom kiri dengan item yang sesuai di kolom kanan.
                                    </p>
                                    <div class="space-y-2">
                                        <div v-for="(option, oIndex) in question.options" :key="option.id" class="flex items-center gap-3">
                                            <span class="font-medium">{{ String.fromCharCode(65 + oIndex) }}.</span>
                                            <span class="flex-1">{{ option.option_text.split('|')[0] }}</span>
                                            <Input
                                                v-model="form.answers[qIndex].answer_text"
                                                placeholder="Jawaban"
                                                class="w-32"
                                            />
                                        </div>
                                    </div>
                                    <InputError :message="errors[`answers.${qIndex}.answer_text`]" />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardFooter class="flex justify-end gap-3">
                                <Button type="button" variant="outline" :disabled="processing">
                                    Simpan Draft
                                </Button>
                                <Button type="submit" class="gap-2" :disabled="processing">
                                    <Check class="h-4 w-4" />
                                    {{ processing ? 'Menyimpan...' : 'Serahkan Penilaian' }}
                                </Button>
                            </CardFooter>
                        </Card>
                    </Form>
                </div>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi Percobaan</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center gap-3">
                                <ListOrdered class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Percobaan Ke-</p>
                                    <p class="font-medium">{{ attempt.attempt_number }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <Clock class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Waktu Berjalan</p>
                                    <p class="font-medium">{{ timeElapsed }}</p>
                                </div>
                            </div>

                            <div v-if="assessment.time_limit_minutes" class="flex items-center gap-3">
                                <AlertTriangle class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Sisa Waktu</p>
                                    <p class="font-medium">{{ timeLeft }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <CheckCircle class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Nilai Kelulusan</p>
                                    <p class="font-medium">{{ assessment.passing_score }}%</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <FileText class="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p class="text-sm text-muted-foreground">Total Pertanyaan</p>
                                    <p class="font-medium">{{ assessment.questions.length }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Navigasi Pertanyaan</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-5 gap-2">
                                <Button 
                                    v-for="(_, index) in assessment.questions" 
                                    :key="index"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 w-8 p-0"
                                    @click="() => {
                                        const element = document.getElementById(`question-${index}`);
                                        if (element) element.scrollIntoView({ behavior: 'smooth' });
                                    }">
                                    {{ index + 1 }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Petunjuk</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex items-start gap-3">
                                <Check class="h-5 w-5 text-green-600 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium">Jawab semua pertanyaan</p>
                                    <p class="text-sm text-muted-foreground">
                                        Pastikan Anda menjawab semua pertanyaan sebelum menyerahkan.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Clock class="h-5 w-5 text-blue-600 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium">Kelola waktu dengan baik</p>
                                    <p class="text-sm text-muted-foreground">
                                        Perhatikan batas waktu jika ada.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <Upload class="h-5 w-5 text-purple-600 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium">Simpan pekerjaan Anda</p>
                                    <p class="text-sm text-muted-foreground">
                                        Gunakan fitur simpan draft untuk menghindari kehilangan data.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>