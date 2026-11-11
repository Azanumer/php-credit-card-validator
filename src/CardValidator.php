<?php
/**
 * CardValidator — dependency-free credit-card validation.
 *
 * Detects the card brand, runs the Luhn checksum, and validates the number
 * length, expiry date and CVC. Nothing here touches a payment gateway: it
 * only answers "is this card number/expiry/CVC plausible?" before you send
 * anything to a processor.
 *
 * Usage:
 *
 *     $v = new CardValidator();
 *     $v->detectBrand('4242424242424242'); // 'visa'
 *     $v->validate('4242424242424242', 12, 2030, '123'); // [] = valid
 *     $v->validate('4242', 1, 2020, '1'); // ['number', 'expiry', 'cvc']
 */

declare(strict_types=1);

namespace Azanumer\CardValidator;

class CardValidator
{
    /** Brand definitions: regex (on digits only) + allowed lengths. */
    private const BRANDS = [
        // Visa: 4..., 13 / 16 / 19 digits.
        'visa'       => ['/^4\d*$/', [13, 16, 19]],
        // Mastercard: 51-55 or 2221-2720, 16 digits.
        'mastercard' => ['/^(?:5[1-5]\d*|2[2-7]\d*)$/', [16]],
        // American Express: 34 / 37, 15 digits.
        'amex'       => ['/^3[47]\d*$/', [15]],
        // Discover: 6011, 65, 644-649, 16 / 19 digits.
        'discover'   => ['/^(?:6011\d*|65\d*|64[4-9]\d*)$/', [16, 19]],
        // Diners Club: 300-305, 36, 38, 14 digits.
        'diners'     => ['/^(?:3(?:0[0-5]\d*|[68]\d*))$/', [14]],
        // JCB: 35, 16-19 digits.
        'jcb'        => ['/^35\d*$/', [16, 17, 18, 19]],
        // UnionPay: 62, 16-19 digits.
        'unionpay'   => ['/^62\d*$/', [16, 17, 18, 19]],
    ];

    /**
     * Strip spaces and dashes from a card number.
     */
    public function normalize(string $number): string
    {
        return preg_replace('/[\s-]+/', '', $number) ?? '';
    }

    /**
     * Detect the card brand from the number, or null when unknown.
     * Works on partial numbers too (useful while the user is typing).
     */
    public function detectBrand(string $number): ?string
    {
        $digits = $this->normalize($number);
        if ('' === $digits || ! ctype_digit($digits)) {
            return null;
        }

        foreach (self::BRANDS as $brand => [$pattern, $lengths]) {
            if (1 === preg_match($pattern, $digits)) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * Luhn checksum (mod 10). Returns false for empty / non-digit input.
     */
    public function luhnCheck(string $number): bool
    {
        $digits = $this->normalize($number);
        if ('' === $digits || ! ctype_digit($digits)) {
            return false;
        }

        $sum    = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];
            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum   += $digit;
            $double = ! $double;
        }

        return 0 === ($sum % 10);
    }

    /**
     * Is the card number structurally valid? (digits only, known brand,
     * allowed length for that brand, Luhn checksum passes.)
     */
    public function validateNumber(string $number): bool
    {
        $digits = $this->normalize($number);
        if ('' === $digits || ! ctype_digit($digits)) {
            return false;
        }

        $brand = $this->detectBrand($digits);
        if (null === $brand) {
            return false;
        }

        $lengths = self::BRANDS[$brand][1];
        if (! in_array(strlen($digits), $lengths, true)) {
            return false;
        }

        return $this->luhnCheck($digits);
    }

    /**
     * Is the expiry date valid? Month 1-12, year 2- or 4-digit, and the
     * card must still be valid at the end of that month.
     */
    public function validateExpiry(int $month, int $year): bool
    {
        if ($month < 1 || $month > 12) {
            return false;
        }

        // Normalise a 2-digit year into the 2000s (cards never expire in
        // the 1900s, and a 2-digit year is the common form input).
        if ($year < 100) {
            $year += 2000;
        }

        $now          = new \DateTimeImmutable('now');
        $currentYear  = (int) $now->format('Y');
        $currentMonth = (int) $now->format('n');

        if ($year < $currentYear) {
            return false;
        }
        if ($year === $currentYear && $month < $currentMonth) {
            return false;
        }

        return true;
    }

    /**
     * Is the CVC valid? 3 digits, or 4 for American Express.
     *
     * @param string      $cvc   The CVC, digits only (spaces are stripped).
     * @param string|null $brand Brand slug from detectBrand(), or null to
     *                           accept 3 or 4 digits.
     */
    public function validateCvc(string $cvc, ?string $brand = null): bool
    {
        $cvc = preg_replace('/\s+/', '', $cvc) ?? '';
        if (! ctype_digit($cvc)) {
            return false;
        }

        if ('amex' === $brand) {
            return 4 === strlen($cvc);
        }
        if (null === $brand) {
            return 3 === strlen($cvc) || 4 === strlen($cvc);
        }
        return 3 === strlen($cvc);
    }

    /**
     * Validate a whole card at once. Returns the list of fields that
     * failed: any of 'number', 'expiry', 'cvc'. An empty array means valid.
     *
     * @return string[]
     */
    public function validate(string $number, int $month, int $year, string $cvc): array
    {
        $errors = [];

        if (! $this->validateNumber($number)) {
            $errors[] = 'number';
        }
        if (! $this->validateExpiry($month, $year)) {
            $errors[] = 'expiry';
        }
        if (! $this->validateCvc($cvc, $this->detectBrand($number))) {
            $errors[] = 'cvc';
        }

        return $errors;
    }

    /**
     * Format a number for display: groups of 4, except Amex (4-6-5).
     */
    public function format(string $number): string
    {
        $digits = $this->normalize($number);
        if ('amex' === $this->detectBrand($digits)) {
            return trim(substr($digits, 0, 4) . ' ' . substr($digits, 4, 6) . ' ' . substr($digits, 10));
        }
        return trim(chunk_split($digits, 4, ' '));
    }
}
