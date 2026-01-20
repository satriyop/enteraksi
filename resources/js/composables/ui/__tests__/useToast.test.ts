import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useToast } from '../useToast';

describe('useToast', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        // Clear any existing toasts
        const { toasts, clear } = useToast();
        clear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('starts with empty toasts array', () => {
        const { toasts, clear } = useToast();
        // Clear any leftover toasts from singleton
        clear();

        expect(toasts.value).toHaveLength(0);
    });

    it('adds success toast', () => {
        const { toasts, success } = useToast();

        success({ title: 'Operation successful' });

        expect(toasts.value).toHaveLength(1);
        expect(toasts.value[0].type).toBe('success');
        expect(toasts.value[0].title).toBe('Operation successful');
    });

    it('adds error toast', () => {
        const { toasts, error } = useToast();

        error({ title: 'Something went wrong' });

        expect(toasts.value).toHaveLength(1);
        expect(toasts.value[0].type).toBe('error');
        expect(toasts.value[0].title).toBe('Something went wrong');
    });

    it('adds warning toast', () => {
        const { toasts, warning } = useToast();

        warning({ title: 'Please be careful' });

        expect(toasts.value).toHaveLength(1);
        expect(toasts.value[0].type).toBe('warning');
        expect(toasts.value[0].title).toBe('Please be careful');
    });

    it('adds info toast', () => {
        const { toasts, info } = useToast();

        info({ title: 'Here is some information' });

        expect(toasts.value).toHaveLength(1);
        expect(toasts.value[0].type).toBe('info');
        expect(toasts.value[0].title).toBe('Here is some information');
    });

    it('adds toast with title and message', () => {
        const { toasts, success } = useToast();

        success({ title: 'Custom Title', message: 'Detailed message' });

        expect(toasts.value[0].title).toBe('Custom Title');
        expect(toasts.value[0].message).toBe('Detailed message');
    });

    it('dismisses toast by id', () => {
        const { toasts, success, dismiss } = useToast();

        success({ title: 'First' });
        success({ title: 'Second' });

        const firstId = toasts.value[0].id;
        dismiss(firstId);

        expect(toasts.value).toHaveLength(1);
        expect(toasts.value[0].title).toBe('Second');
    });

    it('clears all toasts', () => {
        const { toasts, success, error, clear } = useToast();

        success({ title: 'First' });
        error({ title: 'Second' });
        success({ title: 'Third' });

        expect(toasts.value).toHaveLength(3);

        clear();

        expect(toasts.value).toHaveLength(0);
    });

    it('auto-removes toast after duration', () => {
        const { toasts, success } = useToast();

        success({ title: 'Auto dismiss', duration: 3000 });

        expect(toasts.value).toHaveLength(1);

        vi.advanceTimersByTime(3000);

        expect(toasts.value).toHaveLength(0);
    });

    it('does not auto-remove when duration is 0', () => {
        const { toasts, success } = useToast();

        success({ title: 'Persistent', duration: 0 });

        expect(toasts.value).toHaveLength(1);

        vi.advanceTimersByTime(10000);

        expect(toasts.value).toHaveLength(1);
    });

    it('generates unique ids for toasts', () => {
        const { toasts, success } = useToast();

        success({ title: 'First' });
        success({ title: 'Second' });
        success({ title: 'Third' });

        const ids = toasts.value.map(t => t.id);
        const uniqueIds = new Set(ids);

        expect(uniqueIds.size).toBe(3);
    });

    it('maintains singleton state across multiple useToast calls', () => {
        const toast1 = useToast();
        const toast2 = useToast();

        toast1.success({ title: 'From instance 1' });

        expect(toast2.toasts.value).toHaveLength(1);
        expect(toast2.toasts.value[0].title).toBe('From instance 1');
    });

    it('adds multiple toasts in order', () => {
        const { toasts, success, error, info } = useToast();

        success({ title: 'First' });
        error({ title: 'Second' });
        info({ title: 'Third' });

        expect(toasts.value).toHaveLength(3);
        expect(toasts.value[0].title).toBe('First');
        expect(toasts.value[1].title).toBe('Second');
        expect(toasts.value[2].title).toBe('Third');
    });

    it('uses default duration when not specified', () => {
        const { toasts, success } = useToast();

        success({ title: 'Default duration' });

        expect(toasts.value).toHaveLength(1);

        // Default is TOAST_DURATION.normal (5000ms)
        vi.advanceTimersByTime(5000);

        expect(toasts.value).toHaveLength(0);
    });

    it('error toasts have longer default duration', () => {
        const { toasts, error } = useToast();

        error({ title: 'Error message' });

        expect(toasts.value).toHaveLength(1);

        // After normal duration (5000ms), error should still be there
        vi.advanceTimersByTime(5000);
        expect(toasts.value).toHaveLength(1);

        // After long duration (8000ms total), error should be gone
        vi.advanceTimersByTime(3000);
        expect(toasts.value).toHaveLength(0);
    });

    it('returns toast id when adding', () => {
        const { success } = useToast();

        const id = success({ title: 'Test' });

        expect(typeof id).toBe('number');
        expect(id).toBeGreaterThan(0);
    });

    it('can use add method directly with type', () => {
        const { toasts, add } = useToast();

        add('warning', { title: 'Direct add' });

        expect(toasts.value).toHaveLength(1);
        expect(toasts.value[0].type).toBe('warning');
        expect(toasts.value[0].title).toBe('Direct add');
    });
});
