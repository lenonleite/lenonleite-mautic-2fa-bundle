<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectContactBulkButtons', 0],
        ];
    }

    public function injectContactBulkButtons(CustomButtonEvent $event): void
    {
        if (str_starts_with($event->getRoute(), 'mautic_user_index')) {
            $event->addButton(
                [
                    'attr'      => [
                        'data-toggle'           => 'confirmation',
                        'href'                  => $this->router->generate('lenonleitemautic_2fa_batch_reset'),
                        'data-precheck'         => 'batchActionPrecheck',
                        'data-message'          => $this->translator->trans(
                            'mautic.core.export.items',
                            ['%items%' => 'contacts']
                        ),
                        'data-confirm-text'     => $this->translator->trans('mautic.core.export.xlsx'),
                        'data-confirm-callback' => 'executeBatchAction',
                        'data-cancel-text'      => $this->translator->trans('mautic.core.form.cancel'),
                        'data-cancel-callback'  => 'dismissConfirmation',
                    ],
                    'btnText'   => $this->translator->trans('mautic.core.export.xlsx'),
                    'iconClass' => 'ri-file-excel-line',
                ],
                ButtonHelper::LOCATION_BULK_ACTIONS
            );
        }
    }
}
