<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { dashboard, login, register } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, X, Search, Moon, Sun } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

interface Props {
    appName?: string;
    canRegister?: boolean;
}

withDefaults(defineProps<Props>(), {
    appName: 'E-Learning',
    canRegister: true,
});

const page = usePage();
const { appearance, updateAppearance } = useAppearance();

const user = computed(() => page.props.auth?.user);
const mobileMenuOpen = ref(false);

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const toggleTheme = () => {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
};
</script>

<template>
    <header class="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center gap-8">
                    <Link href="/" class="flex items-center gap-2">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary"
                        >
                            <AppLogoIcon
                                class="h-5 w-5 fill-current text-primary-foreground"
                            />
                        </div>
                        <span class="hidden text-xl font-bold text-foreground sm:block">
                            {{ appName }}
                        </span>
                    </Link>

                    <nav class="hidden items-center gap-6 lg:flex">
                        <Link
                            v-if="user"
                            href="/learner/dashboard"
                            class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Pembelajaran Saya
                        </Link>
                        <Link
                            href="/courses"
                            class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Kursus
                        </Link>
                        <Link
                            href="/learner/learning-paths/browse"
                            class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Learning Path
                        </Link>
                        <Link
                            href="/categories"
                            class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Kategori
                        </Link>
                        <Link
                            href="/instructors"
                            class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Instruktur
                        </Link>
                    </nav>
                </div>

                <div class="hidden flex-1 items-center justify-center px-8 lg:flex">
                    <div class="relative w-full max-w-md">
                        <Search
                            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            type="text"
                            placeholder="Cari kursus..."
                            class="h-10 w-full rounded-full border bg-muted/50 pl-10 pr-4 text-sm outline-none transition-colors focus:border-primary focus:bg-background"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="hidden sm:flex"
                        @click="toggleTheme"
                    >
                        <Sun v-if="appearance === 'dark'" class="h-5 w-5" />
                        <Moon v-else class="h-5 w-5" />
                    </Button>

                    <template v-if="user">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" class="relative h-9 w-9 rounded-full">
                                    <Avatar class="h-9 w-9">
                                        <AvatarImage
                                            v-if="user.avatar"
                                            :src="user.avatar"
                                            :alt="user.name"
                                        />
                                        <AvatarFallback class="bg-primary/10 text-primary">
                                            {{ getInitials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-56" align="end">
                                <div class="flex items-center justify-start gap-2 p-2">
                                    <div class="flex flex-col space-y-0.5 leading-none">
                                        <p class="text-sm font-medium">{{ user.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ user.email }}
                                        </p>
                                    </div>
                                </div>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem as-child>
                                    <Link :href="dashboard()">Dashboard</Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child>
                                    <Link href="/settings/profile">Pengaturan</Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem as-child>
                                    <Link href="/logout" method="post" as="button" class="w-full">
                                        Keluar
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>

                    <template v-else>
                        <Link :href="login()" class="hidden sm:block">
                            <Button variant="ghost">Masuk</Button>
                        </Link>
                        <Link v-if="canRegister" :href="register()" class="hidden sm:block">
                            <Button>Daftar</Button>
                        </Link>
                    </template>

                    <Button
                        variant="ghost"
                        size="icon"
                        class="lg:hidden"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                    >
                        <X v-if="mobileMenuOpen" class="h-6 w-6" />
                        <Menu v-else class="h-6 w-6" />
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="mobileMenuOpen"
            class="border-t lg:hidden"
        >
            <div class="space-y-1 px-4 py-4">
                <div class="relative mb-4">
                    <Search
                        class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        type="text"
                        placeholder="Cari kursus..."
                        class="h-10 w-full rounded-lg border bg-muted/50 pl-10 pr-4 text-sm outline-none"
                    />
                </div>

                <Link
                    v-if="user"
                    href="/learner/dashboard"
                    class="block rounded-lg px-3 py-2 text-base font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    Pembelajaran Saya
                </Link>
                <Link
                    href="/courses"
                    class="block rounded-lg px-3 py-2 text-base font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    Kursus
                </Link>
                <Link
                    href="/learner/learning-paths/browse"
                    class="block rounded-lg px-3 py-2 text-base font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    Learning Path
                </Link>
                <Link
                    href="/categories"
                    class="block rounded-lg px-3 py-2 text-base font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    Kategori
                </Link>
                <Link
                    href="/instructors"
                    class="block rounded-lg px-3 py-2 text-base font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    Instruktur
                </Link>

                <div class="border-t pt-4">
                    <template v-if="user">
                        <Link
                            :href="dashboard()"
                            class="block rounded-lg px-3 py-2 text-base font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            Dashboard
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="block w-full rounded-lg px-3 py-2 text-left text-base font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            Keluar
                        </Link>
                    </template>
                    <template v-else>
                        <div class="flex gap-2">
                            <Link :href="login()" class="flex-1">
                                <Button variant="outline" class="w-full">Masuk</Button>
                            </Link>
                            <Link v-if="canRegister" :href="register()" class="flex-1">
                                <Button class="w-full">Daftar</Button>
                            </Link>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between border-t pt-4">
                    <span class="text-sm text-muted-foreground">Tema</span>
                    <Button variant="outline" size="sm" @click="toggleTheme">
                        <Sun v-if="appearance === 'dark'" class="mr-2 h-4 w-4" />
                        <Moon v-else class="mr-2 h-4 w-4" />
                        {{ appearance === 'dark' ? 'Terang' : 'Gelap' }}
                    </Button>
                </div>
            </div>
        </div>
    </header>
</template>
