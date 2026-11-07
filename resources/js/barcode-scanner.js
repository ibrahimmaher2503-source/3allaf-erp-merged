// USB / Bluetooth barcode scanners in "keyboard wedge" mode behave like a very fast keyboard that
// ends each code with Enter (or Tab). They need no driver, but the keystrokes land wherever focus is.
// This keeps focus on the page's scan field (`[data-scan-target]`) so a scan always reaches it.
const isEditable = (element) => element instanceof HTMLElement
    && (element.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName));

const scanTarget = () => {
    const target = document.querySelector('[data-scan-target]');
    return target && target.offsetParent !== null && !target.disabled ? target : null;
};

const modalOpen = () => document.querySelector('dialog[open], [role="dialog"]:not([hidden]), [aria-modal="true"]');

document.addEventListener('keydown', (event) => {
    if (event.ctrlKey || event.metaKey || event.altKey || event.isComposing) return;
    if (isEditable(event.target) || modalOpen()) return;
    const target = scanTarget();
    if (!target) return;
    const printable = event.key.length === 1;
    // Some scanners are programmed with a Tab terminator; ignore a stray Tab/Enter when nothing was typed.
    if (!printable) return;
    target.focus({ preventScroll: true });
}, true);

// A scanner may send Tab instead of Enter: treat it as Enter on the scan field.
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab' || !event.target?.matches?.('[data-scan-target]') || !event.target.value.trim()) return;
    event.preventDefault();
    event.target.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', bubbles: true, cancelable: true }));
    event.target.form?.requestSubmit?.();
}, true);

const refocus = () => {
    const target = scanTarget();
    if (target && !isEditable(document.activeElement) && !modalOpen()) target.focus({ preventScroll: true });
};
document.addEventListener('livewire:initialized', () => window.Livewire?.hook?.('morphed', () => setTimeout(refocus, 0)));
document.addEventListener('livewire:navigated', () => setTimeout(refocus, 50));
