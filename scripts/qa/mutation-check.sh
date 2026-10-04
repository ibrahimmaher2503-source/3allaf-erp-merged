#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/environment.sh"
coverage=${QA_PCOV_EXTENSION:-$QA_ROOT/.qa-runtime/pcov/pcov.so}
options=()
if [[ -f "$coverage" ]]; then options=(-d "extension=$coverage" -d pcov.enabled=1 -d "pcov.directory=$QA_ROOT/app"); fi
"$QA_PHP" "${options[@]}" -r 'if (!extension_loaded("pcov") && !extension_loaded("xdebug")) {fwrite(STDERR,"FAIL mutation coverage driver unavailable\n");exit(1);}'
filter='app/Modules/Retail/Services/PosCalculationService.php,app/Modules/Retail/Support/DecimalMoney.php,app/Modules/Purchasing/Services/PurchaseInvoiceCalculator.php,app/Modules/Catalog/Support/ProductPricingContract.php,app/Support/ProductQuantity.php'
"$QA_PHP" "${options[@]}" vendor/bin/infection --configuration=infection.qa.json5 --no-progress --only-covered --filter="$filter" --min-msi=0 --min-covered-msi=50 --threads=2 --initial-tests-php-options="${options[*]}"

python3 - <<'SCORE'
import json,sys
x=json.load(open('.ai/qa/infection.json'));score=x['stats']['coveredCodeMsi']
if score<70:
 print(f'WARN initial critical arithmetic mutation MSI {score}%; minimum 50%, improvement target 70%; survivors require follow-up')
 sys.exit(77)
SCORE
