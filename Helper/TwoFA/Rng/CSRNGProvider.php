<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Helper\TwoFA\Rng;

use MauticPlugin\LenonLeiteMautic2FABundle\Helper\TwoFA\IRNGProvider;

class CSRNGProvider implements IRNGProvider
{
    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function getRandomBytes(int $bytecount): bool|string
    {
        return \random_bytes($bytecount);    // PHP7+
    }

    /**
     * {@inheritdoc}
     */
    public function isCryptographicallySecure(): bool
    {
        return true;
    }
}
