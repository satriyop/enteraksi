# Phase 7: Performance Optimization

## Overview

This phase focuses on optimizing the frontend for better user experience. Key areas include bundle optimization, lazy loading, virtual scrolling, and memory management.

**Duration:** 1-2 weeks
**Risk Level:** Low
**Dependencies:** Phase 3 (Components)

---

## Performance Audit

### Current Issues to Address

| Issue | Impact | Priority |
|-------|--------|----------|
| No route-based code splitting | Large initial bundle | High |
| Heavy components loaded eagerly | Slow initial render | High |
| No image optimization | Slow content load | Medium |
| Large lists render all items | Memory issues | Medium |
| No prefetching strategy | Slow navigation | Low |

---

## Code Splitting

### Route-Based Splitting

Inertia.js supports lazy loading pages. Configure in `app.js`:

**File: `resources/js/app.js`**
```javascript
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob('./pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
```

This automatically code-splits each page into its own chunk.

### Component-Level Splitting

**Lazy load heavy components:**
```vue
<script setup lang="ts">
import { defineAsyncComponent } from 'vue';

// Heavy components loaded on demand
const RichTextEditor = defineAsyncComponent(() =>
    import('@/components/features/shared/RichTextEditor.vue')
);

const VideoPlayer = defineAsyncComponent(() =>
    import('@/components/features/lesson/VideoPlayer.vue')
);

const ChartDashboard = defineAsyncComponent(() =>
    import('@/components/features/dashboard/ChartDashboard.vue')
);
</script>

<template>
    <Suspense>
        <template #default>
            <RichTextEditor v-if="showEditor" v-model="content" />
        </template>
        <template #fallback>
            <LoadingState text="Memuat editor..." />
        </template>
    </Suspense>
</template>
```

### Async Component Wrapper

**File: `components/AsyncLoader.vue`**
```vue
<script setup lang="ts">
import { ref, onErrorCaptured } from 'vue';
import LoadingState from './features/shared/LoadingState.vue';
import EmptyState from './features/shared/EmptyState.vue';
import { AlertCircle } from 'lucide-vue-next';

interface Props {
    loadingText?: string;
    errorTitle?: string;
    errorDescription?: string;
}

withDefaults(defineProps<Props>(), {
    loadingText: 'Memuat...',
    errorTitle: 'Gagal memuat',
    errorDescription: 'Terjadi kesalahan saat memuat komponen',
});

const error = ref<Error | null>(null);

onErrorCaptured((err) => {
    error.value = err;
    return false; // Don't propagate
});

function retry() {
    error.value = null;
}
</script>

<template>
    <div v-if="error">
        <EmptyState
            :icon="AlertCircle"
            :title="errorTitle"
            :description="errorDescription"
            action-label="Coba Lagi"
            @action="retry"
        />
    </div>
    <Suspense v-else>
        <template #default>
            <slot />
        </template>
        <template #fallback>
            <LoadingState :text="loadingText" />
        </template>
    </Suspense>
</template>
```

---

## Virtual Scrolling

For long lists (100+ items), use virtual scrolling to render only visible items.

### Install vue-virtual-scroller

```bash
npm install vue-virtual-scroller
```

### Virtual List Component

**File: `components/features/shared/VirtualList.vue`**
```vue
<script setup lang="ts" generic="T">
import { RecycleScroller } from 'vue-virtual-scroller';
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css';
import EmptyState from './EmptyState.vue';

interface Props {
    items: T[];
    itemSize: number;
    keyField?: string;
    emptyTitle?: string;
    emptyDescription?: string;
}

withDefaults(defineProps<Props>(), {
    keyField: 'id',
    emptyTitle: 'Tidak ada data',
});

defineSlots<{
    default(props: { item: T; index: number }): unknown;
}>();
</script>

<template>
    <RecycleScroller
        v-if="items.length > 0"
        :items="items"
        :item-size="itemSize"
        :key-field="keyField"
        class="h-full"
        v-slot="{ item, index }"
    >
        <slot :item="item" :index="index" />
    </RecycleScroller>

    <EmptyState
        v-else
        :title="emptyTitle"
        :description="emptyDescription"
    />
</template>
```

**Usage:**
```vue
<script setup lang="ts">
import VirtualList from '@/components/features/shared/VirtualList.vue';
import UserCard from '@/components/features/user/UserCard.vue';
import type { User } from '@/types';

const props = defineProps<{
    users: User[];
}>();
</script>

<template>
    <VirtualList
        :items="users"
        :item-size="72"
        empty-title="Tidak ada pengguna"
    >
        <template #default="{ item: user }">
            <UserCard :user="user" />
        </template>
    </VirtualList>
</template>
```

### Infinite Scroll with Virtual List

**File: `composables/ui/useInfiniteScroll.ts`**
```typescript
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

interface UseInfiniteScrollOptions {
    /** Element to observe */
    target: () => HTMLElement | null;
    /** Distance from bottom to trigger (in px) */
    threshold?: number;
    /** URL for fetching more data */
    endpoint: string;
    /** Merge key for Inertia */
    mergeKey: string;
}

export function useInfiniteScroll(options: UseInfiniteScrollOptions) {
    const { target, threshold = 100, endpoint, mergeKey } = options;

    const isLoading = ref(false);
    const hasMore = ref(true);
    const page = ref(1);

    let observer: IntersectionObserver | null = null;

    async function loadMore() {
        if (isLoading.value || !hasMore.value) return;

        isLoading.value = true;
        page.value++;

        await router.get(endpoint, { page: page.value }, {
            preserveState: true,
            preserveScroll: true,
            only: [mergeKey],
            onSuccess: (response) => {
                const data = response.props[mergeKey];
                if (data.meta.current_page >= data.meta.last_page) {
                    hasMore.value = false;
                }
            },
            onFinish: () => {
                isLoading.value = false;
            },
        });
    }

    onMounted(() => {
        const element = target();
        if (!element) return;

        observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting) {
                    loadMore();
                }
            },
            { rootMargin: `${threshold}px` }
        );

        observer.observe(element);
    });

    onUnmounted(() => {
        observer?.disconnect();
    });

    return {
        isLoading,
        hasMore,
        loadMore,
    };
}
```

---

## Image Optimization

### Lazy Image Component

**File: `components/features/shared/LazyImage.vue`**
```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue';

interface Props {
    src: string;
    alt: string;
    placeholder?: string;
    width?: number;
    height?: number;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: '/images/placeholder.svg',
});

const imageRef = ref<HTMLImageElement | null>(null);
const isLoaded = ref(false);
const hasError = ref(false);

let observer: IntersectionObserver | null = null;

function loadImage() {
    if (!imageRef.value) return;

    const img = new Image();
    img.src = props.src;

    img.onload = () => {
        if (imageRef.value) {
            imageRef.value.src = props.src;
            isLoaded.value = true;
        }
    };

    img.onerror = () => {
        hasError.value = true;
    };
}

onMounted(() => {
    if (!imageRef.value) return;

    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting) {
                loadImage();
                observer?.disconnect();
            }
        },
        { rootMargin: '50px' }
    );

    observer.observe(imageRef.value);
});
</script>

<template>
    <img
        ref="imageRef"
        :src="placeholder"
        :alt="alt"
        :width="width"
        :height="height"
        :class="[
            props.class,
            'transition-opacity duration-300',
            isLoaded ? 'opacity-100' : 'opacity-50'
        ]"
        loading="lazy"
    />
</template>
```

### Responsive Image Component

**File: `components/features/shared/ResponsiveImage.vue`**
```vue
<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    src: string;
    alt: string;
    sizes?: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    sizes: '100vw',
});

// Generate srcset for common breakpoints
const srcset = computed(() => {
    if (!props.src) return '';

    // Assuming images are served from a service that supports width params
    const widths = [320, 640, 768, 1024, 1280, 1536];

    return widths
        .map((w) => {
            const url = props.src.includes('?')
                ? `${props.src}&w=${w}`
                : `${props.src}?w=${w}`;
            return `${url} ${w}w`;
        })
        .join(', ');
});
</script>

<template>
    <img
        :src="src"
        :srcset="srcset"
        :sizes="sizes"
        :alt="alt"
        :class="props.class"
        loading="lazy"
        decoding="async"
    />
</template>
```

---

## Prefetching

### Inertia Link Prefetching

Inertia v2 supports prefetching. Enable it for critical navigation:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <!-- Prefetch on hover (default behavior) -->
    <Link href="/courses" prefetch>Courses</Link>

    <!-- Prefetch on mount (for critical links) -->
    <Link href="/dashboard" prefetch="mount">Dashboard</Link>

    <!-- Prefetch with cache time -->
    <Link
        href="/courses"
        prefetch
        :prefetch-cache-time="30000"
    >
        Courses
    </Link>
</template>
```

### Manual Prefetching

```typescript
import { router } from '@inertiajs/vue3';

// Prefetch a page
function prefetchCourse(id: number) {
    router.prefetch(`/courses/${id}`, {
        cacheFor: 30000, // 30 seconds
    });
}

// Usage: prefetch on hover
function handleMouseEnter(courseId: number) {
    prefetchCourse(courseId);
}
```

---

## Memory Management

### Component Cleanup

**Properly clean up resources in components:**
```vue
<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

const intervalId = ref<number | null>(null);
const abortController = ref<AbortController | null>(null);
const websocket = ref<WebSocket | null>(null);

onMounted(() => {
    // Set up interval
    intervalId.value = setInterval(() => {
        // ...
    }, 1000);

    // Set up abort controller for fetch
    abortController.value = new AbortController();

    // Set up websocket
    websocket.value = new WebSocket('ws://...');
});

onUnmounted(() => {
    // Clean up interval
    if (intervalId.value) {
        clearInterval(intervalId.value);
    }

    // Abort pending requests
    abortController.value?.abort();

    // Close websocket
    websocket.value?.close();
});
</script>
```

### Event Listener Cleanup Composable

**File: `composables/ui/useEventListener.ts`**
```typescript
import { onMounted, onUnmounted, type Ref, watch, unref } from 'vue';

type Target = Window | Document | HTMLElement | Ref<HTMLElement | null>;

export function useEventListener<K extends keyof WindowEventMap>(
    target: Target,
    event: K,
    handler: (event: WindowEventMap[K]) => void,
    options?: AddEventListenerOptions
) {
    function cleanup(el: EventTarget | null) {
        if (el) {
            el.removeEventListener(event, handler as EventListener, options);
        }
    }

    function setup(el: EventTarget | null) {
        if (el) {
            el.addEventListener(event, handler as EventListener, options);
        }
    }

    // Handle refs
    if (typeof target === 'object' && 'value' in target) {
        watch(
            target,
            (newEl, oldEl) => {
                cleanup(oldEl);
                setup(newEl);
            },
            { immediate: true }
        );
    } else {
        onMounted(() => setup(target as EventTarget));
    }

    onUnmounted(() => cleanup(unref(target) as EventTarget));
}
```

### Debounced Watchers

**File: `composables/utils/useDebouncedWatch.ts`**
```typescript
import { watch, type WatchSource, type WatchCallback, type WatchOptions } from 'vue';
import { debounce } from '@/lib/utils';

export function useDebouncedWatch<T>(
    source: WatchSource<T>,
    callback: WatchCallback<T, T>,
    delay: number = 300,
    options?: WatchOptions
) {
    const debouncedCallback = debounce(callback, delay);

    return watch(source, debouncedCallback, options);
}
```

---

## Bundle Analysis

### Vite Bundle Analyzer

Install and configure:
```bash
npm install -D rollup-plugin-visualizer
```

**Update `vite.config.js`:**
```javascript
import { visualizer } from 'rollup-plugin-visualizer';

export default defineConfig({
    plugins: [
        // ... other plugins
        visualizer({
            filename: 'stats.html',
            open: true,
            gzipSize: true,
        }),
    ],
});
```

Run analysis:
```bash
npm run build
# Opens stats.html with bundle visualization
```

### Manual Chunk Configuration

**File: `vite.config.js`**
```javascript
export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    // Vendor chunks
                    'vendor-vue': ['vue', '@inertiajs/vue3'],
                    'vendor-ui': [
                        '@radix-vue/primitive',
                        'class-variance-authority',
                        'clsx',
                        'tailwind-merge',
                    ],
                    'vendor-editor': [
                        '@tiptap/core',
                        '@tiptap/starter-kit',
                        '@tiptap/vue-3',
                    ],
                    'vendor-charts': ['chart.js', 'vue-chartjs'],
                },
            },
        },
    },
});
```

---

## Performance Monitoring

### Web Vitals Tracking

**File: `lib/performance.ts`**
```typescript
import { onCLS, onFID, onLCP, onFCP, onTTFB } from 'web-vitals';

interface Metric {
    name: string;
    value: number;
    rating: 'good' | 'needs-improvement' | 'poor';
}

type ReportHandler = (metric: Metric) => void;

export function trackWebVitals(reportHandler: ReportHandler) {
    onCLS((metric) => reportHandler({
        name: 'CLS',
        value: metric.value,
        rating: metric.rating,
    }));

    onFID((metric) => reportHandler({
        name: 'FID',
        value: metric.value,
        rating: metric.rating,
    }));

    onLCP((metric) => reportHandler({
        name: 'LCP',
        value: metric.value,
        rating: metric.rating,
    }));

    onFCP((metric) => reportHandler({
        name: 'FCP',
        value: metric.value,
        rating: metric.rating,
    }));

    onTTFB((metric) => reportHandler({
        name: 'TTFB',
        value: metric.value,
        rating: metric.rating,
    }));
}

// Usage in app.js
if (import.meta.env.PROD) {
    trackWebVitals((metric) => {
        // Send to analytics
        console.log(metric);
    });
}
```

---

## Checklist

### Code Splitting
- [ ] Configure Inertia page lazy loading
- [ ] Create AsyncLoader component
- [ ] Lazy load heavy components (editor, charts, video player)
- [ ] Configure manual chunks in Vite

### Virtual Scrolling
- [ ] Install vue-virtual-scroller
- [ ] Create VirtualList component
- [ ] Implement infinite scroll composable
- [ ] Apply to long lists (users, courses, lessons)

### Image Optimization
- [ ] Create LazyImage component
- [ ] Create ResponsiveImage component
- [ ] Add placeholder images
- [ ] Configure image compression

### Prefetching
- [ ] Enable Inertia link prefetching
- [ ] Add prefetch to critical navigation
- [ ] Implement manual prefetching for lists

### Memory Management
- [ ] Audit event listener cleanup
- [ ] Create useEventListener composable
- [ ] Create useDebouncedWatch composable
- [ ] Clean up timers and subscriptions

### Monitoring
- [ ] Install web-vitals
- [ ] Create performance tracking
- [ ] Set up bundle analyzer
- [ ] Establish performance budgets

---

## Performance Targets

| Metric | Target | Threshold |
|--------|--------|-----------|
| LCP (Largest Contentful Paint) | < 2.5s | < 4s |
| FID (First Input Delay) | < 100ms | < 300ms |
| CLS (Cumulative Layout Shift) | < 0.1 | < 0.25 |
| Initial Bundle | < 200KB | < 300KB |
| Time to Interactive | < 3s | < 5s |

---

## Next Phase

After completing Performance Optimization, proceed to [Phase 8: Migration Guide](./08-MIGRATION-GUIDE.md).
