<?php

declare(strict_types=1);

require __DIR__ . '/../src/StatementReconciler.php';

use ContentTrue\BankFileTool\StatementReconciler;

$csv = "date,description,amount\n2026-11-01,Deposit,120.00\n2026-11-02,Payment,-45.50\n2026-11-02,Payment,-45.50\n";
$report = StatementReconciler::reconcile($csv, '1000.00', '1029.00');
assert($report['calculatedClosing'] === '1029.00');
assert($report['matchesExpected'] === true);
assert($report['transactions'] === 3);
assert($report['duplicates'] === [['row' => 4, 'firstRow' => 3]]);
try {
    StatementReconciler::reconcile("date,description,amount\nnow,X,1.234\n", '0.00');
    throw new RuntimeException('Invalid amount was accepted.');
} catch (InvalidArgumentException) {
}
echo "StatementReconciler smoke test passed\n";
