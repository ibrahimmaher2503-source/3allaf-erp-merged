export const SIDEBAR_STATE_KEY = 'rajeh_sidebar_navigation_v2';
export const SIDEBAR_SCROLL_SELECTOR = '[data-sidebar-scroll]';

const findSidebarScroller = (root = document) => {
    const matches = root.querySelectorAll(SIDEBAR_SCROLL_SELECTOR);
    return matches.length === 1 ? matches[0] : null;
};

const readState = (storage = window.localStorage) => {
    try {
        const state = JSON.parse(storage.getItem(SIDEBAR_STATE_KEY) || 'null');
        return state?.version === 2 && Number.isFinite(state.scrollTop) && Array.isArray(state.open) ? state : null;
    } catch (ignored) {
        return null;
    }
};

const openKeys = (navigation) => [...navigation.querySelectorAll('details[data-navigation-state][open]')]
    .map((details) => details.dataset.navigationState)
    .filter((key) => typeof key === 'string' && key !== '');

const maximumScrollTop = (navigation) => Math.max(0, navigation.scrollHeight - navigation.clientHeight);

const revealActiveInsideSidebar = (navigation, active) => {
    if (!active) return;

    const navigationRect = navigation.getBoundingClientRect();
    const activeRect = active.getBoundingClientRect();
    let delta = 0;

    if (activeRect.top < navigationRect.top) {
        delta = activeRect.top - navigationRect.top;
    } else if (activeRect.bottom > navigationRect.bottom) {
        delta = activeRect.bottom - navigationRect.bottom;
    }

    if (delta !== 0) {
        navigation.scrollTop = Math.min(
            maximumScrollTop(navigation),
            Math.max(0, Math.round(navigation.scrollTop + delta)),
        );
    }
};

export const captureSidebarState = (root = document, storage = window.localStorage) => {
    const navigation = findSidebarScroller(root);
    if (!navigation) return null;

    const state = {
        version: 2,
        scrollTop: Math.min(maximumScrollTop(navigation), Math.max(0, Math.round(navigation.scrollTop))),
        open: openKeys(navigation),
    };

    try {
        storage.setItem(SIDEBAR_STATE_KEY, JSON.stringify(state));
    } catch (ignored) {}

    return state;
};

export const restoreSidebarState = (root = document, storage = window.localStorage) => {
    const navigation = findSidebarScroller(root);
    if (!navigation) return null;

    const state = readState(storage);
    const active = navigation.querySelector('.app-navigation__item[aria-current="page"]');
    const details = [...navigation.querySelectorAll('details[data-navigation-state]')];
    const activeAncestors = new Set(
        active ? details
            .filter((candidate) => candidate.contains(active))
            .map((candidate) => candidate.dataset.navigationState) : [],
    );

    if (state) {
        const storedOpen = new Set(state.open);
        details.forEach((candidate) => {
            candidate.open = storedOpen.has(candidate.dataset.navigationState)
                || activeAncestors.has(candidate.dataset.navigationState);
        });
        navigation.scrollTop = Math.min(maximumScrollTop(navigation), Math.max(0, Math.round(state.scrollTop)));
    } else {
        details.forEach((candidate) => {
            if (activeAncestors.has(candidate.dataset.navigationState)) candidate.open = true;
        });
    }

    revealActiveInsideSidebar(navigation, active);
    navigation.dataset.sidebarRestored = 'true';

    return { state };
};

const bindSidebarState = () => {
    const navigation = findSidebarScroller(document);
    if (!navigation) return;

    if (navigation.dataset.sidebarStateBound === 'true') {
        restoreSidebarState();
        return;
    }

    navigation.dataset.sidebarStateBound = 'true';
    let frame = null;
    navigation.addEventListener('scroll', () => {
        if (frame !== null) cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            captureSidebarState();
            frame = null;
        });
    }, { passive: true });
    navigation.addEventListener('toggle', () => captureSidebarState(), true);
    navigation.addEventListener('click', (event) => {
        if (event.target.closest('a[href]')) captureSidebarState();
    }, true);
    restoreSidebarState();
};

document.addEventListener('DOMContentLoaded', bindSidebarState);
document.addEventListener('livewire:navigating', () => captureSidebarState());
document.addEventListener('livewire:navigated', bindSidebarState);
window.addEventListener('pagehide', () => captureSidebarState());
