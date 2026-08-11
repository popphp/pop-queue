<?php

namespace Pop\Queue\Test\Process;

use Pop\Queue\Process\PayloadSigner;
use PHPUnit\Framework\TestCase;

class PayloadSignerTest extends TestCase
{

    protected function tearDown(): void
    {
        PayloadSigner::setKey(null);
    }

    public function testNoKeyIsPassthrough()
    {
        $this->assertFalse(PayloadSigner::hasKey());
        $this->assertEquals('hello', PayloadSigner::sign('hello'));
        $this->assertEquals('hello', PayloadSigner::verify('hello'));
    }

    public function testSignAndVerifyRoundTrip()
    {
        PayloadSigner::setKey('test-secret-key');
        $this->assertTrue(PayloadSigner::hasKey());

        $signed = PayloadSigner::sign('hello world');
        $this->assertNotEquals('hello world', $signed);
        $this->assertEquals('hello world', PayloadSigner::verify($signed));
    }

    public function testSignAndVerifyRoundTripWithEmptyPayload()
    {
        PayloadSigner::setKey('test-secret-key');

        $signed = PayloadSigner::sign('');
        $this->assertEquals(32, strlen($signed));
        $this->assertEquals('', PayloadSigner::verify($signed));
    }

    public function testVerifyFailsOnTamperedPayload()
    {
        PayloadSigner::setKey('test-secret-key');

        $signed           = PayloadSigner::sign('hello world');
        $tamperedPayload  = substr($signed, 0, 32) . 'tampered payload!!';

        $this->assertFalse(PayloadSigner::verify($tamperedPayload));
    }

    public function testVerifyFailsOnTamperedSignature()
    {
        PayloadSigner::setKey('test-secret-key');

        $signed = PayloadSigner::sign('hello world');

        // XOR the first byte against 0xFF so it's guaranteed to differ from
        // the original, whatever the original byte was - avoids the tiny
        // (1/256) chance a literal replacement character coincidentally
        // matches the real byte and produces a false-negative test.
        $flippedFirstByte = chr(ord($signed[0]) ^ 0xFF);
        $tamperedSignature = $flippedFirstByte . substr($signed, 1);

        $this->assertFalse(PayloadSigner::verify($tamperedSignature));
    }

    public function testVerifyFailsOnTruncatedSignature()
    {
        PayloadSigner::setKey('test-secret-key');

        $signed    = PayloadSigner::sign('hello world');
        $truncated = substr($signed, 0, 10);

        $this->assertFalse(PayloadSigner::verify($truncated));
    }

    public function testVerifyFailsWithDifferentKeyThanSigned()
    {
        PayloadSigner::setKey('key-one');
        $signed = PayloadSigner::sign('hello world');

        PayloadSigner::setKey('key-two');
        $this->assertFalse(PayloadSigner::verify($signed));
    }

    public function testSetKeyNullDisablesSigning()
    {
        PayloadSigner::setKey('test-secret-key');
        $signed = PayloadSigner::sign('hello world');

        PayloadSigner::setKey(null);
        $this->assertFalse(PayloadSigner::hasKey());
        // Once the key is cleared, sign()/verify() are pass-throughs again -
        // a previously-signed payload is no longer even checked, just
        // handed back byte for byte.
        $this->assertEquals($signed, PayloadSigner::sign($signed));
        $this->assertEquals($signed, PayloadSigner::verify($signed));
    }

}
