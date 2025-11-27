import { AppPageProps } from '@/types/index';

// YouTube IFrame API types
declare global {
    interface Window {
        YT: typeof YT;
        onYouTubeIframeAPIReady?: () => void;
    }
}

declare namespace YT {
    class Player {
        constructor(elementId: string | HTMLElement, options: PlayerOptions);
        loadVideoById(videoId: string): void;
        playVideo(): void;
        pauseVideo(): void;
        stopVideo(): void;
        seekTo(seconds: number, allowSeekAhead: boolean): void;
        getCurrentTime(): number;
        getDuration(): number;
        getPlayerState(): PlayerState;
        destroy(): void;
    }

    interface PlayerOptions {
        videoId?: string;
        width?: number | string;
        height?: number | string;
        playerVars?: PlayerVars;
        events?: PlayerEvents;
    }

    interface PlayerVars {
        autoplay?: 0 | 1;
        controls?: 0 | 1;
        modestbranding?: 0 | 1;
        rel?: 0 | 1;
        start?: number;
        end?: number;
    }

    interface PlayerEvents {
        onReady?: (event: PlayerEvent) => void;
        onStateChange?: (event: OnStateChangeEvent) => void;
        onError?: (event: OnErrorEvent) => void;
    }

    interface PlayerEvent {
        target: Player;
    }

    interface OnStateChangeEvent extends PlayerEvent {
        data: PlayerState;
    }

    interface OnErrorEvent extends PlayerEvent {
        data: number;
    }

    enum PlayerState {
        UNSTARTED = -1,
        ENDED = 0,
        PLAYING = 1,
        PAUSED = 2,
        BUFFERING = 3,
        CUED = 5,
    }
}

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, AppPageProps {}
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
