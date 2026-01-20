// =============================================================================
// useFileUpload Composable
// File upload handling with validation and progress tracking
// =============================================================================

import { ref, computed } from 'vue';
import {
    MAX_IMAGE_SIZE,
    MAX_VIDEO_SIZE,
    MAX_AUDIO_SIZE,
    MAX_DOCUMENT_SIZE,
    ALLOWED_IMAGE_TYPES,
    ALLOWED_VIDEO_TYPES,
    ALLOWED_AUDIO_TYPES,
    ALLOWED_DOCUMENT_TYPES,
} from '@/lib/constants';

// =============================================================================
// Types
// =============================================================================

type FileCategory = 'image' | 'video' | 'audio' | 'document' | 'any';

interface FileValidationRules {
    maxSize: number;
    allowedTypes: readonly string[];
}

interface UploadedFile {
    file: File;
    name: string;
    size: number;
    type: string;
    preview?: string;
    progress: number;
    error?: string;
}

interface UseFileUploadOptions {
    /** File category for validation */
    category?: FileCategory;
    /** Maximum file size in bytes (overrides category default) */
    maxSize?: number;
    /** Allowed MIME types (overrides category default) */
    allowedTypes?: string[];
    /** Maximum number of files (default: 1) */
    maxFiles?: number;
    /** Callback when files are selected */
    onSelect?: (files: File[]) => void;
    /** Callback when validation fails */
    onError?: (error: string) => void;
}

// =============================================================================
// Validation Rules by Category
// =============================================================================

const VALIDATION_RULES: Record<FileCategory, FileValidationRules> = {
    image: {
        maxSize: MAX_IMAGE_SIZE,
        allowedTypes: ALLOWED_IMAGE_TYPES as unknown as string[],
    },
    video: {
        maxSize: MAX_VIDEO_SIZE,
        allowedTypes: ALLOWED_VIDEO_TYPES as unknown as string[],
    },
    audio: {
        maxSize: MAX_AUDIO_SIZE,
        allowedTypes: ALLOWED_AUDIO_TYPES as unknown as string[],
    },
    document: {
        maxSize: MAX_DOCUMENT_SIZE,
        allowedTypes: ALLOWED_DOCUMENT_TYPES as unknown as string[],
    },
    any: {
        maxSize: MAX_VIDEO_SIZE, // Use largest as default
        allowedTypes: [],
    },
};

// =============================================================================
// Composable
// =============================================================================

export function useFileUpload(options: UseFileUploadOptions = {}) {
    const {
        category = 'any',
        maxSize: customMaxSize,
        allowedTypes: customAllowedTypes,
        maxFiles = 1,
        onSelect,
        onError,
    } = options;

    // Get validation rules
    const rules = VALIDATION_RULES[category];
    const maxSize = customMaxSize ?? rules.maxSize;
    const allowedTypes = customAllowedTypes ?? rules.allowedTypes;

    // =============================================================================
    // State
    // =============================================================================

    const files = ref<UploadedFile[]>([]);
    const isDragging = ref(false);
    const isUploading = ref(false);
    const error = ref<string | null>(null);

    // =============================================================================
    // Computed
    // =============================================================================

    const hasFiles = computed(() => files.value.length > 0);

    const canAddMore = computed(() => files.value.length < maxFiles);

    const totalSize = computed(() =>
        files.value.reduce((sum, f) => sum + f.size, 0)
    );

    const uploadProgress = computed(() => {
        if (files.value.length === 0) return 0;
        return files.value.reduce((sum, f) => sum + f.progress, 0) / files.value.length;
    });

    const acceptString = computed(() =>
        allowedTypes.length > 0 ? allowedTypes.join(',') : undefined
    );

    // =============================================================================
    // Helpers
    // =============================================================================

    /**
     * Format file size to human readable string
     */
    function formatSize(bytes: number): string {
        if (bytes === 0) return '0 B';

        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`;
    }

    /**
     * Validate a single file
     */
    function validateFile(file: File): string | null {
        // Check file type
        if (allowedTypes.length > 0 && !allowedTypes.includes(file.type)) {
            return `Tipe file tidak diizinkan: ${file.type}`;
        }

        // Check file size
        if (file.size > maxSize) {
            return `Ukuran file terlalu besar (maks ${formatSize(maxSize)})`;
        }

        return null;
    }

    /**
     * Create preview URL for images
     */
    function createPreview(file: File): string | undefined {
        if (file.type.startsWith('image/')) {
            return URL.createObjectURL(file);
        }
        return undefined;
    }

    // =============================================================================
    // Methods
    // =============================================================================

    /**
     * Add files from input or drop event
     */
    function addFiles(fileList: FileList | File[]): void {
        error.value = null;

        const newFiles = Array.from(fileList);
        const validFiles: File[] = [];

        for (const file of newFiles) {
            // Check max files limit
            if (files.value.length + validFiles.length >= maxFiles) {
                error.value = `Maksimal ${maxFiles} file diizinkan`;
                onError?.(error.value);
                break;
            }

            // Validate file
            const validationError = validateFile(file);
            if (validationError) {
                error.value = validationError;
                onError?.(validationError);
                continue;
            }

            validFiles.push(file);

            // Add to files list
            files.value.push({
                file,
                name: file.name,
                size: file.size,
                type: file.type,
                preview: createPreview(file),
                progress: 0,
            });
        }

        if (validFiles.length > 0) {
            onSelect?.(validFiles);
        }
    }

    /**
     * Remove file by index
     */
    function removeFile(index: number): void {
        const file = files.value[index];
        if (file?.preview) {
            URL.revokeObjectURL(file.preview);
        }
        files.value.splice(index, 1);
        error.value = null;
    }

    /**
     * Clear all files
     */
    function clear(): void {
        // Revoke all preview URLs
        files.value.forEach(f => {
            if (f.preview) URL.revokeObjectURL(f.preview);
        });
        files.value = [];
        error.value = null;
    }

    /**
     * Update upload progress for a file
     */
    function updateProgress(index: number, progress: number): void {
        if (files.value[index]) {
            files.value[index].progress = Math.min(100, Math.max(0, progress));
        }
    }

    /**
     * Set error for a file
     */
    function setFileError(index: number, errorMessage: string): void {
        if (files.value[index]) {
            files.value[index].error = errorMessage;
        }
    }

    /**
     * Get files for form submission
     */
    function getFiles(): File[] {
        return files.value.map(f => f.file);
    }

    /**
     * Get first file (for single file uploads)
     */
    function getFile(): File | null {
        return files.value[0]?.file ?? null;
    }

    // =============================================================================
    // Drag & Drop Handlers
    // =============================================================================

    function handleDragEnter(event: DragEvent): void {
        event.preventDefault();
        isDragging.value = true;
    }

    function handleDragLeave(event: DragEvent): void {
        event.preventDefault();
        isDragging.value = false;
    }

    function handleDragOver(event: DragEvent): void {
        event.preventDefault();
    }

    function handleDrop(event: DragEvent): void {
        event.preventDefault();
        isDragging.value = false;

        if (event.dataTransfer?.files) {
            addFiles(event.dataTransfer.files);
        }
    }

    /**
     * Handle file input change
     */
    function handleInputChange(event: Event): void {
        const input = event.target as HTMLInputElement;
        if (input.files) {
            addFiles(input.files);
        }
        // Reset input to allow selecting same file again
        input.value = '';
    }

    // =============================================================================
    // Return
    // =============================================================================

    return {
        // State
        files,
        isDragging,
        isUploading,
        error,

        // Computed
        hasFiles,
        canAddMore,
        totalSize,
        uploadProgress,
        acceptString,

        // Methods
        addFiles,
        removeFile,
        clear,
        updateProgress,
        setFileError,
        getFiles,
        getFile,
        formatSize,

        // Drag & Drop Handlers
        handleDragEnter,
        handleDragLeave,
        handleDragOver,
        handleDrop,
        handleInputChange,
    };
}
