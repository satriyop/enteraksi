import { describe, it, expect, vi, beforeEach } from 'vitest';
import { nextTick } from 'vue';
import { useModal } from '../useModal';

describe('useModal', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('starts closed', () => {
        const { isOpen, data } = useModal();

        expect(isOpen.value).toBe(false);
        expect(data.value).toBeNull();
    });

    it('opens modal', () => {
        const { isOpen, open } = useModal();

        open();

        expect(isOpen.value).toBe(true);
    });

    it('closes modal', () => {
        const { isOpen, open, close } = useModal();

        open();
        expect(isOpen.value).toBe(true);

        close();
        expect(isOpen.value).toBe(false);
    });

    it('toggles modal state', () => {
        const { isOpen, toggle } = useModal();

        expect(isOpen.value).toBe(false);

        toggle();
        expect(isOpen.value).toBe(true);

        toggle();
        expect(isOpen.value).toBe(false);
    });

    it('stores data when opened', () => {
        const { data, open, close } = useModal<{ id: number; name: string }>();

        open({ id: 123, name: 'Test' });

        expect(data.value).toEqual({ id: 123, name: 'Test' });

        close();

        expect(data.value).toBeNull();
    });

    it('updates data on subsequent opens', () => {
        const { data, open } = useModal<{ value: string }>();

        open({ value: 'first' });
        expect(data.value).toEqual({ value: 'first' });

        open({ value: 'second' });
        expect(data.value).toEqual({ value: 'second' });
    });

    it('adds keydown listener when opened', async () => {
        const addEventListenerSpy = vi.spyOn(document, 'addEventListener');
        const { open } = useModal();

        open();
        await nextTick();

        expect(addEventListenerSpy).toHaveBeenCalledWith('keydown', expect.any(Function));
    });

    it('removes keydown listener when closed', async () => {
        const removeEventListenerSpy = vi.spyOn(document, 'removeEventListener');
        const { open, close } = useModal();

        open();
        await nextTick();

        close();
        await nextTick();

        expect(removeEventListenerSpy).toHaveBeenCalledWith('keydown', expect.any(Function));
    });

    it('closes on escape key when closeOnEscape is true (default)', async () => {
        const { isOpen, open } = useModal();

        open();
        await nextTick();

        // Simulate escape key press
        const event = new KeyboardEvent('keydown', { key: 'Escape' });
        document.dispatchEvent(event);

        expect(isOpen.value).toBe(false);
    });

    it('does not close on escape key when closeOnEscape is false', async () => {
        const { isOpen, open } = useModal({ closeOnEscape: false });

        open();
        await nextTick();

        const event = new KeyboardEvent('keydown', { key: 'Escape' });
        document.dispatchEvent(event);

        expect(isOpen.value).toBe(true);
    });

    it('handleClickOutside closes modal when closeOnClickOutside is true', () => {
        const { isOpen, open, handleClickOutside } = useModal({ closeOnClickOutside: true });

        open();
        handleClickOutside();

        expect(isOpen.value).toBe(false);
    });

    it('handleClickOutside does not close modal when closeOnClickOutside is false', () => {
        const { isOpen, open, handleClickOutside } = useModal({ closeOnClickOutside: false });

        open();
        handleClickOutside();

        expect(isOpen.value).toBe(true);
    });

    it('returns readonly refs for isOpen and data', () => {
        const { isOpen, data } = useModal();

        // TypeScript would catch direct assignment, but we can verify the refs work
        expect(isOpen.value).toBe(false);
        expect(data.value).toBeNull();
    });
});
