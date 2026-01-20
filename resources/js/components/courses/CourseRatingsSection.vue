<script setup lang="ts">
// =============================================================================
// CourseRatingsSection Component
// Displays course ratings summary, user rating form, and reviews list
// =============================================================================

import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import StarRating from '@/components/StarRating.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Star, User, Pencil, Trash2 } from 'lucide-vue-next';
import { store, update, destroy } from '@/actions/App/Http/Controllers/CourseRatingController';
import type { UserSummary } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface CourseRating {
    id: number;
    user_id: number;
    course_id: number;
    rating: number;
    review: string | null;
    created_at: string;
    user: UserSummary;
}

interface Props {
    /** Course ID */
    courseId: number;
    /** Whether user is enrolled (can rate) */
    isEnrolled?: boolean;
    /** User's existing rating */
    userRating?: CourseRating | null;
    /** All ratings/reviews */
    ratings: CourseRating[];
    /** Average rating */
    averageRating?: number | null;
    /** Total ratings count */
    ratingsCount: number;
}

// =============================================================================
// Component Setup
// =============================================================================

const props = withDefaults(defineProps<Props>(), {
    isEnrolled: false,
    userRating: null,
    averageRating: null,
});

// =============================================================================
// State
// =============================================================================

const isEditingRating = ref(false);

const ratingForm = useForm({
    rating: props.userRating?.rating || 0,
    review: props.userRating?.review || '',
});

// =============================================================================
// Methods
// =============================================================================

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const submitRating = () => {
    if (props.userRating) {
        // Update existing rating
        ratingForm.patch(update.url(props.courseId, props.userRating.id), {
            preserveScroll: true,
            onSuccess: () => {
                isEditingRating.value = false;
            },
        });
    } else {
        // Create new rating
        ratingForm.post(store.url(props.courseId), {
            preserveScroll: true,
        });
    }
};

const deleteRating = () => {
    if (!props.userRating || !confirm('Apakah Anda yakin ingin menghapus rating ini?')) {
        return;
    }
    router.delete(destroy.url(props.courseId, props.userRating.id), {
        preserveScroll: true,
    });
};

const startEditRating = () => {
    ratingForm.rating = props.userRating?.rating || 0;
    ratingForm.review = props.userRating?.review || '';
    isEditingRating.value = true;
};

const cancelEditRating = () => {
    ratingForm.rating = props.userRating?.rating || 0;
    ratingForm.review = props.userRating?.review || '';
    isEditingRating.value = false;
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Star class="h-5 w-5" />
                Rating & Ulasan
            </CardTitle>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Rating Summary -->
            <div class="flex items-center gap-6 pb-4 border-b">
                <div class="text-center">
                    <div class="text-4xl font-bold text-amber-600 dark:text-amber-500">
                        {{ averageRating?.toFixed(1) || '-' }}
                    </div>
                    <StarRating
                        :rating="averageRating || 0"
                        readonly
                        size="sm"
                        class="mt-1"
                    />
                    <div class="text-sm text-muted-foreground mt-1">
                        {{ ratingsCount }} ulasan
                    </div>
                </div>
            </div>

            <!-- User Rating Form (only for enrolled users) -->
            <div v-if="isEnrolled" class="pb-4 border-b">
                <h4 class="font-medium mb-3">
                    {{ userRating ? 'Rating Anda' : 'Berikan Rating' }}
                </h4>

                <!-- Display existing rating -->
                <div v-if="userRating && !isEditingRating" class="space-y-3">
                    <div class="flex items-center gap-2">
                        <StarRating :rating="userRating.rating" readonly size="md" />
                        <span class="text-sm text-muted-foreground">
                            {{ formatDate(userRating.created_at) }}
                        </span>
                    </div>
                    <p v-if="userRating.review" class="text-sm">
                        {{ userRating.review }}
                    </p>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="startEditRating"
                        >
                            <Pencil class="h-4 w-4 mr-1" />
                            Edit
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="deleteRating"
                        >
                            <Trash2 class="h-4 w-4 mr-1" />
                            Hapus
                        </Button>
                    </div>
                </div>

                <!-- Rating form (new or edit) -->
                <form
                    v-else
                    @submit.prevent="submitRating"
                    class="space-y-4"
                >
                    <div>
                        <label class="text-sm font-medium mb-2 block">Rating</label>
                        <StarRating
                            v-model="ratingForm.rating"
                            size="lg"
                        />
                        <p v-if="ratingForm.errors.rating" class="text-sm text-destructive mt-1">
                            {{ ratingForm.errors.rating }}
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium mb-2 block">
                            Ulasan (opsional)
                        </label>
                        <Textarea
                            v-model="ratingForm.review"
                            placeholder="Bagikan pengalaman belajar Anda..."
                            rows="3"
                        />
                        <p v-if="ratingForm.errors.review" class="text-sm text-destructive mt-1">
                            {{ ratingForm.errors.review }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            type="submit"
                            :disabled="ratingForm.processing || ratingForm.rating === 0"
                        >
                            {{ ratingForm.processing ? 'Menyimpan...' : (userRating ? 'Perbarui' : 'Kirim') }}
                        </Button>
                        <Button
                            v-if="userRating"
                            type="button"
                            variant="outline"
                            @click="cancelEditRating"
                        >
                            Batal
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Reviews List -->
            <div v-if="ratings.length > 0" class="space-y-4">
                <h4 class="font-medium">Ulasan Terbaru</h4>
                <div
                    v-for="rating in ratings"
                    :key="rating.id"
                    class="border-b pb-4 last:border-b-0 last:pb-0"
                >
                    <div class="flex items-center gap-2 mb-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10">
                            <User class="h-4 w-4 text-primary" />
                        </div>
                        <div>
                            <div class="font-medium text-sm">{{ rating.user.name }}</div>
                            <div class="flex items-center gap-2">
                                <StarRating :rating="rating.rating" readonly size="sm" />
                                <span class="text-xs text-muted-foreground">
                                    {{ formatDate(rating.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <p v-if="rating.review" class="text-sm text-muted-foreground ml-10">
                        {{ rating.review }}
                    </p>
                </div>
            </div>

            <!-- No reviews yet -->
            <div v-else class="text-center py-4 text-muted-foreground">
                <Star class="h-8 w-8 mx-auto mb-2 opacity-50" />
                <p>Belum ada ulasan untuk kursus ini.</p>
            </div>
        </CardContent>
    </Card>
</template>
