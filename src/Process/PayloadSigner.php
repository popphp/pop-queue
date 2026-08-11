<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Process;

/**
 * Payload signer class - HMAC-signs/verifies the serialized bytes every
 * storage adapter writes, closing the unserialize() object-injection gap
 * for anyone who can write to the underlying storage directly (a
 * compromised Redis instance, SQL injection elsewhere in the host app, a
 * writable queue directory). Static-only, mirroring
 * Laravel\SerializableClosure::setSecretKey()'s own static-global shape -
 * a familiar idiom already present in this project's dependency tree.
 *
 * When no key is configured (the default), sign()/verify() are exact
 * pass-throughs - zero behavior change from every adapter's current,
 * unsigned serialize()/unserialize() round-trip. Once setKey() is called
 * (recommended once, at application bootstrap, before any queue/worker
 * operation), sign() prepends a raw 32-byte HMAC-SHA256 and verify()
 * checks it with hash_equals() before returning the payload bytes - a
 * failed check means the caller must never pass those bytes to
 * unserialize() at all.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
final class PayloadSigner
{

    /**
     * Signing key. Null (the default) disables signing entirely - sign()
     * and verify() both become pass-throughs.
     * @var ?string
     */
    protected static ?string $key = null;

    /**
     * Set the signing key. Pass null to disable signing again.
     *
     * @param  ?string $key
     * @return void
     */
    public static function setKey(?string $key): void
    {
        self::$key = $key;
    }

    /**
     * Whether a signing key is currently configured
     *
     * @return bool
     */
    public static function hasKey(): bool
    {
        return (self::$key !== null);
    }

    /**
     * Sign a payload. Pass-through when no key is configured.
     *
     * @param  string $payload
     * @return string
     */
    public static function sign(string $payload): string
    {
        if (self::$key === null) {
            return $payload;
        }

        return hash_hmac('sha256', $payload, self::$key, true) . $payload;
    }

    /**
     * Verify a signed payload, returning the original payload bytes on
     * success. Pass-through when no key is configured. Returns false on
     * any verification failure (missing/truncated signature, tampered
     * signature, tampered payload, or a payload signed with a different
     * key) - callers must never unserialize() the input when this returns
     * false.
     *
     * @param  string $signed
     * @return string|false
     */
    public static function verify(string $signed): string|false
    {
        if (self::$key === null) {
            return $signed;
        }

        if (strlen($signed) < 32) {
            return false;
        }

        $mac  = substr($signed, 0, 32);
        $body = substr($signed, 32);

        return hash_equals(hash_hmac('sha256', $body, self::$key, true), $mac) ? $body : false;
    }

}
