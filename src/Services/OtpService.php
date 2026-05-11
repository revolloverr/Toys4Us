<?php

declare(strict_types=1);

namespace App\Services;

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;

/**
 * OtpService
 *
 * Generates and verifies Time-based One-Time Passwords (TOTP) using the
 * robthree/twofactorauth library (RFC 6238).
 *
 * The TwoFactorAuth object is created once in the constructor with:
 *   - BaconQrCodeProvider — renders QR codes as self-contained data URIs (no external service)
 *   - 'TodoApp'           — the issuer name shown in the authenticator app
 *
 * Session structure written by generate():
 *
 *   $_SESSION['totp_secret'] = 'BASE32ENCODEDSECRET';
 *
 * Reflection question Q5 is about generate(). Answer it after completing Step 1.
 * Reflection question Q6 is about verify(). Answer it after completing Step 2.
 */
class OtpService
{
    private TwoFactorAuth $tfa;

    public function __construct()
    {
        $this->tfa = new TwoFactorAuth(new BaconQrCodeProvider(4, '#ffffff', '#000000', 'svg'), 'TodoApp');
    }

    // ── generate() ────────────────────────────────────────────────────────────
    /**
     * Generate a new TOTP secret for the given label (username), store it in
     * the session, and return a QR code as a base64 data URI.
     *
     * The data URI can be used directly in an <img> tag:
     *   <img src="{{ qr_code }}">
     *
     * TODO:
     *   1. Call $this->tfa->createSecret() and store the result in $_SESSION['totp_secret'].
     *   2. Call $this->tfa->getQRCodeImageAsDataUri($label, $secret) and return it.
     */
    public function generate(string $label): string
    {
        // your code here
        $secret = $this->tfa->createSecret();                              // generate a new Base32 secret
        $_SESSION['totp_secret'] = $secret;                           // store it in the session

        return $this->tfa->getQRCodeImageAsDataUri($label, $secret); 
    }

    // ── verify() ──────────────────────────────────────────────────────────────
    /**
     * Verify a user-supplied TOTP code against the secret stored in the session.
     *
     * Returns true if the code matches the current 30-second window.
     * Returns false if no secret is in the session or the code is wrong.
     *
     * TODO:
     *   1. Read $_SESSION['totp_secret']. If null, return false.
     *   2. Call $this->tfa->verifyCode($secret, $input) and return the result.
     */
    public function verify(string $input): bool
    {
        // 1. Read the secret from session. If null, return false.
        $secret = $_SESSION['totp_secret'] ?? null;
        if (is_null($secret)) {
            return false;
        }

        // 2. Verify the code against the secret
        return $this->tfa->verifyCode($secret, $input);
    }

    // ── invalidate() ──────────────────────────────────────────────────────────
    /**
     * Remove the TOTP secret from the session.
     * Called immediately after a successful login.
     *
     * PRE-FILLED.
     */
    public function invalidate(): void
    {
        unset($_SESSION['totp_secret']);
    }
}
