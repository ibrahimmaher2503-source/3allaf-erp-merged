// Native date inputs render in the browser's locale (mm/dd/yyyy on en-US browsers).
// Keep the native control (picker, keyboard entry, ISO form value, Livewire binding) and
// draw the value as dd/mm/yyyy on top of it.
const FORMAT_CLASS = 'date-fmt';
const PLACEHOLDER = 'dd/mm/yyyy';

function format(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');

    return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
}

function position(input, label) {
    const host = input.offsetParent || input.parentElement;
    if (!host) return;
    const rtl = getComputedStyle(input).direction === 'rtl';
    const padding = 12;
    label.style.top = `${input.offsetTop}px`;
    label.style.height = `${input.offsetHeight}px`;
    label.style.lineHeight = `${input.offsetHeight}px`;
    if (rtl) {
        label.style.left = 'auto';
        label.style.right = `${host.clientWidth - input.offsetLeft - input.offsetWidth + padding}px`;
    } else {
        label.style.right = 'auto';
        label.style.left = `${input.offsetLeft + padding}px`;
    }
}

function refresh(input) {
    const label = input.__dateLabel;
    if (!label || !label.isConnected) return;
    const text = format(input.value);
    const shown = text || PLACEHOLDER;
    if (label.textContent !== shown) label.textContent = shown;
    label.classList.toggle('date-fmt--empty', text === '');
    label.classList.toggle('date-fmt--disabled', input.disabled);
    position(input, label);
}

function enhance(input) {
    if (input.type !== 'date' || input.dataset.nativeDate !== undefined) return;
    if (input.__dateLabel && input.__dateLabel.isConnected) return;
    const parent = input.parentElement;
    if (!parent || input.closest('[hidden]')) return;
    if (getComputedStyle(parent).position === 'static') parent.style.position = 'relative';
    const label = document.createElement('span');
    label.className = FORMAT_CLASS;
    label.setAttribute('aria-hidden', 'true');
    input.after(label);
    input.__dateLabel = label;
    input.classList.add('date-fmt-input');
    ['input', 'change', 'focus', 'blur'].forEach((name) => input.addEventListener(name, () => refresh(input)));
    refresh(input);
}

function scan(root = document) {
    root.querySelectorAll('input[type="date"]').forEach(enhance);
}

function refreshAll() {
    document.querySelectorAll('input.date-fmt-input').forEach((input) => {
        if (!input.__dateLabel || !input.__dateLabel.isConnected) {
            input.classList.remove('date-fmt-input');
            enhance(input);
        } else {
            refresh(input);
        }
    });
}

function start() {
    scan();
    new MutationObserver((records) => {
        for (const record of records) {
            record.addedNodes.forEach((node) => {
                if (node.nodeType === 1 && !node.classList.contains(FORMAT_CLASS)) {
                    if (node.matches?.('input[type="date"]')) enhance(node);
                    else scan(node);
                }
            });
        }
        if (records.some((record) => [...record.addedNodes, ...record.removedNodes].some((node) => node.nodeType === 1 && !node.classList.contains(FORMAT_CLASS)))) refreshAll();
    }).observe(document.body, { childList: true, subtree: true });
    window.addEventListener('resize', refreshAll);
    // Livewire sets .value programmatically (no input event), so keep labels in sync.
    setInterval(refreshAll, 600);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
else start();
