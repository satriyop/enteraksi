<script setup lang="ts">
// =============================================================================
// LessonTabNav Component
// Tab navigation for lesson content (Overview, Notes)
// =============================================================================

// =============================================================================
// Types
// =============================================================================

type TabId = 'overview' | 'notes';

interface Tab {
    id: TabId;
    label: string;
}

interface Props {
    tabs: Tab[];
}

// =============================================================================
// Component Setup
// =============================================================================

defineProps<Props>();

const activeTab = defineModel<TabId>({ required: true });
</script>

<template>
    <div class="border-b bg-background">
        <nav class="flex gap-6 px-4">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                class="py-3 text-sm transition-colors relative"
                :class="activeTab === tab.id
                    ? 'text-foreground font-medium'
                    : 'text-muted-foreground hover:text-foreground'"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
                <span
                    v-if="activeTab === tab.id"
                    class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary"
                />
            </button>
        </nav>
    </div>
</template>
