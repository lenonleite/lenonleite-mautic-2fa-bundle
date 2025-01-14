<?php

return [
    'name'        => 'Lenon Leite',
    'description' => 'This plugin adds 2FA to Mautic',
    'version'     => '1.0.0',
    'author'      => 'Lenon Leite',
    'services'    => [
        'integrations' => [
            'mautic.integration.lenonleitemautic2fa' => [
                'class' => MauticPlugin\LenonLeiteMautic2FABundle\Integration\LenonLeiteMautic2FAIntegration::class,
                'tags'  => [
                    'mautic.integration',
                    'mautic.basic_integration',
                ],
            ],
            'mautic.integration.lenonleitemautic2fa.configuration' => [
                'class' => MauticPlugin\LenonLeiteMautic2FABundle\Integration\Support\ConfigSupport::class,
                'tags'  => [
                    'mautic.config_integration',
                ],
            ],
            'mautic.integration.lenonleitemautic2fa.config' => [
                'class' => MauticPlugin\LenonLeiteMautic2FABundle\Integration\Config::class,
                'tags'  => [
                    'mautic.integrations.helper',
                ],
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
        ],
    ],
    'routes' => [
        'main' => [
            'lenonleitemautic_2fa_auth' => [
                'path'       => '/login/2fa',
                'controller' => 'MauticPlugin\LenonLeiteMautic2FABundle\Controller\TwoFAController::indexAction',
            ],
            'lenonleitemautic_2fa_batch_reset' => [
                'path'       => '/2fa/batch-reset',
                'controller' => 'MauticPlugin\LenonLeiteMautic2FABundle\Controller\TwoFAController::batchRecover2faAction',
            ],
        ],
    ],
];
