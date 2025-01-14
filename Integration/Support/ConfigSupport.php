<?php

declare(strict_types=1);

namespace MauticPlugin\LenonLeiteMautic2FABundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\LenonLeiteMautic2FABundle\Integration\LenonLeiteMautic2FAIntegration;

class ConfigSupport extends LenonLeiteMautic2FAIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}
