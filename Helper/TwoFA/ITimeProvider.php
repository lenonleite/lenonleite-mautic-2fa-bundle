<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Helper\TwoFA;

interface ITimeProvider
{
    /**
     * @return int the current timestamp according to this provider
     */
    public function getTime();
}
