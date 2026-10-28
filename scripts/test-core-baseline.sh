#!/usr/bin/env bash
set -Eeuo pipefail

# Rajeh v2.0 Core gate. The existing QA runner creates a socket-only disposable
# MariaDB database and refuses a worktree with Production environment/cache files.
root=$(git rev-parse --show-toplevel)
cd "$root"
[[ ! -e .qa-runtime/last-db ]] || { echo 'FAIL: an existing QA runtime must be preserved; use a clean worktree' >&2; exit 1; }
[[ ! -f .env && ! -f bootstrap/cache/config.php ]] || { echo 'FAIL: Core gate requires an isolated worktree without Production configuration' >&2; exit 1; }
[[ -x vendor/bin/phpunit ]] || { echo 'FAIL: branch-local Composer dependencies are required' >&2; exit 1; }

cleanup() {
    if [[ -f .qa-runtime/last-db ]]; then
        mapfile -t runtime < .qa-runtime/last-db
        if [[ ${runtime[0]:-} == /tmp/rajeh-qa-mariadb.* && ${runtime[1]:-} == qa_rajh_* && -O ${runtime[0]:-} ]]; then
            mariadb-admin --no-defaults --protocol=socket --socket="${runtime[0]}/mariadb.sock" -uroot shutdown >/dev/null 2>&1 || true
            rm -rf -- "${runtime[0]}"
            rm -- .qa-runtime/last-db
        else
            echo 'WARN: QA runtime metadata did not match this disposable gate; preserved for review' >&2
        fi
    fi
}
trap cleanup EXIT

echo 'Core suite: smart split and payment calculations'
PATH="/opt/cpanel/ea-nodejs20/bin:$PATH" node tests/Unit/pos-payment-calculator.test.js || { echo 'FAIL: smart split and payment calculations' >&2; exit 1; }

reuse=0
suite() {
    local label=$1 file=$2 filter=$3
    echo "Core suite: $label"
    if ! QA_REUSE_RUNTIME="$reuse" QA_PRESERVE_RUNTIME=1 bash scripts/qa/mariadb-run.sh focused "$file" --filter "$filter"; then
        echo "FAIL: $label" >&2
        exit 1
    fi
    reuse=1
}

suite 'POS sale and payment posting' tests/Feature/Module5PosQaTest.php 'test_card_checkout_rejects_wrong_allocations_and_posts_once|test_cash_and_mixed_checkout_use_the_approved_rounding_setting|test_exact_cash_sale_posts_once_when_rounding_is_disabled'
suite 'inventory movement, balance, purchase receipt, and active stocktake' tests/Feature/InventoryLiveStocktakeMovementValidationTest.php 'test_pos_sale_during_active_count_posts_once_and_reconciles|test_customer_return_during_active_count_posts_once_and_reconciles|test_purchase_receipt_and_combined_count_boundary_use_canonical_movements'
suite 'multi-destination transfer, automatic dispatch, partial receipt, and duplicate protection' tests/Feature/InventoryMultiDestinationTransferFocusedTest.php 'test_dispatches_one_line_to_two_destinations_once_and_preserves_independence|test_over_allocation_is_rejected_before_any_movement|test_receive_is_independent_per_destination_and_idempotent|test_review_confirmation_posts_only_entered_actual_quantity_and_keeps_other_destination_pending|test_reviewer_edits_allocations_and_approval_dispatches_final_values_atomically|test_failed_automatic_dispatch_rolls_back_review_and_approval_and_preserves_input'
suite 'stocktake settlement and scope' tests/Feature/InventoryLiveStocktakeFocusedTest.php 'test_live_count_uses_each_products_count_boundary_and_settles_only_variances|test_hidden_warehouse_and_settlement_require_authorization'
suite 'linked and unverified returns, partial limits, refund idempotency, and permission denial' tests/Feature/QA/PosSmartSplitReturnsTest.php 'test_unverified_return_posts_manager_amount_stock_refund_and_audit_once|test_unverified_return_denies_an_ungranted_cashier|test_linked_return_uses_original_economics_split_refund_and_remaining_quantity|test_return_stock_mapping_rejects_a_different_store_and_missing_pos_context'
suite 'cash movement and expected physical cash' tests/Feature/Module9CashShiftsQaTest.php 'test_m9_06_movement_screen_and_report_show_existing_actor_and_reference|test_cash_expected_uses_signed_movements_and_cash_refunds_but_excludes_card'
suite 'ordinary-cashier blind close secrecy' tests/Feature/QA/CommercialBlindCloseTest.php 'test_ordinary_cashier_sees_only_counted_cash_and_cannot_infer_expected_from_prints'

echo 'PASS: Rajeh v2.0 Core regression gate'
