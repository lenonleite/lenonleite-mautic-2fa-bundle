<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\EventListener;

use Mautic\CoreBundle\Helper\BundleHelper;
use MauticPlugin\LenonLeiteMautic2FABundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TwoFASubscriber implements EventSubscriberInterface
{
    public const PRIVATE_ROUTES_ALLOWED = [
        'lenonleitemautic_2fa_auth',
        'lenonleitemautic_2fa_verify',
        'login',
        'mautic_user_logout',
    ];

    public function __construct(
        private Config $config,
        private BundleHelper $bundleHelper,
        private UrlGeneratorInterface $router
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['TwoFAAuth', 3],
        ];
    }

    public function TwoFAAuth(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->config->isPublished()) {
            return;
        }

        // Check if request is an API request or dev environment
        if (
            str_contains($event->getRequest()->getRequestUri(), '/api/')
            || str_contains($event->getRequest()->getRequestUri(), '/_profiler/')
            || str_contains($event->getRequest()->getRequestUri(), '/_wdt/')
        ) {
            return;
        }

        if ($this->isPublicUrl($event)) {
            return;
        }

        if ($this->isAuthRouteAllowed($event)) {
            return;
        }

        $session                  = $event->getRequest()->getSession();
        $twoFASessionAuth         = $session->get('mautic.2fa.isAuth', false);
        if ($twoFASessionAuth) {
            return;
        }
        $url      = $this->router->generate('lenonleitemautic_2fa_auth');
        $redirect = new RedirectResponse($url);
        $event->setResponse($redirect);
    }

    private function isPublicUrl(RequestEvent $event): bool
    {
        $bundles = $this->bundleHelper->getMauticBundles(true);
        foreach ($bundles as $bundle) {
            if (
                !is_array($bundle)
                || !isset($bundle['config'])
                || !isset($bundle['config']['routes'])
                || !isset($bundle['config']['routes']['public'])
            ) {
                continue;
            }

            $route = $event->getRequest()->attributes->get('_route');

            if (array_key_exists($route, $bundle['config']['routes']['public'])) {
                return true;
            }
        }

        return false;
    }

    private function isAuthRouteAllowed(RequestEvent $event): bool
    {
        $route = $event->getRequest()->attributes->get('_route');

        return in_array($route, self::PRIVATE_ROUTES_ALLOWED);
    }
}
