#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/environment.sh"
coverage=${QA_PCOV_EXTENSION:-$QA_ROOT/.qa-runtime/pcov/pcov.so}
export QA_PCOV_EXTENSION="$coverage"
options=()
if [[ -f "$coverage" ]]; then options=(-d "extension=$coverage" -d pcov.enabled=1 -d "pcov.directory=$QA_ROOT/app"); fi
"$QA_PHP" "${options[@]}" -r 'if (!extension_loaded("pcov") && !extension_loaded("xdebug")) {fwrite(STDERR,"FAIL mutation coverage driver unavailable\n");exit(1);}'
filter='app/Modules/Retail/Services/PosCalculationService.php,app/Modules/Retail/Support/DecimalMoney.php,app/Modules/Purchasing/Services/PurchaseInvoiceCalculator.php,app/Modules/Catalog/Support/ProductPricingContract.php,app/Support/ProductQuantity.php'
"$QA_PHP" "${options[@]}" vendor/bin/infection --configuration=infection.qa.json5 --no-progress --only-covered --filter="$filter" --min-msi=0 --min-covered-msi=0 --threads=2 --initial-tests-php-options="${options[*]}"

python3 "$QA_ROOT/scripts/qa/mutation-score.py" "$QA_ROOT/.ai/qa/infection.json" "${QA_RUN_DIR:-$QA_ROOT/.ai/qa/runs}/mutation-score.json"
