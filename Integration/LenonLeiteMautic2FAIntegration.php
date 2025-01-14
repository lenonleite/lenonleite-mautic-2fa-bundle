<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteMautic2FABundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

class LenonLeiteMautic2FAIntegration extends BasicIntegration implements BasicInterface
{
    use ConfigurationTrait;

    public const INTEGRATION_NAME = 'lenonleitemautic2fa';
    public const DISPLAY_NAME     = 'Add 2FA to Mautic';

    public function getName(): string
    {
        return self::INTEGRATION_NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/LenonLeiteMautic2FABundle/Assets/img/icon.png';
    }
}
