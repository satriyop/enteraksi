import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import FlashMessages from '../FlashMessages.vue';

// Mock usePage specifically for this test file
vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual('@inertiajs/vue3');
    return {
        ...actual,
        usePage: vi.fn(),
    };
});

describe('FlashMessages.vue', () => {
    it('renders success message when present in flash props', () => {
        (usePage as any).mockReturnValue({
            props: {
                flash: {
                    success: 'Operation successful!',
                },
            },
        });

        const wrapper = mount(FlashMessages);

        expect(wrapper.text()).toContain('Operation successful!');
        expect(wrapper.find('.bg-green-50').exists()).toBe(true);
    });

    it('renders error message when present in flash props', () => {
        (usePage as any).mockReturnValue({
            props: {
                flash: {
                    error: 'Something went wrong.',
                },
            },
        });

        const wrapper = mount(FlashMessages);

        expect(wrapper.text()).toContain('Something went wrong.');
        expect(wrapper.find('.bg-red-50').exists()).toBe(true);
    });

    it('renders both messages when both are present', () => {
        (usePage as any).mockReturnValue({
            props: {
                flash: {
                    success: 'Saved!',
                    error: 'But with some warnings.',
                },
            },
        });

        const wrapper = mount(FlashMessages);

        expect(wrapper.text()).toContain('Saved!');
        expect(wrapper.text()).toContain('But with some warnings.');
    });

    it('renders nothing when flash props are empty', () => {
        (usePage as any).mockReturnValue({
            props: {
                flash: {},
            },
        });

        const wrapper = mount(FlashMessages);

        expect(wrapper.html()).toBe('<!--v-if-->');
    });

    it('renders nothing when flash props are missing', () => {
        (usePage as any).mockReturnValue({
            props: {},
        });

        const wrapper = mount(FlashMessages);

        expect(wrapper.html()).toBe('<!--v-if-->');
    });
});
