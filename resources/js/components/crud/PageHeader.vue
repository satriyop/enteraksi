<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface Props {
    title: string;
    description?: string;
    backHref?: string;
    backLabel?: string;
}

withDefaults(defineProps<Props>(), {
    backLabel: 'Kembali',
});
</script>

<template>
    <div class="mb-8">
        <Link v-if="backHref" :href="backHref" class="mb-4 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground">
            <ArrowLeft class="h-4 w-4" />
            {{ backLabel }}
        </Link>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    {{ title }}
                </h1>
                <p v-if="description" class="mt-1 text-muted-foreground">
                    {{ description }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
