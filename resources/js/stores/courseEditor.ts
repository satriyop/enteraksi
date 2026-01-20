// =============================================================================
// Course Editor Store (Provide/Inject Pattern)
// State management for course editing interface
// =============================================================================

import {
    provide,
    inject,
    ref,
    computed,
    readonly,
    type InjectionKey,
    type Ref,
    type ComputedRef,
} from 'vue';
import { router } from '@inertiajs/vue3';
import type { Course, CourseSection, Lesson } from '@/types';

// =============================================================================
// Types
// =============================================================================

interface CourseEditorState {
    /** Course being edited */
    course: Ref<Course>;
    /** Course sections with lessons */
    sections: Ref<CourseSection[]>;
    /** Whether there are unsaved changes */
    isDirty: Ref<boolean>;
    /** Currently selected section ID */
    selectedSectionId: Ref<number | null>;
    /** Currently selected lesson ID */
    selectedLessonId: Ref<number | null>;
    /** Whether save is in progress */
    isSaving: Ref<boolean>;
    /** Error message if any */
    error: Ref<string | null>;
}

interface CourseEditorComputed {
    /** Currently selected section */
    selectedSection: ComputedRef<CourseSection | null>;
    /** Currently selected lesson */
    selectedLesson: ComputedRef<Lesson | null>;
    /** Total number of lessons across all sections */
    totalLessons: ComputedRef<number>;
    /** Total duration in minutes */
    totalDuration: ComputedRef<number>;
    /** Whether the course can be published */
    canPublish: ComputedRef<boolean>;
}

interface CourseEditorActions {
    /** Select a section */
    selectSection: (id: number | null) => void;
    /** Select a lesson */
    selectLesson: (id: number | null) => void;
    /** Add a new section */
    addSection: (section: CourseSection) => void;
    /** Update an existing section */
    updateSection: (id: number, data: Partial<CourseSection>) => void;
    /** Delete a section */
    deleteSection: (id: number) => void;
    /** Add a new lesson to a section */
    addLesson: (sectionId: number, lesson: Lesson) => void;
    /** Update an existing lesson */
    updateLesson: (id: number, data: Partial<Lesson>) => void;
    /** Delete a lesson */
    deleteLesson: (id: number) => void;
    /** Reorder sections */
    reorderSections: (sectionIds: number[]) => void;
    /** Reorder lessons within a section */
    reorderLessons: (sectionId: number, lessonIds: number[]) => void;
    /** Move lesson to another section */
    moveLessonToSection: (lessonId: number, targetSectionId: number) => void;
    /** Save changes to server */
    save: () => Promise<boolean>;
    /** Discard unsaved changes */
    discardChanges: () => void;
    /** Mark state as dirty */
    markDirty: () => void;
    /** Clear error */
    clearError: () => void;
}

type CourseEditorContext = CourseEditorState & CourseEditorComputed & CourseEditorActions;

// =============================================================================
// Injection Key
// =============================================================================

const CourseEditorKey: InjectionKey<CourseEditorContext> = Symbol('CourseEditor');

// =============================================================================
// Provider
// =============================================================================

export function provideCourseEditor(
    initialCourse: Course,
    initialSections: CourseSection[]
): CourseEditorContext {
    // =============================================================================
    // State
    // =============================================================================

    const course = ref<Course>({ ...initialCourse });
    const sections = ref<CourseSection[]>(
        initialSections.map(s => ({
            ...s,
            lessons: s.lessons?.map(l => ({ ...l })) ?? [],
        }))
    );
    const isDirty = ref(false);
    const selectedSectionId = ref<number | null>(null);
    const selectedLessonId = ref<number | null>(null);
    const isSaving = ref(false);
    const error = ref<string | null>(null);

    // Store original state for discard
    let originalSections = JSON.stringify(initialSections);

    // =============================================================================
    // Computed
    // =============================================================================

    const selectedSection = computed<CourseSection | null>(() =>
        sections.value.find(s => s.id === selectedSectionId.value) ?? null
    );

    const selectedLesson = computed<Lesson | null>(() => {
        if (!selectedLessonId.value) return null;

        for (const section of sections.value) {
            const lesson = section.lessons?.find(l => l.id === selectedLessonId.value);
            if (lesson) return lesson;
        }
        return null;
    });

    const totalLessons = computed(() =>
        sections.value.reduce((sum, s) => sum + (s.lessons?.length ?? 0), 0)
    );

    const totalDuration = computed(() =>
        sections.value.reduce((sum, s) => {
            const sectionDuration = s.lessons?.reduce(
                (lessonSum, l) => lessonSum + (l.estimated_duration_minutes ?? 0),
                0
            ) ?? 0;
            return sum + sectionDuration;
        }, 0)
    );

    const canPublish = computed(() =>
        totalLessons.value > 0 &&
        course.value.title.length > 0 &&
        !isDirty.value
    );

    // =============================================================================
    // Actions
    // =============================================================================

    function selectSection(id: number | null): void {
        selectedSectionId.value = id;
        // Clear lesson selection when selecting a section
        if (id !== null) {
            selectedLessonId.value = null;
        }
    }

    function selectLesson(id: number | null): void {
        selectedLessonId.value = id;

        // Auto-select parent section
        if (id !== null) {
            for (const section of sections.value) {
                if (section.lessons?.some(l => l.id === id)) {
                    selectedSectionId.value = section.id;
                    break;
                }
            }
        }
    }

    function addSection(section: CourseSection): void {
        sections.value.push({
            ...section,
            lessons: section.lessons ?? [],
        });
        isDirty.value = true;
    }

    function updateSection(id: number, data: Partial<CourseSection>): void {
        const index = sections.value.findIndex(s => s.id === id);
        if (index > -1) {
            sections.value[index] = { ...sections.value[index], ...data };
            isDirty.value = true;
        }
    }

    function deleteSection(id: number): void {
        const index = sections.value.findIndex(s => s.id === id);
        if (index > -1) {
            sections.value.splice(index, 1);
            isDirty.value = true;

            // Clear selection if deleted
            if (selectedSectionId.value === id) {
                selectedSectionId.value = null;
                selectedLessonId.value = null;
            }
        }
    }

    function addLesson(sectionId: number, lesson: Lesson): void {
        const section = sections.value.find(s => s.id === sectionId);
        if (section) {
            section.lessons = section.lessons ?? [];
            section.lessons.push(lesson);
            isDirty.value = true;
        }
    }

    function updateLesson(id: number, data: Partial<Lesson>): void {
        for (const section of sections.value) {
            const lessonIndex = section.lessons?.findIndex(l => l.id === id) ?? -1;
            if (lessonIndex > -1 && section.lessons) {
                section.lessons[lessonIndex] = {
                    ...section.lessons[lessonIndex],
                    ...data,
                };
                isDirty.value = true;
                return;
            }
        }
    }

    function deleteLesson(id: number): void {
        for (const section of sections.value) {
            const index = section.lessons?.findIndex(l => l.id === id) ?? -1;
            if (index > -1 && section.lessons) {
                section.lessons.splice(index, 1);
                isDirty.value = true;

                // Clear selection if deleted
                if (selectedLessonId.value === id) {
                    selectedLessonId.value = null;
                }
                return;
            }
        }
    }

    function reorderSections(sectionIds: number[]): void {
        const reordered = sectionIds
            .map(id => sections.value.find(s => s.id === id))
            .filter((s): s is CourseSection => s !== undefined)
            .map((s, index) => ({ ...s, order: index + 1 }));

        sections.value = reordered;
        isDirty.value = true;
    }

    function reorderLessons(sectionId: number, lessonIds: number[]): void {
        const section = sections.value.find(s => s.id === sectionId);
        if (section && section.lessons) {
            const reordered = lessonIds
                .map(id => section.lessons!.find(l => l.id === id))
                .filter((l): l is Lesson => l !== undefined)
                .map((l, index) => ({ ...l, order: index + 1 }));

            section.lessons = reordered;
            isDirty.value = true;
        }
    }

    function moveLessonToSection(lessonId: number, targetSectionId: number): void {
        // Find and remove from current section
        let movedLesson: Lesson | undefined;

        for (const section of sections.value) {
            const index = section.lessons?.findIndex(l => l.id === lessonId) ?? -1;
            if (index > -1 && section.lessons) {
                [movedLesson] = section.lessons.splice(index, 1);
                break;
            }
        }

        // Add to target section
        if (movedLesson) {
            const targetSection = sections.value.find(s => s.id === targetSectionId);
            if (targetSection) {
                targetSection.lessons = targetSection.lessons ?? [];
                movedLesson.course_section_id = targetSectionId;
                movedLesson.order = targetSection.lessons.length + 1;
                targetSection.lessons.push(movedLesson);
                isDirty.value = true;
            }
        }
    }

    async function save(): Promise<boolean> {
        if (!isDirty.value) return true;

        isSaving.value = true;
        error.value = null;

        return new Promise((resolve) => {
            router.put(`/courses/${course.value.id}/curriculum`, {
                sections: sections.value.map((s, sIndex) => ({
                    id: s.id,
                    title: s.title,
                    description: s.description,
                    order: sIndex + 1,
                    lessons: s.lessons?.map((l, lIndex) => ({
                        id: l.id,
                        title: l.title,
                        order: lIndex + 1,
                    })) ?? [],
                })),
            }, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    isDirty.value = false;
                    originalSections = JSON.stringify(sections.value);
                    isSaving.value = false;
                    resolve(true);
                },
                onError: (errors) => {
                    error.value = typeof errors === 'object'
                        ? Object.values(errors)[0] as string
                        : 'Gagal menyimpan perubahan';
                    isSaving.value = false;
                    resolve(false);
                },
            });
        });
    }

    function discardChanges(): void {
        sections.value = JSON.parse(originalSections);
        isDirty.value = false;
        error.value = null;
    }

    function markDirty(): void {
        isDirty.value = true;
    }

    function clearError(): void {
        error.value = null;
    }

    // =============================================================================
    // Context
    // =============================================================================

    const context: CourseEditorContext = {
        // State
        course,
        sections,
        isDirty: readonly(isDirty),
        selectedSectionId: readonly(selectedSectionId),
        selectedLessonId: readonly(selectedLessonId),
        isSaving: readonly(isSaving),
        error: readonly(error),

        // Computed
        selectedSection,
        selectedLesson,
        totalLessons,
        totalDuration,
        canPublish,

        // Actions
        selectSection,
        selectLesson,
        addSection,
        updateSection,
        deleteSection,
        addLesson,
        updateLesson,
        deleteLesson,
        reorderSections,
        reorderLessons,
        moveLessonToSection,
        save,
        discardChanges,
        markDirty,
        clearError,
    };

    provide(CourseEditorKey, context);

    return context;
}

// =============================================================================
// Consumer Hook
// =============================================================================

export function useCourseEditor(): CourseEditorContext {
    const context = inject(CourseEditorKey);

    if (!context) {
        throw new Error(
            'useCourseEditor must be used within a component that calls provideCourseEditor'
        );
    }

    return context;
}

// =============================================================================
// Optional: Check if context exists (non-throwing version)
// =============================================================================

export function useCourseEditorOptional(): CourseEditorContext | null {
    return inject(CourseEditorKey) ?? null;
}
