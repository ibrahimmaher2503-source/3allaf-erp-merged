#!/usr/bin/env bash
set -Eeuo pipefail
root=${QA_ROOT:-$(git rev-parse --show-toplevel)}
base=${QA_BASE:-origin/main}
mapfile -t changed < <({ git -C "$root" diff --name-only "$base" -- '*.php'; git -C "$root" ls-files --others --exclude-standard -- '*.php'; } | sort -u)
if [[ ${#changed[@]} -eq 0 ]]; then
  changed=(app/Modules/Retail/Services/PosCalculationService.php app/Modules/Purchasing/Services/PurchaseInvoiceCalculator.php app/Modules/Catalog/Actions/SaveProductAction.php app/Modules/Retail/Actions/RetailSaleAction.php)
fi
count=0
for file in "${changed[@]}"; do [[ -f "$root/$file" ]] || continue; "${QA_PHP:-php}" -l "$root/$file" >/dev/null; count=$((count+1)); done
[[ $count -gt 0 ]]
printf 'PASS PHP syntax checked %d changed/critical files.
' "$count"
