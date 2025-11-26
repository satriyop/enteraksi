<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { type LucideIcon } from 'lucide-vue-next';

interface Props {
    icon?: LucideIcon;
    title: string;
    description?: string;
    actionLabel?: string;
    actionHref?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed bg-muted/20 px-6 py-16 text-center">
        <div v-if="icon" class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
            <component :is="icon" class="h-8 w-8 text-muted-foreground" />
        </div>
        <h3 class="text-lg font-semibold text-foreground">
            {{ title }}
        </h3>
        <p v-if="description" class="mt-2 max-w-sm text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div class="mt-6">
            <slot name="action">
                <Link v-if="actionHref && actionLabel" :href="actionHref">
                    <Button>
                        {{ actionLabel }}
                    </Button>
                </Link>
            </slot>
        </div>
    </div>
</template>
