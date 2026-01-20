import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useTabs } from '../useTabs';

describe('useTabs', () => {
    const tabs = ['details', 'curriculum', 'reviews'] as const;

    beforeEach(() => {
        // Reset window location hash
        if (typeof window !== 'undefined') {
            window.history.replaceState(null, '', window.location.pathname);
        }
    });

    it('initializes with first tab as default', () => {
        const { currentTab, currentIndex } = useTabs(tabs);

        expect(currentTab.value).toBe('details');
        expect(currentIndex.value).toBe(0);
    });

    it('initializes with specified initial tab', () => {
        const { currentTab, currentIndex } = useTabs(tabs, { initialTab: 'curriculum' });

        expect(currentTab.value).toBe('curriculum');
        expect(currentIndex.value).toBe(1);
    });

    it('sets tab correctly', () => {
        const { currentTab, setTab } = useTabs(tabs);

        setTab('reviews');

        expect(currentTab.value).toBe('reviews');
    });

    it('ignores invalid tab', () => {
        const { currentTab, setTab } = useTabs(tabs);

        // @ts-expect-error - testing invalid tab
        setTab('invalid');

        expect(currentTab.value).toBe('details');
    });

    it('navigates to next tab', () => {
        const { currentTab, next } = useTabs(tabs);

        expect(currentTab.value).toBe('details');

        next();
        expect(currentTab.value).toBe('curriculum');

        next();
        expect(currentTab.value).toBe('reviews');
    });

    it('does not navigate past last tab', () => {
        const { currentTab, setTab, next } = useTabs(tabs);

        setTab('reviews');
        next();

        expect(currentTab.value).toBe('reviews');
    });

    it('navigates to previous tab', () => {
        const { currentTab, setTab, previous } = useTabs(tabs);

        setTab('reviews');
        expect(currentTab.value).toBe('reviews');

        previous();
        expect(currentTab.value).toBe('curriculum');

        previous();
        expect(currentTab.value).toBe('details');
    });

    it('does not navigate before first tab', () => {
        const { currentTab, previous } = useTabs(tabs);

        previous();

        expect(currentTab.value).toBe('details');
    });

    it('navigates to specific index', () => {
        const { currentTab, goToIndex } = useTabs(tabs);

        goToIndex(2);
        expect(currentTab.value).toBe('reviews');

        goToIndex(0);
        expect(currentTab.value).toBe('details');
    });

    it('ignores invalid index', () => {
        const { currentTab, goToIndex } = useTabs(tabs);

        goToIndex(-1);
        expect(currentTab.value).toBe('details');

        goToIndex(10);
        expect(currentTab.value).toBe('details');
    });

    it('computes isFirst correctly', () => {
        const { isFirst, setTab } = useTabs(tabs);

        expect(isFirst.value).toBe(true);

        setTab('curriculum');
        expect(isFirst.value).toBe(false);

        setTab('details');
        expect(isFirst.value).toBe(true);
    });

    it('computes isLast correctly', () => {
        const { isLast, setTab } = useTabs(tabs);

        expect(isLast.value).toBe(false);

        setTab('reviews');
        expect(isLast.value).toBe(true);

        setTab('curriculum');
        expect(isLast.value).toBe(false);
    });

    it('isActive returns correct state', () => {
        const { isActive, setTab } = useTabs(tabs);

        expect(isActive('details')).toBe(true);
        expect(isActive('curriculum')).toBe(false);
        expect(isActive('reviews')).toBe(false);

        setTab('curriculum');

        expect(isActive('details')).toBe(false);
        expect(isActive('curriculum')).toBe(true);
        expect(isActive('reviews')).toBe(false);
    });

    it('calls onChange callback when tab changes', () => {
        const onChange = vi.fn();
        const { setTab } = useTabs(tabs, { onChange });

        setTab('curriculum');

        expect(onChange).toHaveBeenCalledWith('curriculum', 'details');
    });

    it('does not call onChange when setting same tab', () => {
        const onChange = vi.fn();
        const { setTab } = useTabs(tabs, { onChange });

        setTab('details'); // Same as initial

        expect(onChange).not.toHaveBeenCalled();
    });

    it('getTabProps returns correct attributes', () => {
        const { getTabProps, setTab } = useTabs(tabs);

        const props = getTabProps('curriculum');

        expect(props['aria-selected']).toBe(false);
        expect(props['aria-controls']).toBe('panel-curriculum');
        expect(props.tabindex).toBe(-1);
        expect(typeof props.onClick).toBe('function');

        setTab('curriculum');
        const activeProps = getTabProps('curriculum');

        expect(activeProps['aria-selected']).toBe(true);
        expect(activeProps.tabindex).toBe(0);
    });

    it('getPanelProps returns correct attributes', () => {
        const { getPanelProps, setTab } = useTabs(tabs);

        const detailsProps = getPanelProps('details');
        expect(detailsProps.id).toBe('panel-details');
        expect(detailsProps.role).toBe('tabpanel');
        expect(detailsProps.hidden).toBe(false);

        const curriculumProps = getPanelProps('curriculum');
        expect(curriculumProps.hidden).toBe(true);

        setTab('curriculum');
        const updatedCurriculumProps = getPanelProps('curriculum');
        expect(updatedCurriculumProps.hidden).toBe(false);
    });

    describe('with URL hash persistence', () => {
        afterEach(() => {
            window.history.replaceState(null, '', window.location.pathname);
        });

        it('updates URL hash when persistToHash is true', () => {
            const { setTab } = useTabs(tabs, { persistToHash: true });

            setTab('curriculum');

            expect(window.location.hash).toBe('#curriculum');
        });

        it('does not update URL hash when persistToHash is false', () => {
            const { setTab } = useTabs(tabs, { persistToHash: false });

            setTab('curriculum');

            expect(window.location.hash).toBe('');
        });

        it('reads initial tab from URL hash', () => {
            window.location.hash = '#reviews';

            const { currentTab } = useTabs(tabs, { persistToHash: true });

            expect(currentTab.value).toBe('reviews');
        });

        it('ignores invalid hash value', () => {
            window.location.hash = '#invalid';

            const { currentTab } = useTabs(tabs, { persistToHash: true });

            expect(currentTab.value).toBe('details');
        });
    });
});
