<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { ChevronDown, Search, User } from 'lucide-vue-next';

interface Learner {
    id: number;
    name: string;
    email: string;
}

interface Props {
    courseId: number;
    modelValue: number | null;
    label?: string;
}

const props = withDefaults(defineProps<Props>(), {
    label: 'Pilih Peserta',
});

const emit = defineEmits<{
    'update:modelValue': [userId: number | null];
}>();

const searchQuery = ref('');
const learners = ref<Learner[]>([]);
const isLoading = ref(false);
const isOpen = ref(false);
const selectedLearner = ref<Learner | null>(null);

const searchLearners = async (query: string) => {
    if (!query || query.length < 2) {
        learners.value = [];
        return;
    }

    isLoading.value = true;
    try {
        const response = await axios.get<Learner[]>('/api/users/search', {
            params: {
                q: query,
                course_id: props.courseId,
            },
        });
        learners.value = response.data;
    } catch (error) {
        console.error('Error searching learners:', error);
        learners.value = [];
    } finally {
        isLoading.value = false;
    }
};

const debouncedSearch = useDebounceFn(searchLearners, 300);

watch(searchQuery, (newQuery) => {
    debouncedSearch(newQuery);
});

const selectLearner = (learner: Learner) => {
    selectedLearner.value = learner;
    emit('update:modelValue', learner.id);
    searchQuery.value = learner.name;
    isOpen.value = false;
};

const clearSelection = () => {
    selectedLearner.value = null;
    searchQuery.value = '';
    emit('update:modelValue', null);
    learners.value = [];
};

const displayValue = computed(() => {
    if (selectedLearner.value) {
        return selectedLearner.value.name;
    }
    return searchQuery.value;
});

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const handleBlur = () => {
    window.setTimeout(() => {
        isOpen.value = false;
    }, 200);
};
</script>

<template>
    <div class="relative">
        <Label v-if="label" for="learner-search" class="mb-2">
            {{ label }}
        </Label>
        <div class="relative">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    id="learner-search"
                    v-model="searchQuery"
                    type="text"
                    placeholder="Cari peserta berdasarkan nama atau email..."
                    class="pl-9 pr-9"
                    @focus="isOpen = true"
                    @blur="handleBlur"
                />
                <Spinner
                    v-if="isLoading"
                    class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <button
                    v-else-if="selectedLearner"
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="clearSelection"
                >
                    <span class="sr-only">Hapus pilihan</span>
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <div
                v-if="isOpen && searchQuery.length >= 2"
                class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-popover shadow-md"
            >
                <div v-if="isLoading" class="px-4 py-8 text-center text-sm text-muted-foreground">
                    <Spinner class="mx-auto h-5 w-5" />
                    <p class="mt-2">Mencari peserta...</p>
                </div>
                <div
                    v-else-if="learners.length === 0"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    <User class="mx-auto h-8 w-8 text-muted-foreground/50" />
                    <p class="mt-2">Tidak ada peserta ditemukan</p>
                </div>
                <div v-else class="py-1">
                    <button
                        v-for="learner in learners"
                        :key="learner.id"
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-accent"
                        @click="selectLearner(learner)"
                    >
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-medium text-primary">
                            {{ getInitials(learner.name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ learner.name }}</div>
                            <div class="truncate text-sm text-muted-foreground">
                                {{ learner.email }}
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
