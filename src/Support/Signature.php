<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Support;

use Beziworld\Comfino\Exceptions\InvalidNotification;

/**
 * ComfinoPay signatures.
 *
 * Status notifications carry CR-Signature = sha3-256(apiKey . rawBody) in
 * lowercase hexadecimal; this is a plain hash, not an HMAC, and it must be
 * computed over the exact bytes received. Loan applications are signed with
 * Comfino-Cart-Hash, Comfino-Customer-Hash and Comfino-Order-Signature in
 * the same way as the official client does.
 */
final class Signature
{
    public const string HEADER = 'CR-Signature';

    public const string LEGACY_HEADER = 'X-CR-Signature';

    public static function compute(string $rawBody, #[\SensitiveParameter] string $apiKey): string
    {
        return hash('sha3-256', $apiKey . $rawBody);
    }

    /**
     * @throws InvalidNotification When the signature is missing or does not match.
     */
    public static function verify(string $rawBody, ?string $signature, #[\SensitiveParameter] string $apiKey): void
    {
        $signature = $signature !== null ? strtolower(trim($signature)) : '';

        if ($signature === '') {
            throw InvalidNotification::missingSignature();
        }

        if ($apiKey === '' || ! hash_equals(self::compute($rawBody, $apiKey), $signature)) {
            throw InvalidNotification::invalidSignature();
        }
    }

    /**
     * Hash of a cart or customer section, JSON-encoded exactly as it is sent.
     *
     * @param array<array-key, mixed> $section
     */
    public static function sectionHash(array $section): string
    {
        return hash('sha3-256', Json::encode($section));
    }

    /**
     * Headers that sign a loan application body.
     *
     * @param array<array-key, mixed> $cart
     * @param array<array-key, mixed> $customer
     * @return array<string, string>
     */
    public static function orderHeaders(array $cart, array $customer, #[\SensitiveParameter] string $apiKey): array
    {
        $cartHash = self::sectionHash($cart);
        $customerHash = self::sectionHash($customer);

        return [
            'Comfino-Cart-Hash' => $cartHash,
            'Comfino-Customer-Hash' => $customerHash,
            'Comfino-Order-Signature' => hash('sha3-256', $cartHash . $customerHash . $apiKey),
        ];
    }

    /**
     * Short-lived authentication token of the checkout paywall iframe.
     *
     * The token is base64(timestamp . HMAC-SHA3-256(timestamp . widgetKey, apiKey) . widgetKey),
     * where timestamp is an unsigned 64-bit big-endian integer; ComfinoPay accepts it for 15 minutes.
     */
    public static function paywallAuthKey(string $widgetKey, #[\SensitiveParameter] string $apiKey, int $timestamp): string
    {
        $time = pack('J', $timestamp);

        return base64_encode($time . hash_hmac('sha3-256', $time . $widgetKey, $apiKey, true) . $widgetKey);
    }
}
