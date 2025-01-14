<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Helper\TwoFA\QR;

use MauticPlugin\LenonLeiteMautic2FABundle\Exception\TwoFAException;

class QRServerProvider extends BaseHTTPQRCodeProvider
{
    /**
     * @param bool   $verifyssl
     * @param string $errorcorrectionlevel
     * @param int    $margin
     * @param int    $qzone
     * @param string $bgcolor
     * @param string $color
     * @param string $format
     */
    public function __construct(
        $verifyssl = false,
        public $errorcorrectionlevel = 'L',
        public $margin = 4,
        public $qzone = 1,
        public $bgcolor = 'ffffff',
        public $color = '000000',
        public $format = 'png'
    ) {
        if (!is_bool($verifyssl)) {
            throw new TwoFAException('VerifySSL must be bool');
        }

        $this->verifyssl = $verifyssl;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimeType(): string
    {
        return match (strtolower($this->format)) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg'   => 'image/svg+xml',
            'eps'   => 'application/postscript',
            default => throw new TwoFAException(sprintf('Unknown MIME-type: %s', $this->format)),
        };
    }

    /**
     * {@inheritdoc}
     */
    public function getQRCodeImage($qrtext, $size)
    {
        return $this->getContent($this->getUrl($qrtext, $size));
    }

    /**
     * @param string     $qrtext the value to encode in the QR code
     * @param int|string $size   the desired size of the QR code
     *
     * @return string file contents of the QR code
     */
    public function getUrl($qrtext, $size): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/'
            .'?size='.$size.'x'.$size
            .'&ecc='.strtoupper($this->errorcorrectionlevel)
            .'&margin='.$this->margin
            .'&qzone='.$this->qzone
            .'&bgcolor='.$this->decodeColor($this->bgcolor)
            .'&color='.$this->decodeColor($this->color)
            .'&format='.strtolower($this->format)
            .'&data='.rawurlencode($qrtext);
    }

    /**
     * @param string $value
     */
    private function decodeColor($value): string
    {
        return vsprintf('%d-%d-%d', sscanf($value, '%02x%02x%02x'));
    }
}
