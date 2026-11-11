# PHP Credit Card Validator

Dependency-free credit-card validation: brand detection, the Luhn checksum, number-length rules per brand, expiry-date and CVC checks. It only answers *"is this card number / expiry / CVC plausible?"* — nothing here touches a payment gateway; send the card to your processor (Stripe, etc.) for the real charge.

## Usage

```php
require 'src/CardValidator.php';

use Azanumer\CardValidator\CardValidator;

$v = new CardValidator();

$v->detectBrand('4242424242424242'); // 'visa'
$v->detectBrand('378282246310005');  // 'amex'
$v->luhnCheck('4242424242424242');   // true

// Whole card at once — [] means valid, otherwise the failing fields:
$v->validate('4242424242424242', 12, 2030, '123'); // []
$v->validate('4242', 1, 2020, '1');               // ['number', 'expiry', 'cvc']

// Individual checks:
$v->validateNumber('5555555555554444'); // true (Mastercard test number)
$v->validateExpiry(12, 2030);          // true
$v->validateExpiry(1, 2020);           // false — expired
$v->validateCvc('1234', 'amex');       // true (Amex uses 4 digits)
$v->validateCvc('123', 'amex');        // false

// Display formatting (Amex is grouped 4-6-5, everything else in 4s):
$v->format('378282246310005'); // '3782 822463 10005'
```

## Supported brands

Visa, Mastercard (51–55 and 2221–2720 ranges), American Express, Discover, Diners Club, JCB, UnionPay — each with its allowed lengths. Unknown brands fail `validateNumber()` rather than guessing.

## Notes

- `detectBrand()` works on partial numbers too, so you can show the brand logo while the user is still typing.
- `validateExpiry()` accepts 2- or 4-digit years and treats a card as valid until the end of its expiry month.
- The example uses the standard gateway **test** numbers (Visa `4242…`, Amex `3782…`, Mastercard `5555…`). They are not real cards.

## Example

```bash
php examples/card-example.php
```

## Requirements

PHP 7.4+ (no extensions beyond the standard library).

## License

MIT — see [LICENSE](LICENSE).
