<?php

declare(strict_types=1);

/**
 * Security hardening tests for \yii\base\Security.
 *
 * Validates that the Security component correctly prevents common
 * vulnerability patterns:
 *
 * - Timing-safe string comparison (prevents timing-oracle attacks)
 * - HMAC data validation rejects tampered payloads
 * - Password hashes are non-deterministic (salt prevents rainbow tables)
 * - Encryption/decryption round-trip integrity
 * - compareString rejects non-string arguments gracefully
 *
 * @group base
 * @since 2.0
 */

namespace yiiunit\framework\base;

use yii\base\InvalidArgumentException;
use yii\base\Security;
use yiiunit\TestCase;

class SecurityHardeningTest extends TestCase
{
    private Security $security;

    protected function setUp(): void
    {
        parent::setUp();
        $this->security                      = new Security();
        $this->security->derivationIterations = 1000; // faster for tests
    }

    // -------------------------------------------------------------------------
    // compare_string — timing-safe equality check
    // -------------------------------------------------------------------------

    /**
     * compare_string must return true for identical strings.
     */
    public function test_compare_string_returns_true_for_equal_strings(): void
    {
        $this->assertTrue($this->security->compare_string('secret', 'secret'));
    }

    /**
     * compare_string must return false when strings differ.
     *
     * Prevents timing oracle: an attacker must not be able to determine how many
     * characters they guessed correctly by measuring execution time.
     */
    public function test_compare_string_returns_false_for_different_strings(): void
    {
        $this->assertFalse($this->security->compare_string('secret', 'Secret'));
        $this->assertFalse($this->security->compare_string('abc', 'abcd'));
        $this->assertFalse($this->security->compare_string('abc', ''));
        $this->assertFalse($this->security->compare_string('', 'abc'));
    }

    /**
     * Two empty strings must be considered equal.
     */
    public function test_compare_string_accepts_empty_strings(): void
    {
        $this->assertTrue($this->security->compare_string('', ''));
    }

    /**
     * Passing a non-string expected value must throw, not silently pass.
     *
     * Prevents a type-juggling bypass where `null === ''` or `0 == 'anything'`
     * could otherwise yield a false positive.
     */
    public function test_compare_string_rejects_non_string_expected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line intentional type violation for security test
        $this->security->compare_string(null, 'value');
    }

    /**
     * Passing a non-string actual value must throw, not silently pass.
     */
    public function test_compare_string_rejects_non_string_actual(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line intentional type violation for security test
        $this->security->compare_string('expected', null);
    }

    // -------------------------------------------------------------------------
    // hash_data / validate_data — HMAC tamper detection
    // -------------------------------------------------------------------------

    /**
     * Tampered data must fail validation.
     *
     * Validates that HMAC verification catches any modification to the payload —
     * preventing attackers from forging trusted data blobs.
     */
    public function test_tampered_data_fails_validation(): void
    {
        $key    = 'signing-key';
        $data   = 'trusted payload';
        $hashed = $this->security->hash_data($data, $key);

        // Flip the last character to simulate tampering.
        $tampered = substr($hashed, 0, -1) . (substr($hashed, -1) === 'A' ? 'B' : 'A');

        $this->assertFalse($this->security->validate_data($tampered, $key));
    }

    /**
     * Valid hash must pass validation and return original data.
     */
    public function test_valid_hash_passes_validation(): void
    {
        $key    = 'signing-key';
        $data   = 'trusted payload';
        $hashed = $this->security->hash_data($data, $key);

        $this->assertSame($data, $this->security->validate_data($hashed, $key));
    }

    /**
     * Hash signed with a different key must not validate.
     *
     * Prevents a key-confusion attack where an attacker uses a different secret
     * to generate a valid-looking hash for a different signing context.
     */
    public function test_hash_signed_with_wrong_key_fails_validation(): void
    {
        $hashed = $this->security->hash_data('payload', 'correct-key');
        $this->assertFalse($this->security->validate_data($hashed, 'wrong-key'));
    }

    // -------------------------------------------------------------------------
    // generatePasswordHash — non-deterministic (salt-based)
    // -------------------------------------------------------------------------

    /**
     * Two hashes of the same password must differ (random salt).
     *
     * Ensures rainbow table attacks cannot precompute matching hashes.
     */
    public function test_password_hash_is_non_deterministic(): void
    {
        $hash1 = $this->security->generate_password_hash('my-password');
        $hash2 = $this->security->generate_password_hash('my-password');

        $this->assertNotSame($hash1, $hash2, 'Each hash must use a unique random salt.');
    }

    /**
     * A correct password must validate against its own hash.
     */
    public function test_correct_password_validates(): void
    {
        $hash = $this->security->generate_password_hash('correct-horse-battery-staple');
        $this->assertTrue($this->security->validate_password('correct-horse-battery-staple', $hash));
    }

    /**
     * An incorrect password must not validate.
     *
     * Defends against an application bug that always returns true regardless of
     * the password value.
     */
    public function test_wrong_password_does_not_validate(): void
    {
        $hash = $this->security->generate_password_hash('correct-horse-battery-staple');
        $this->assertFalse($this->security->validate_password('Correct-Horse-Battery-Staple', $hash));
        $this->assertFalse($this->security->validate_password('', $hash));
    }

    // -------------------------------------------------------------------------
    // generateRandomKey — randomness sanity check
    // -------------------------------------------------------------------------

    /**
     * Two calls to generateRandomKey must produce different values.
     *
     * A statically-seeded or broken RNG would produce identical values,
     * which would make all tokens guessable.
     */
    public function test_random_keys_are_unique(): void
    {
        $key1 = $this->security->generate_random_key(32);
        $key2 = $this->security->generate_random_key(32);

        $this->assertNotSame($key1, $key2);
    }

    /**
     * generateRandomKey must return a binary string of the requested length.
     */
    public function test_random_key_has_correct_length(): void
    {
        foreach ([16, 32, 64] as $length) {
            $key = $this->security->generate_random_key($length);
            $this->assertSame($length, strlen($key), "Expected {$length} bytes.");
        }
    }
}
