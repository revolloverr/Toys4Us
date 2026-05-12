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
 */
class OtpService
{
    private TwoFactorAuth $tfa;

    public function __construct()
    {
        $this->tfa = new TwoFactorAuth(
            new BaconQrCodeProvider(4, '#7c3aed', '#ffffff', 'svg'),
            'Toys4Us'
        );
    }

    /**
     * Generate a new TOTP secret.
     */
    public function generateSecret(): string
    {
        return $this->tfa->createSecret();
    }

    /**
     * Get a QR code data URI for the given label and secret.
     */
    public function getQrCode(string $label, string $secret): string
    {
        return $this->tfa->getQRCodeImageAsDataUri($label, $secret);
    }

    /**
     * Verify a user-supplied TOTP code against a secret.
     */
    public function verify(string $input, string $secret): bool
    {
        if (empty($secret)) {
            return false;
        }
        return $this->tfa->verifyCode($secret, $input);
    }
}