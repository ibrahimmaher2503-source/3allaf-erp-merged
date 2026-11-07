export function queryScalar(): never {
    throw new Error('UI acceptance DB checks must be implemented with a read-only MySQL adapter before use.');
}

export function assertLedgerValue(): never {
    throw new Error('UI acceptance ledger assertions require a configured read-only MySQL adapter.');
}
