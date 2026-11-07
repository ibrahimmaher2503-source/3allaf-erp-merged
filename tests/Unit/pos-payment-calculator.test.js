import assert from 'node:assert/strict';

globalThis.window = {};
const { summarizePaymentParts, paymentProgress } = await import('../../resources/js/pos-payment-calculator.js');
const field = { value: '30' };
const part = {
    hasAttribute: () => false,
    querySelector: (selector) => selector === '[data-payment-method]' ? { value: '2' } : field,
};
const calculator = window.posPaymentCalculator({ payable: '30' });
calculator.$el = { querySelectorAll: () => [] };
calculator.$root = { querySelector: () => null, querySelectorAll: () => [part] };
assert.equal(calculator.canComplete(), true, 'Exact card amount must use the dialog fields');
for (const amount of ['29', '31', '', '-1']) {
    field.value = amount;
    assert.equal(calculator.canComplete(), false, `Reject allocation ${amount}`);
}
assert.deepEqual(summarizePaymentParts('30', [{ methodId: '1', isCash: true, tendered: '32' }]), { valid: true, remaining: 0n, change: 200n });
assert.equal(summarizePaymentParts('30', [{ methodId: '2', amount: '10' }, { methodId: '1', isCash: true, tendered: '20' }]).valid, true);
assert.deepEqual(paymentProgress('2100.00', [{ methodId: '1', isCash: true, tendered: '2200.00' }]), { paid: 210000n, remaining: 0n, change: 10000n });
assert.deepEqual(paymentProgress('2100.00', [{ methodId: '2', amount: '500.00' }, { methodId: '1', isCash: true, tendered: '1000.00' }]), { paid: 150000n, remaining: 60000n, change: 0n });
assert.deepEqual(paymentProgress('2100.00', [{ methodId: '2', amount: '500.00' }, { methodId: '1', isCash: true, tendered: '1700.00' }]), { paid: 210000n, remaining: 0n, change: 10000n });
const cashField = { value: '50.00' };
const remainingField = { value: '0.00' };
const cashPart = { hasAttribute: (name) => name === 'data-cash-payment', querySelector: (selector) => selector === '[data-cash-received]' ? cashField : null };
const cardPart = { hasAttribute: () => false, querySelector: (selector) => selector === '[data-auto-remaining]' || selector === '[data-noncash-amount]' ? remainingField : null };
const splitCalculator = window.posPaymentCalculator({ payable: '64.00' });
splitCalculator.$root = { querySelector: () => null, querySelectorAll: () => [cashPart, cardPart] };
splitCalculator.syncRemainingDefaults();
assert.equal(remainingField.value, '14.00', 'Cash 50 leaves 14 for the card portion');
cashField.value = '40.00';
splitCalculator.syncRemainingDefaults();
assert.equal(remainingField.value, '24.00', 'Editing cash live updates the default second portion');
const firstCash = { value: '500.00' };
const secondCard = { value: '0.00', auto: true };
const thirdCard = { value: '0.00', auto: true };
const portion = (field, cash = false) => ({
    hasAttribute: (name) => cash && name === 'data-cash-payment',
    querySelector: (selector) => {
        if (selector === '[data-auto-remaining]') return field.auto ? field : null;
        if (selector === '[data-cash-received]') return cash ? field : null;
        if (selector === '[data-noncash-amount]') return cash ? null : field;
        return null;
    },
});
const portions = [portion(firstCash, true), portion(secondCard)];
const smartSplit = window.posPaymentCalculator({ payable: '650.00' });
smartSplit.$root = { querySelector: () => null, querySelectorAll: () => portions };
smartSplit.syncRemainingDefaults();
assert.equal(secondCard.value, '150.00', 'Second split part autofills the remaining 150');
assert.equal(summarizePaymentParts('650.00', [{ methodId: 'cash', isCash: true, tendered: firstCash.value }, { methodId: 'card', amount: secondCard.value }]).valid, true);
secondCard.auto = false;
secondCard.value = '120.00';
smartSplit.syncRemainingDefaults();
assert.equal(secondCard.value, '120.00', 'Manual override is not changed');
portions.push(portion(thirdCard));
smartSplit.syncRemainingDefaults();
assert.equal(thirdCard.value, '30.00', 'Third part receives the remaining amount after override');
assert.equal(summarizePaymentParts('650.00', [{ methodId: 'card', amount: '500.00' }, { methodId: 'wallet', amount: '151.00' }]).valid, false, 'Noncash split cannot exceed invoice total');
assert.equal(summarizePaymentParts('650.00', [{ methodId: 'cash', isCash: true, tendered: '500.00' }, { methodId: 'card', amount: '151.00' }]).change, 100n, 'Cash excess remains customer change');
console.log('PASS: exact/under/over card allocation, cash change, mixed payment');

const modeField = { value: 'credit' };
const eligibleField = { value: '1' };
const modeCalculator = window.posPaymentCalculator({ payable: '30', allowOutstanding: false, creditOnly: false });
let currentParts = [];
modeCalculator.$root = {
    querySelector: (selector) => selector === '[name="payment_mode"]' ? modeField : selector === '[data-pos-credit-eligible]' ? eligibleField : null,
    querySelectorAll: () => currentParts,
};
assert.equal(modeCalculator.canComplete(), true, 'Livewire switching to credit must update retained Alpine calculator');
modeField.value = 'card';
field.value = '30';
currentParts = [part];
assert.equal(modeCalculator.canComplete(), true, 'Switching back to card must allow exact payment');
eligibleField.value = '0';
field.value = '10';
assert.equal(modeCalculator.canComplete(), false, 'Changing to a cash-only customer must reject partial payment');
