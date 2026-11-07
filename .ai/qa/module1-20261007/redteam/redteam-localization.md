# Module 1 localization red-team review

Reviewed integration: `07a514c94d106efe3516d81780ee9081ecfd86bc` on `hotfix/module1-redteam-localization-20261007`.

## Scope and result

Reviewed approval-inbox decision errors, the integrated tax-rate required validation label, branch/drawer safe-error handling and locale keys, approval labels, and the Settings master-list pagination controls. No source PDF statuses or production state were changed.

Confirmed P2 localization defect in `resources/views/platform/system/approval-inbox.blade.php:87-121`: English approve/reject authorization failures and fallback failures used Arabic sentences as translation source keys. An English reviewer hitting the real `decide` authorization denial therefore saw Arabic. The pre-fix Livewire RED captured `لا تملك صلاحية اعتماد هذا الطلب.` for approve and `لا تملك صلاحية رفض هذا الطلب.` for reject in English. The denial still preserved authorization and left the record pending.

Replaced those error literals with existing English source keys that have Arabic and Egyptian-Arabic equivalents: `Permission denied`, `Unable to complete the request. Please review the data and try again.`, and `This source does not expose a rejection transition.` No authorization, decision, or record-state logic changed. The focused en/ar proof confirms the visible denial text is `Permission denied` in English and `تم رفض الصلاحية` in Arabic, the modal remains open, and the approval remains pending with no approver.

The integrated tax form’s native `validationAttributes()` mapping is compatible with the required Livewire rule. Exact rendered messages are `The Tax Rate (%) field is required.` in English and `حقل نسبة الضريبة (%) ده مطلوب ومهم.` in Arabic; neither exposes `taxSettingForm.rate`. A separate direct `SaveLocalSettingsAction::validatedTaxRate(['rate' => ''])` check under Arabic still returns `حقل tax setting form.rate ده مطلوب ومهم.` because that standalone validator does not receive field attributes. The normal form catches blank input first. Reported this compatibility limitation to the tax owner; no tax-action edit was made in this lane.

## Verification

- Pre-fix denial reproducer: 1 focused test, expected message mismatch in English; actual output was the Arabic authorization literals.
- Post-fix `Module1RedTeamLocalizationTest.php`: 2 tests / 25 assertions passed on disposable XAMPP schema `qa_rajh_m1_localization_20261007`.
- Pint passed on the approval inbox SFC and focused test; PHP syntax and `git diff --check` passed. Exact-worktree CodeGraph status showed pending files, then `codegraph sync` completed for this worktree.
- Existing first-pass evidence remains the source for drawer stale-state and branch dependency behavior; it was not rerun. The second-pass permission catalog lacked the required drawer/branch codes, and the red-team brief prohibits creating permission-catalog fixtures. Their en/ar/ar-EG message keys were read and verified in all three JSON catalogs.
- Static pagination review confirmed Settings lists use `WithPagination` links and reset state on tab/search changes; the print-template paginator has its own `templates_page` name and uses the Laravel/Livewire links view. The existing 101-row reachability test was not repeated.

## Limits and declined scope

No whole-screen locale/RTL visual matrix, browser session, full suite, production access, push, merge, or deployment was performed. Locale-key inspection and focused component rendering do not establish whole-screen translation completeness. The existing first-pass M1-F18 and M1-F19 source-PDF statuses remain `PARTIAL`. No N+1 or unbounded-query claim was made in this second-pass review.
