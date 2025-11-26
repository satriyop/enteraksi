<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link } from '@inertiajs/vue3';
import { MoreVertical, Play } from 'lucide-vue-next';

interface Action {
    label: string;
    href?: string;
    icon?: any;
    variant?: 'default' | 'destructive';
    onClick?: () => void;
}

interface Props {
    title: string;
    subtitle?: string;
    description?: string;
    thumbnailUrl?: string;
    href?: string;
    badges?: { label: string; variant?: 'default' | 'secondary' | 'outline' | 'destructive' }[];
    meta?: { icon?: any; label: string }[];
    actions?: Action[];
}

defineProps<Props>();
</script>

<template>
    <div class="group relative flex flex-col overflow-hidden rounded-xl border bg-card transition-all hover:shadow-lg">
        <component
            :is="href ? Link : 'div'"
            :href="href"
            class="relative aspect-video w-full overflow-hidden bg-muted"
        >
            <img
                v-if="thumbnailUrl"
                :src="thumbnailUrl"
                :alt="title"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary/10 to-primary/5"
            >
                <Play class="h-12 w-12 text-primary/30" />
            </div>
            <div v-if="badges && badges.length > 0" class="absolute left-3 top-3 flex flex-wrap gap-1.5">
                <Badge
                    v-for="(badge, idx) in badges"
                    :key="idx"
                    :variant="badge.variant || 'default'"
                    class="shadow-sm"
                >
                    {{ badge.label }}
                </Badge>
            </div>
        </component>

        <div class="flex flex-1 flex-col p-4">
            <div class="mb-2 flex items-start justify-between gap-2">
                <div class="flex-1">
                    <component
                        :is="href ? Link : 'h3'"
                        :href="href"
                        class="line-clamp-2 font-semibold leading-tight text-foreground transition-colors group-hover:text-primary"
                    >
                        {{ title }}
                    </component>
                    <p v-if="subtitle" class="mt-0.5 text-sm text-muted-foreground">
                        {{ subtitle }}
                    </p>
                </div>
                <DropdownMenu v-if="actions && actions.length > 0">
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" class="h-8 w-8 shrink-0">
                            <MoreVertical class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <template v-for="(action, idx) in actions" :key="idx">
                            <DropdownMenuSeparator v-if="action.variant === 'destructive' && idx > 0" />
                            <DropdownMenuItem
                                :as="action.href ? Link : 'button'"
                                :href="action.href"
                                :class="action.variant === 'destructive' ? 'text-destructive focus:text-destructive' : ''"
                                @click="action.onClick"
                            >
                                <component v-if="action.icon" :is="action.icon" class="mr-2 h-4 w-4" />
                                {{ action.label }}
                            </DropdownMenuItem>
                        </template>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <p v-if="description" class="mb-3 line-clamp-2 flex-1 text-sm text-muted-foreground">
                {{ description }}
            </p>

            <div v-if="meta && meta.length > 0" class="mt-auto flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                <span v-for="(item, idx) in meta" :key="idx" class="flex items-center gap-1">
                    <component v-if="item.icon" :is="item.icon" class="h-3.5 w-3.5" />
                    {{ item.label }}
                </span>
            </div>

            <slot name="footer" />
        </div>
    </div>
</template>
