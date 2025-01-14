<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Helper\TwoFA;

interface IRNGProvider
{
    /**
     * @param int $bytecount the number of bytes of randomness to return
     *
     * @return string the random bytes
     */
    public function getRandomBytes(int $bytecount);

    /**
     * @return bool whether this provider is cryptographically secure
     */
    public function isCryptographicallySecure();
}
