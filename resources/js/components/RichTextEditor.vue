<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';
import Underline from '@tiptap/extension-underline';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import { common, createLowlight } from 'lowlight';
import { Button } from '@/components/ui/button';
import {
    Bold,
    Italic,
    Underline as UnderlineIcon,
    Strikethrough,
    Code,
    Heading1,
    Heading2,
    Heading3,
    List,
    ListOrdered,
    Quote,
    Minus,
    Undo,
    Redo,
    Link as LinkIcon,
    Unlink,
    Image as ImageIcon,
    AlignLeft,
    AlignCenter,
    AlignRight,
    AlignJustify,
    CodeSquare,
} from 'lucide-vue-next';
import { watch, ref, onBeforeUnmount } from 'vue';

// TipTap JSON document type
type TipTapContent = string | Record<string, unknown> | null | undefined;

interface Props {
    modelValue?: TipTapContent;
    placeholder?: string;
    editable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: 'Tulis konten di sini...',
    editable: true,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const lowlight = createLowlight(common);

// Determine initial content - TipTap accepts both HTML string and JSON
const getInitialContent = (): TipTapContent => {
    if (!props.modelValue) return '';
    return props.modelValue;
};

const editor = useEditor({
    content: getInitialContent(),
    editable: props.editable,
    extensions: [
        StarterKit.configure({
            codeBlock: false,
        }),
        Underline,
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-primary underline',
            },
        }),
        Image.configure({
            HTMLAttributes: {
                class: 'rounded-lg max-w-full',
            },
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        CodeBlockLowlight.configure({
            lowlight,
        }),
    ],
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

// Watch for external changes to modelValue (only for string values)
watch(
    () => props.modelValue,
    (value) => {
        // Only sync if it's a string and different from current HTML
        if (editor.value && typeof value === 'string' && editor.value.getHTML() !== value) {
            editor.value.commands.setContent(value, false);
        }
    }
);

// Clean up editor on unmount
onBeforeUnmount(() => {
    editor.value?.destroy();
});

const setLink = () => {
    const previousUrl = editor.value?.getAttributes('link').href;
    const url = window.prompt('URL', previousUrl);

    if (url === null) {
        return;
    }

    if (url === '') {
        editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

const addImage = () => {
    const url = window.prompt('Image URL');

    if (url) {
        editor.value?.chain().focus().setImage({ src: url }).run();
    }
};

interface ToolbarButton {
    icon: typeof Bold;
    title: string;
    action: () => void;
    isActive?: () => boolean;
    disabled?: () => boolean;
}

const toolbarGroups = ref<ToolbarButton[][]>([
    [
        {
            icon: Undo,
            title: 'Undo',
            action: () => editor.value?.chain().focus().undo().run(),
            disabled: () => !editor.value?.can().undo(),
        },
        {
            icon: Redo,
            title: 'Redo',
            action: () => editor.value?.chain().focus().redo().run(),
            disabled: () => !editor.value?.can().redo(),
        },
    ],
    [
        {
            icon: Bold,
            title: 'Bold',
            action: () => editor.value?.chain().focus().toggleBold().run(),
            isActive: () => editor.value?.isActive('bold') ?? false,
        },
        {
            icon: Italic,
            title: 'Italic',
            action: () => editor.value?.chain().focus().toggleItalic().run(),
            isActive: () => editor.value?.isActive('italic') ?? false,
        },
        {
            icon: UnderlineIcon,
            title: 'Underline',
            action: () => editor.value?.chain().focus().toggleUnderline().run(),
            isActive: () => editor.value?.isActive('underline') ?? false,
        },
        {
            icon: Strikethrough,
            title: 'Strikethrough',
            action: () => editor.value?.chain().focus().toggleStrike().run(),
            isActive: () => editor.value?.isActive('strike') ?? false,
        },
        {
            icon: Code,
            title: 'Inline Code',
            action: () => editor.value?.chain().focus().toggleCode().run(),
            isActive: () => editor.value?.isActive('code') ?? false,
        },
    ],
    [
        {
            icon: Heading1,
            title: 'Heading 1',
            action: () => editor.value?.chain().focus().toggleHeading({ level: 1 }).run(),
            isActive: () => editor.value?.isActive('heading', { level: 1 }) ?? false,
        },
        {
            icon: Heading2,
            title: 'Heading 2',
            action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
            isActive: () => editor.value?.isActive('heading', { level: 2 }) ?? false,
        },
        {
            icon: Heading3,
            title: 'Heading 3',
            action: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(),
            isActive: () => editor.value?.isActive('heading', { level: 3 }) ?? false,
        },
    ],
    [
        {
            icon: AlignLeft,
            title: 'Align Left',
            action: () => editor.value?.chain().focus().setTextAlign('left').run(),
            isActive: () => editor.value?.isActive({ textAlign: 'left' }) ?? false,
        },
        {
            icon: AlignCenter,
            title: 'Align Center',
            action: () => editor.value?.chain().focus().setTextAlign('center').run(),
            isActive: () => editor.value?.isActive({ textAlign: 'center' }) ?? false,
        },
        {
            icon: AlignRight,
            title: 'Align Right',
            action: () => editor.value?.chain().focus().setTextAlign('right').run(),
            isActive: () => editor.value?.isActive({ textAlign: 'right' }) ?? false,
        },
        {
            icon: AlignJustify,
            title: 'Justify',
            action: () => editor.value?.chain().focus().setTextAlign('justify').run(),
            isActive: () => editor.value?.isActive({ textAlign: 'justify' }) ?? false,
        },
    ],
    [
        {
            icon: List,
            title: 'Bullet List',
            action: () => editor.value?.chain().focus().toggleBulletList().run(),
            isActive: () => editor.value?.isActive('bulletList') ?? false,
        },
        {
            icon: ListOrdered,
            title: 'Ordered List',
            action: () => editor.value?.chain().focus().toggleOrderedList().run(),
            isActive: () => editor.value?.isActive('orderedList') ?? false,
        },
        {
            icon: Quote,
            title: 'Blockquote',
            action: () => editor.value?.chain().focus().toggleBlockquote().run(),
            isActive: () => editor.value?.isActive('blockquote') ?? false,
        },
        {
            icon: CodeSquare,
            title: 'Code Block',
            action: () => editor.value?.chain().focus().toggleCodeBlock().run(),
            isActive: () => editor.value?.isActive('codeBlock') ?? false,
        },
        {
            icon: Minus,
            title: 'Horizontal Rule',
            action: () => editor.value?.chain().focus().setHorizontalRule().run(),
        },
    ],
    [
        {
            icon: LinkIcon,
            title: 'Add Link',
            action: setLink,
            isActive: () => editor.value?.isActive('link') ?? false,
        },
        {
            icon: Unlink,
            title: 'Remove Link',
            action: () => editor.value?.chain().focus().unsetLink().run(),
            disabled: () => !editor.value?.isActive('link'),
        },
        {
            icon: ImageIcon,
            title: 'Add Image',
            action: addImage,
        },
    ],
]);
</script>

<template>
    <div class="rounded-lg border bg-background">
        <div
            v-if="editor && editable"
            class="flex flex-wrap items-center gap-1 border-b bg-muted/30 p-2"
        >
            <template v-for="(group, groupIndex) in toolbarGroups" :key="groupIndex">
                <div class="flex items-center gap-0.5">
                    <Button
                        v-for="(button, buttonIndex) in group"
                        :key="buttonIndex"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 w-8 p-0"
                        :class="{
                            'bg-muted text-foreground': button.isActive?.(),
                            'text-muted-foreground': !button.isActive?.(),
                        }"
                        :disabled="button.disabled?.()"
                        :title="button.title"
                        @click="button.action"
                    >
                        <component :is="button.icon" class="h-4 w-4" />
                    </Button>
                </div>
                <div
                    v-if="groupIndex < toolbarGroups.length - 1"
                    class="mx-1 h-6 w-px bg-border"
                />
            </template>
        </div>
        <EditorContent
            :editor="editor"
            class="prose prose-sm dark:prose-invert max-w-none p-4 focus:outline-none [&_.ProseMirror]:min-h-[200px] [&_.ProseMirror]:outline-none [&_.ProseMirror_p.is-editor-empty:first-child::before]:text-muted-foreground [&_.ProseMirror_p.is-editor-empty:first-child::before]:content-[attr(data-placeholder)] [&_.ProseMirror_p.is-editor-empty:first-child::before]:float-left [&_.ProseMirror_p.is-editor-empty:first-child::before]:h-0 [&_.ProseMirror_p.is-editor-empty:first-child::before]:pointer-events-none"
        />
    </div>
</template>

<style>
.ProseMirror {
    outline: none;
}

.ProseMirror pre {
    background: hsl(var(--muted));
    border-radius: 0.5rem;
    padding: 1rem;
    overflow-x: auto;
}

.ProseMirror pre code {
    background: none;
    padding: 0;
    font-size: 0.875rem;
}

.ProseMirror code {
    background: hsl(var(--muted));
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.ProseMirror blockquote {
    border-left: 3px solid hsl(var(--primary));
    padding-left: 1rem;
    margin-left: 0;
    font-style: italic;
}

.ProseMirror hr {
    border: none;
    border-top: 2px solid hsl(var(--border));
    margin: 1.5rem 0;
}

.ProseMirror img {
    max-width: 100%;
    height: auto;
}

.ProseMirror ul,
.ProseMirror ol {
    padding-left: 1.5rem;
}

.ProseMirror h1 {
    font-size: 1.875rem;
    font-weight: 700;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.ProseMirror h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
}

.ProseMirror h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

/* Code block syntax highlighting */
.ProseMirror .hljs-comment,
.ProseMirror .hljs-quote {
    color: #6a737d;
}

.ProseMirror .hljs-keyword,
.ProseMirror .hljs-selector-tag,
.ProseMirror .hljs-addition {
    color: #d73a49;
}

.ProseMirror .hljs-string,
.ProseMirror .hljs-meta .hljs-string {
    color: #032f62;
}

.ProseMirror .hljs-number,
.ProseMirror .hljs-literal {
    color: #005cc5;
}

.ProseMirror .hljs-built_in,
.ProseMirror .hljs-title,
.ProseMirror .hljs-section {
    color: #6f42c1;
}

.dark .ProseMirror .hljs-comment,
.dark .ProseMirror .hljs-quote {
    color: #8b949e;
}

.dark .ProseMirror .hljs-keyword,
.dark .ProseMirror .hljs-selector-tag,
.dark .ProseMirror .hljs-addition {
    color: #ff7b72;
}

.dark .ProseMirror .hljs-string,
.dark .ProseMirror .hljs-meta .hljs-string {
    color: #a5d6ff;
}

.dark .ProseMirror .hljs-number,
.dark .ProseMirror .hljs-literal {
    color: #79c0ff;
}

.dark .ProseMirror .hljs-built_in,
.dark .ProseMirror .hljs-title,
.dark .ProseMirror .hljs-section {
    color: #d2a8ff;
}
</style>
