<?php
/**
 * Example: validate a few (well-known test) card numbers.
 *
 * Run: php examples/card-example.php
 *
 * Note: these are the standard gateway test numbers (Visa 4242…, Amex
 * 3782…, Mastercard 5555…). They are not real cards.
 */

declare(strict_types=1);

require __DIR__ . '/../src/CardValidator.php';

use Azanumer\CardValidator\CardValidator;

$v = new CardValidator();

$cards = [
    ['4242424242424242', 12, 2030, '123'],  // Visa test number — valid.
    ['378282246310005',  6, 2030, '1234'],  // Amex test number — valid.
    ['5555555555554444',  1, 2030, '999'],  // Mastercard test number — valid.
    ['4242424242424241', 12, 2030, '123'],  // Visa, one digit off — Luhn fails.
    ['378282246310005',  1, 2020, '1234'],  // Amex, expired.
    ['378282246310005',  6, 2030, '123'],   // Amex needs a 4-digit CVC.
    ['1234567890123456', 12, 2030, '123'],  // Unknown brand.
];

foreach ($cards as [$number, $month, $year, $cvc]) {
    $brand  = $v->detectBrand($number) ?? '(unknown)';
    $errors = $v->validate($number, $month, $year, $cvc);
    printf(
        "%s  %-11s %02d/%d CVC %s  →  %s\n",
        $v->format($number),
        $brand,
        $month,
        $year,
        $cvc,
        empty($errors) ? 'VALID' : 'INVALID: ' . implode(', ', $errors)
    );
}
