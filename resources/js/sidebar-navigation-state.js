export const SIDEBAR_STATE_KEY = 'rajeh_sidebar_navigation_v1';

const readState = (storage = window.localStorage) => {
    try {
        const state = JSON.parse(storage.getItem(SIDEBAR_STATE_KEY) || 'null');
        return state?.version === 1 && Array.isArray(state.open) ? state : null;
    } catch (ignored) {
        return null;
    }
};

export const captureSidebarState = (root = document, storage = window.localStorage) => {
    const navigation = root.querySelector('.app-navigation');
    if (!navigation) return null;
    const active = navigation.querySelector('.app-navigation__item[aria-current="page"]');
    const activeSection = active?.closest('[data-navigation-group]')?.dataset.navigationGroup ?? null;
    const state = {
        version: 1,
        scrollTop: Math.max(0, Math.round(navigation.scrollTop)),
        open: [...navigation.querySelectorAll('details[data-navigation-state][open]')]
            .map((details) => details.dataset.navigationState),
        activeSection,
    };
    try { storage.setItem(SIDEBAR_STATE_KEY, JSON.stringify(state)); } catch (ignored) {}
    return state;
};

export const restoreSidebarState = (root = document, storage = window.localStorage) => {
    const navigation = root.querySelector('.app-navigation');
    if (!navigation) return null;
    const state = readState(storage);
    const active = navigation.querySelector('.app-navigation__item[aria-current="page"]');
    const activeAncestors = new Set(
        active ? [...navigation.querySelectorAll('details[data-navigation-state]')]
            .filter((details) => details.contains(active))
            .map((details) => details.dataset.navigationState) : [],
    );

    if (state) {
        const storedOpen = new Set(state.open);
        navigation.querySelectorAll('details[data-navigation-state]').forEach((details) => {
            details.open = storedOpen.has(details.dataset.navigationState) || activeAncestors.has(details.dataset.navigationState);
        });
        navigation.scrollTop = Math.min(state.scrollTop, Math.max(0, navigation.scrollHeight - navigation.clientHeight));
    } else {
        activeAncestors.forEach((key) => {
            const details = [...navigation.querySelectorAll('details[data-navigation-state]')]
                .find((candidate) => candidate.dataset.navigationState === key);
            if (details) details.open = true;
        });
    }

    if (active) {
        const navigationRect = navigation.getBoundingClientRect();
        const activeRect = active.getBoundingClientRect();
        if (activeRect.top < navigationRect.top || activeRect.bottom > navigationRect.bottom) {
            active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
    }
    navigation.dataset.sidebarRestored = 'true';
    return { state, activeSection: active?.closest('[data-navigation-group]')?.dataset.navigationGroup ?? null };
};

const bindSidebarState = () => {
    const navigation = document.querySelector('.app-navigation');
    if (!navigation || navigation.dataset.sidebarStateBound === 'true') {
        restoreSidebarState();
        return;
    }
    navigation.dataset.sidebarStateBound = 'true';
    let frame = null;
    navigation.addEventListener('scroll', () => {
        if (frame !== null) cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => { captureSidebarState(); frame = null; });
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
