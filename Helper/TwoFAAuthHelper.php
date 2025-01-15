<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Helper;

use Mautic\UserBundle\Entity\User;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\LenonLeiteMautic2FABundle\Exception\TwoFAException;
use MauticPlugin\LenonLeiteMautic2FABundle\Helper\TwoFA\IQRCodeProvider;
use MauticPlugin\LenonLeiteMautic2FABundle\Helper\TwoFA\IRNGProvider;
use MauticPlugin\LenonLeiteMautic2FABundle\Helper\TwoFA\QR\QRServerProvider;
use MauticPlugin\LenonLeiteMautic2FABundle\Helper\TwoFA\Rng\CSRNGProvider;

/**
 * Code get from.
 */
class TwoFAAuthHelper
{
    public const TWOFA_TITLE            = 'LenonLeiteMautic2FA';
    public const TWOFA_SECOND_TITLE     = 'Mautic';
    public const DEFAULT_CHARACTERS     = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private static array $_base32;
    private static array $_base32lookup = [];
    private string $issuer;
    private int $timeprovider;

    public function __construct(
        private ?IQRCodeProvider $qrcodeprovider,
        private ?IRNGProvider $rngprovider,
        private UserModel $userModel,
        private int $digits = 6,
        private int $period = 30,
        private string $algorithm = 'sha1',
    ) {
        $this->issuer = self::TWOFA_TITLE;
        if (!is_int($this->digits) || $this->digits <= 0) {
            throw new TwoFAException('Digits must be int > 0');
        }

        if (!is_int($this->period) || $this->period <= 0) {
            throw new TwoFAException('Period must be int > 0');
        }

        $this->timeprovider   = time();

        self::$_base32       = str_split(self::DEFAULT_CHARACTERS);
        self::$_base32lookup = array_flip(self::$_base32);
    }

    /**
     * Check if the code is correct. This will accept codes starting from ($discrepancy * $period) sec ago to ($discrepancy * period) sec from now.
     *
     * @param string $secret
     * @param string $code
     * @param int    $discrepancy
     * @param ?int   $time
     * @param int    $timeslice
     */
    public function verifyCode($secret, $code, $discrepancy = 1, $time = null, &$timeslice = 0): bool
    {
        $timestamp = $this->getTime($time);

        $timeslice = 0;

        // To keep safe from timing-attacks we iterate *all* possible codes even though we already may have
        // verified a code is correct. We use the timeslice variable to hold either 0 (no match) or the timeslice
        // of the match. Each iteration we either set the timeslice variable to the timeslice of the match
        // or set the value to itself.  This is an effort to maintain constant execution time for the code.
        for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
            $ts        = $timestamp + ($i * $this->period);
            $slice     = $this->getTimeSlice($ts);
            $timeslice = $this->codeEquals($this->getCode($secret, $ts), $code) ? $slice : $timeslice;
        }

        return $timeslice > 0;
    }

    /**
     * @param ?int $time
     */
    private function getTime($time = null): int
    {
        return (null === $time) ? $this->getTimeProvider() : $time;
    }

    public function getTimeProvider(): int
    {
        // Set default time provider if none was specified
        return $this->timeprovider;
    }

    /**
     * @param int $time
     * @param int $offset
     */
    private function getTimeSlice($time = null, $offset = 0): int
    {
        return (int) floor($time / $this->period) + ($offset * $this->period);
    }

    /**
     * Timing-attack safe comparison of 2 codes (see http://blog.ircmaxell.com/2014/11/its-all-about-time.html).
     *
     * @param string $safe
     * @param string $user
     */
    private function codeEquals($safe, $user): bool
    {
        if (function_exists('hash_equals')) {
            return hash_equals($safe, $user);
        }
        // In general, it's not possible to prevent length leaks. So it's OK to leak the length. The important part is that
        // we don't leak information about the difference of the two strings.
        if (strlen($safe) === strlen($user)) {
            $result = 0;
            for ($i = 0, $iMax = strlen($safe); $i < $iMax; ++$i) {
                $result |= (ord($safe[$i]) ^ ord($user[$i]));
            }

            return 0 === $result;
        }

        return false;
    }

    /**
     * Calculate the code with given secret and point in time.
     *
     * @param string $secret
     * @param ?int   $time
     */
    public function getCode($secret, $time = null): string
    {
        $secretkey = $this->base32Decode($secret);

        $timestamp = "\0\0\0\0".pack(
            'N*',
            $this->getTimeSlice($this->getTime($time))
        );  // Pack time into binary string
        $hashhmac = hash_hmac(
            $this->algorithm,
            $timestamp,
            $secretkey,
            true
        );             // Hash it with users secret key
        $hashpart = substr(
            $hashhmac,
            ord(substr($hashhmac, -1)) & 0x0F,
            4
        );               // Use last nibble of result as index/offset and grab 4 bytes of the result
        $value = unpack('N', $hashpart);                                                   // Unpack binary value
        $value = $value[1] & 0x7FFFFFFF;                                                   // Drop MSB, keep only 31 bits

        return str_pad((string) ($value % pow(10, $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * @param string $value
     */
    private function base32Decode($value): string
    {
        if (0 == strlen($value)) {
            return '';
        }

        if (0 !== preg_match('/[^'.preg_quote(self::DEFAULT_CHARACTERS).']/', $value)) {
            throw new TwoFAException('Invalid base32 string');
        }

        $buffer = '';
        foreach (str_split($value) as $char) {
            if ('=' !== $char) {
                $buffer .= str_pad(decbin(self::$_base32lookup[$char]), 5, '0', STR_PAD_LEFT);
            }
        }
        $length = strlen($buffer);
        $blocks = trim(chunk_split(substr($buffer, 0, $length - ($length % 8)), 8, ' '));

        $output = '';
        foreach (explode(' ', $blocks) as $block) {
            $output .= chr((int) bindec(str_pad($block, 8, '0', STR_PAD_RIGHT)));
        }

        return $output;
    }

    public function registerTwoFactorAuth(User $user): User
    {
        $preferences                                      = $user->getPreferences();
        if (!is_array($preferences)) {
            $preferences = [];
        }
        $preferences['2fa']['twofa_secret']               = $this->createSecret();
        $preferences['2fa']['twofa_src_qrcode']           = $this->getQRCodeImageAsDataUri(
            //            $user->getUsername(),
            self::TWOFA_SECOND_TITLE,
            $preferences['2fa']['twofa_secret']
        );
        $user->setPreferences($preferences);
        $this->userModel->saveEntity($user);

        return $user;
    }

    /**
     * Create a new secret.
     */
    public function createSecret(int $bits = 80, bool $requirecryptosecure = true): string
    {
        $secret      = '';
        $bytes       = (int) ceil($bits / 5);   // We use 5 bits of each byte (since we have a 32-character 'alphabet' / BASE32)
        $rngprovider = $this->getRngProvider();
        if ($requirecryptosecure && !$rngprovider->isCryptographicallySecure()) {
            throw new TwoFAException('RNG provider is not cryptographically secure');
        }
        $rnd = $rngprovider->getRandomBytes($bytes);
        for ($i = 0; $i < $bytes; ++$i) {
            $secret .= self::$_base32[ord($rnd[$i]) & 31];  // Mask out left 3 bits for 0-31 values
        }

        return $secret;
    }

    /**
     * @return CSRNGProvider|IRNGProvider
     *
     * @throws TwoFAException
     */
    public function getRngProvider()
    {
        if (null !== $this->rngprovider) {
            return $this->rngprovider;
        }
        if (function_exists('random_bytes')) {
            return $this->rngprovider = new CSRNGProvider();
        }

        throw new TwoFAException('Unable to find a suited RNGProvider');
    }

    /**
     * Get data-uri of QRCode.
     *
     * @param string $label
     * @param string $secret
     * @param mixed  $size
     */
    public function getQRCodeImageAsDataUri($label, $secret, $size = 200): string
    {
        if (!is_int($size) || $size <= 0) {
            throw new TwoFAException('Size must be int > 0');
        }

        $qrcodeprovider = $this->getQrCodeProvider();

        return 'data:'
            .$qrcodeprovider->getMimeType()
            .';base64,'
            .base64_encode($qrcodeprovider->getQRCodeImage($this->getQRText($label, $secret), $size));
    }

    /**
     * @return QRServerProvider|IQRCodeProvider
     */
    public function getQrCodeProvider()
    {
        // Set default QR Code provider if none was specified
        if (null === $this->qrcodeprovider) {
            return $this->qrcodeprovider = new QRServerProvider();
        }

        return $this->qrcodeprovider;
    }

    /**
     * Builds a string to be encoded in a QR code.
     *
     * @param string $label
     * @param string $secret
     */
    public function getQRText($label, $secret): string
    {
        return 'otpauth://totp/'.rawurlencode($label)
            .'?secret='.rawurlencode($secret)
            .'&issuer='.rawurlencode((string) $this->issuer)
            .'&period='.intval($this->period)
            .'&algorithm='.rawurlencode(strtoupper($this->algorithm))
            .'&digits='.intval($this->digits);
    }
}
