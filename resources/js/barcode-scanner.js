// Global support for USB/Bluetooth barcode scanners in keyboard-emulation (HID keyboard) mode.
//
// Such scanners use the operating system's standard keyboard driver: they "type" the code very
// fast and finish with Enter (or Tab). This module:
//   1. Detects that fast burst and tells it apart from human typing.
//   2. Rebuilds the code from physical keys (event.code), so an Arabic keyboard layout cannot turn
//      letters in a SKU into Arabic characters.
//   3. Delivers the code to the page's scan field even when the cursor is not in it, and stops the
//      trailing Enter from clicking whatever button happens to be focused.

const MAX_KEY_GAP_MS = 80;      // a longer pause starts a new sequence
const MAX_AVERAGE_GAP_MS = 40;  // scanners type far faster than people
const MIN_LENGTH = 4;

const SHIFTED_DIGITS = { 0: ')', 1: '!', 2: '@', 3: '#', 4: '$', 5: '%', 6: '^', 7: '&', 8: '*', 9: '(' };
const PUNCTUATION = {
    Minus: ['-', '_'], Equal: ['=', '+'], BracketLeft: ['[', '{'], BracketRight: [']', '}'],
    Backslash: ['\\', '|'], Semicolon: [';', ':'], Quote: ["'", '"'], Backquote: ['`', '~'],
    Comma: [',', '<'], Period: ['.', '>'], Slash: ['/', '?'], Space: [' ', ' '],
    NumpadSubtract: ['-', '-'], NumpadAdd: ['+', '+'], NumpadMultiply: ['*', '*'],
    NumpadDivide: ['/', '/'], NumpadDecimal: ['.', '.'],
};
const ARABIC_DIGITS = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9' };

// Fields that already expect scanner input; a scan typed into them is kept there.
const SCAN_FIELDS = '[data-scan-target], [data-scan-input], [data-product-search]';
// Where a scan goes when the cursor is not in a text field (first visible match wins).
const DEFAULT_TARGETS = [
    '[data-scan-target]',
    '[data-stocktake-scan] [data-scan-input]',
    '[data-scan-input]',
    '[data-product-lookup]:not([data-context="catalog"]) [data-product-search]',
];

const physicalCharacter = (event) => {
    // A plain ASCII character is already exactly what the scanner sent (case included).
    if (/^[\x20-\x7E]$/.test(event.key)) return event.key;
    // Otherwise the active layout (e.g. Arabic) changed it: rebuild it from the physical key.
    const { code } = event;
    const shift = event.shiftKey;
    if (/^Key[A-Z]$/.test(code)) {
        const caps = event.getModifierState?.('CapsLock') ?? false;
        const letter = code.slice(3);
        return shift !== caps ? letter : letter.toLowerCase();
    }
    if (/^Digit\d$/.test(code)) return shift ? SHIFTED_DIGITS[code.slice(5)] : code.slice(5);
    if (/^Numpad\d$/.test(code)) return code.slice(6);
    if (PUNCTUATION[code]) return PUNCTUATION[code][shift ? 1 : 0];
    return ARABIC_DIGITS[event.key] ?? event.key;
};

const isEditable = (element) => element instanceof HTMLElement && (element.isContentEditable
    || element.matches('textarea, select, input:not([type="button"], [type="submit"], [type="reset"], [type="checkbox"], [type="radio"], [type="file"])'));

const isVisible = (element) => !element.disabled && element.getClientRects().length > 0;

const openModal = () => [...document.querySelectorAll('dialog[open], [role="dialog"]:not([hidden]), [aria-modal="true"]')].find(isVisible) ?? null;

const findDefaultTarget = () => {
    // With a dialog open, only a scan field inside that dialog may receive the code.
    const scope = openModal() ?? document;
    for (const selector of DEFAULT_TARGETS) {
        const candidates = [...scope.querySelectorAll(selector)].filter(isVisible);
        if (candidates.length) return candidates.find((candidate) => candidate.value.trim() === '') ?? candidates[0];
    }
    return null;
};

const setNativeValue = (input, value) => {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
};

const livewireComponent = (element) => {
    const id = element.closest('[wire\\:id]')?.getAttribute('wire:id');
    return id && window.Livewire?.find ? window.Livewire.find(id) : null;
};

let toastTimer = null;
const showToast = (code) => {
    let toast = document.getElementById('barcode-scanner-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'barcode-scanner-toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        Object.assign(toast.style, {
            position: 'fixed', left: '50%', bottom: '1.25rem', transform: 'translateX(-50%)', zIndex: '10001',
            padding: '.5rem .9rem', borderRadius: '.6rem', background: '#065f46', color: '#fff', font: '600 13px/1.4 system-ui, sans-serif',
            boxShadow: '0 8px 24px rgba(0,0,0,.18)', pointerEvents: 'none', transition: 'opacity .2s', unicodeBidi: 'plaintext',
        });
        document.body.appendChild(toast);
    }
    const arabic = (document.documentElement.lang || '').startsWith('ar');
    toast.textContent = `${arabic ? 'تمت قراءة الباركود' : 'Barcode scanned'}: ${code}`;
    toast.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.style.opacity = '0'; }, 1600);
};

const deliver = (code, target, typedIntoTarget) => {
    // Livewire fields: set the property and run the action in one request, so a pending
    // wire:model debounce can never submit a stale value.
    const binding = target.dataset.barcodeLivewire;
    const component = binding ? livewireComponent(target) : null;
    if (component) {
        const [property, method] = binding.split(':');
        target.value = code;
        component.set(property, code, false);
        if (method) component.call(method);
        return true; // handled: the real Enter must not submit again
    }

    if (typedIntoTarget) {
        // The characters are already in the field; only repair them if the keyboard layout changed them.
        if (target.value.trim() !== code) setNativeValue(target, code);
        return false; // let the scanner's own Enter reach the field's handlers
    }

    target.focus({ preventScroll: false });
    setNativeValue(target, code);
    const enter = new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', bubbles: true, cancelable: true });
    if (target.dispatchEvent(enter) && target.form) target.form.requestSubmit();
    return true;
};

const installBarcodeScanner = () => {
    if (window.__barcodeScannerInstalled) return;
    window.__barcodeScannerInstalled = true;

    let buffer = [];

    document.addEventListener('keydown', (event) => {
        if (!event.isTrusted || event.ctrlKey || event.altKey || event.metaKey || event.repeat) return;
        const now = performance.now();
        const last = buffer.at(-1);
        if (last && now - last.time > MAX_KEY_GAP_MS) buffer = [];

        if (event.key === 'Enter' || event.key === 'Tab') {
            const sequence = buffer;
            buffer = [];
            if (sequence.length < MIN_LENGTH || !last) return;
            const averageGap = (sequence.at(-1).time - sequence[0].time) / (sequence.length - 1);
            if (averageGap > MAX_AVERAGE_GAP_MS) return;

            const code = sequence.map((key) => key.character).join('').trim();
            if (!code) return;

            const focused = document.activeElement;
            const typedIntoField = isEditable(focused);
            let target = null;
            if (typedIntoField) {
                // A scan into an unrelated text field stays there; just repair the layout.
                if (!focused.matches(SCAN_FIELDS)) {
                    const raw = sequence.map((key) => key.raw).join('');
                    if (raw !== code && 'value' in focused && focused.value.endsWith(raw)) {
                        setNativeValue(focused, focused.value.slice(0, -raw.length) + code);
                    }
                    if (event.key === 'Tab') event.preventDefault();
                    return;
                }
                target = focused;
            } else {
                target = findDefaultTarget();
            }

            // From here on the terminator belongs to the scan, not to a focused button or focus order.
            if (!target) {
                event.preventDefault();
                showToast(code);
                window.dispatchEvent(new CustomEvent('barcode:scanned', { detail: { code, target: null } }));
                return;
            }

            const handled = deliver(code, target, typedIntoField);
            if (handled || event.key === 'Tab') {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
            showToast(code);
            window.dispatchEvent(new CustomEvent('barcode:scanned', { detail: { code, target } }));
            return;
        }

        if (event.key.length !== 1) return; // Shift, arrows, function keys, etc.
        buffer.push({ character: physicalCharacter(event), raw: event.key, time: now });
    }, true);

    // Keep the cursor in the page's main scan field after Livewire updates, so manual typing also lands there.
    const refocus = () => {
        const target = document.querySelector('[data-scan-target]');
        if (target && isVisible(target) && !isEditable(document.activeElement) && !openModal()) target.focus({ preventScroll: true });
    };
    document.addEventListener('livewire:initialized', () => window.Livewire?.hook?.('morphed', () => setTimeout(refocus, 0)));
    document.addEventListener('livewire:navigated', () => setTimeout(refocus, 50));
};

installBarcodeScanner();
