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
console.log('PASS: exact/under/over card allocation, cash change, mixed payment');
