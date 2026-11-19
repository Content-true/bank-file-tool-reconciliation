# Bank File Tool Reconciliation

A small local PHP library for checking a **normalized CSV** bank statement against an opening balance and an optional expected closing balance. It uses integer cents, avoiding floating-point rounding in totals, and lists exact repeated rows for human review. It does not decide whether a charge is legitimate.

```bash
composer require content-true/bank-file-tool-reconciliation
```

```php
use ContentTrue\BankFileTool\StatementReconciler;

$csv = "date,description,amount\n2026-11-01,Deposit,120.00\n2026-11-02,Payment,-45.50\n";
$report = StatementReconciler::reconcile($csv, '1000.00', '1074.50');
// calculatedClosing: "1074.50"; matchesExpected: true
```

Input must have `date`, `description`, `amount` headers. Amounts are signed dot-decimal strings with at most two fractional digits; expenses are negative. The output includes `transactions`, `calculatedClosing`, `expectedClosing`, `matchesExpected`, and `duplicates` (row numbers). Check a few exported rows against the original statement before relying on any total. When a PDF has first been converted with a [Bank Statement Converter](https://bankfiletool.com/), this local check helps catch missing rows or unexpected totals. It does not parse PDFs or call the website, and no bank data leaves the local process.

Requires PHP 8.1+. Run `php -d zend.assertions=1 -d assert.exception=1 tests/smoke.php`. MIT license.
