<?php

declare(strict_types=1);

namespace ContentTrue\BankFileTool;

use InvalidArgumentException;
use RuntimeException;

final class StatementReconciler
{
    /**
     * Inspect a normalized CSV with date, description and signed amount columns.
     * A duplicate is an exact repeated row, not evidence of a duplicate charge.
     *
     * @return array{transactions:int,calculatedClosing:string,expectedClosing:?string,matchesExpected:?bool,duplicates:list<array{row:int,firstRow:int}>}
     */
    public static function reconcile(string $csv, string $opening, ?string $expectedClosing = null): array
    {
        $balance = self::toCents($opening);
        $expected = $expectedClosing === null ? null : self::toCents($expectedClosing);
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('Unable to open a temporary CSV stream.');
        }
        try {
            fwrite($stream, $csv);
            rewind($stream);
            $header = fgetcsv($stream, 0, ',', '"', '');
            if ($header === false) {
                throw new InvalidArgumentException('CSV must contain a header.');
            }
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            $columns = array_flip($header);
            foreach (['date', 'description', 'amount'] as $required) {
                if (!isset($columns[$required])) {
                    throw new InvalidArgumentException('CSV requires date, description and amount columns.');
                }
            }
            $seen = [];
            $duplicates = [];
            $transactions = 0;
            $rowNumber = 1;
            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $rowNumber++;
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== count($header)) {
                    throw new InvalidArgumentException("CSV row {$rowNumber} has an unexpected column count.");
                }
                $date = trim($row[$columns['date']]);
                $description = trim($row[$columns['description']]);
                if ($date === '' || $description === '') {
                    throw new InvalidArgumentException("CSV row {$rowNumber} needs a date and description.");
                }
                $cents = self::toCents($row[$columns['amount']]);
                if (($cents > 0 && $balance > PHP_INT_MAX - $cents) ||
                    ($cents < 0 && $balance < PHP_INT_MIN - $cents)) {
                    throw new InvalidArgumentException('Balance exceeds supported integer range.');
                }
                $balance += $cents;
                $key = json_encode([$date, $description, $cents], JSON_THROW_ON_ERROR);
                if (isset($seen[$key])) {
                    $duplicates[] = ['row' => $rowNumber, 'firstRow' => $seen[$key]];
                } else {
                    $seen[$key] = $rowNumber;
                }
                $transactions++;
            }
        } finally {
            fclose($stream);
        }
        return [
            'transactions' => $transactions,
            'calculatedClosing' => self::formatCents($balance),
            'expectedClosing' => $expected === null ? null : self::formatCents($expected),
            'matchesExpected' => $expected === null ? null : $balance === $expected,
            'duplicates' => $duplicates,
        ];
    }

    private static function toCents(string $amount): int
    {
        $amount = trim($amount);
        if (!preg_match('/^([+-]?)(\d+)(?:\.(\d{1,2}))?$/D', $amount, $match)) {
            throw new InvalidArgumentException("Invalid decimal amount: {$amount}");
        }
        $fraction = str_pad($match[3] ?? '', 2, '0');
        $whole = ltrim($match[2], '0');
        $digits = ($whole === '' ? '0' : $whole) . $fraction;
        $limit = $match[1] === '-' ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new InvalidArgumentException('Amount exceeds supported integer range.');
        }
        if ($match[1] === '-' && $digits === $limit) {
            return PHP_INT_MIN;
        }
        $cents = (int) $digits;
        return $match[1] === '-' ? -$cents : $cents;
    }

    private static function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $digits = $negative ? substr((string) $cents, 1) : (string) $cents;
        $digits = str_pad($digits, 3, '0', STR_PAD_LEFT);
        return ($negative ? '-' : '') . substr($digits, 0, -2) . '.' . substr($digits, -2);
    }
}
