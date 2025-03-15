<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LenonLeiteMautic2FABundle\Integration\LenonLeiteMautic2FAIntegration;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class TowFaControllerTest extends MauticMysqlTestCase
{
    private SessionInterface $session;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
        // @phpstan-ignore-next-line
        $this->session = static::getContainer()->get(SessionInterface::class);
    }

    public function testWhenUserTryAuthAfterLoginNoSuccess(): void
    {
        $this->activePlugin(true);
        $username                                          = 'admin';
        $crawler                                           = $this->client->request('GET', '/s/logout');
        $form                                              = $crawler->filter('form[name="login"]')->form();
        $form->setValues(
            [
                '_username' => $username,
                '_password' => 'mautica',
            ]
        );
        $this->client->submit($form);
        $this->assertStringContainsString(
            'Forgot your password?',
            $this->client->getResponse()->getContent(),
            'The return must contain text about two factor authentication.'
        );
        $this->cleanUserPreferences($username);
        $crawler = $this->client->request('GET', '/s/logout');

        $form = $crawler->filter('form[name="login"]')->form();
        $form->setValues(
            [
                '_username' => $username,
                '_password' => 'Maut1cR0cks!',
            ]
        );
        $this->client->submit($form);
        $this->assertStringContainsString(
            'Attention: You must use a two-factor authentication application to access the system.',
            $this->client->getResponse()->getContent(),
            'The return must contain text about two factor authentication.'
        );
        $this->client->request('GET', '/s/dashboard');
        $this->assertStringContainsString(
            'Please enter the code from your authenticator app',
            $this->client->getResponse()->getContent(),
            'The return must contain text about two factor authentication.'
        );
    }

    private function cleanUserPreferences(string $username = 'admin'): void
    {
        if ('admin' != $username and 'sales' != $username) {
            return;
        }
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['username' => $username]);
        $preferences                                      = $user->getPreferences();
        $preferences['2fa']['twofa_secret']               = '';
        $preferences['2fa']['twofa_src_qrcode']           = '';
        $user->setPreferences($preferences);
        $this->em->persist($user);
        $this->em->flush();
    }

    public function testUserDontHasAuthTwoFaFirstAccess(): void
    {
        $this->activePlugin(true);
        if ($this->client->getContainer()->get('security.token_storage')->getToken()) {
            $this->client->request('GET', '/s/logout');
        }
        $this->session->set('mautic.code5.2fa.isAuth', false);
        $this->client->request('GET', '/s/dashboard');
        $this->assertStringContainsString(
            'Attention: You must use a two-factor authentication application to access the system.',
            $this->client->getResponse()->getContent(),
            'The return must contain text about two factor authentication.'
        );
        $this->client->request('GET', '/s/dashboard');
    }

    /**
     * @return void
     */
    public function testUSerHasAuthTwoFaSecondAccessForFirstTimeShowCodeAndNextDontShow(): void
    {
        $this->activePlugin(true);
        $this->cleanUserPreferences();
        $this->client->request('GET', '/s/dashboard');
        $this->assertStringContainsString(
            'Attention: You must use a two-factor authentication application to access the system.',
            $this->client->getResponse()->getContent(),
            'The return must contain text about two factor authentication.'
        );

        $this->client->request('GET', '/s/dashboard');
        $this->assertStringContainsString(
            'Please enter the code from your authenticator app',
            $this->client->getResponse()->getContent(),
            'The return must contain qrcode image.'
        );
    }

    /**
     * @return void
     */
    public function testUserDisabled(): void
    {
        $session = $this->session;
        $session->set('mautic.code5.2fa.isAuth', true);
        $this->client->request('GET', '/s/code5/2fa/2/reset');
        $userSales = $this->em->getRepository(User::class)
            ->findOneBy(['username' => 'sales']);
        $preferences = $userSales->getPreferences();
        $this->assertTrue(
            empty($preferences['Code5SecurityBundle']['code5_2fa_enabled'])
        );
        $this->assertTrue(
            empty($preferences['Code5SecurityBundle']['twofa_src_qrcode'])
        );
    }

    /**
     * @return void
     */
    public function testWhenUserAccessWithoutTwoFaActivated(): void
    {
        $this->activePlugin(false);
        $this->client->request('GET', '/s/dashboard');
        $this->assertStringContainsString(
            '/s/dashboard',
            $this->client->getResponse()->getContent(),
            'Dashboard |\n
                Mautic'
        );
    }

    public function testLogoutIsOkByGet(): void
    {
        $session = $this->session;
        $session->set('mautic.code5.2fa.isAuth', true);

        $this->client->request(Request::METHOD_GET, '/s/logout');
        $this->assertStringContainsString(
            'Forgot your password?',
            $this->client->getResponse()->getContent(),
            'Check if Logout is ok but get'
        );
    }

    public function testIfRecoveryPasswordIsOk(): void
    {
        $this->activePlugin(true);
        $session = $this->session;
        $session->set('mautic.code5.2fa.isAuth', true);
        $this->client->request(Request::METHOD_GET, '/s/logout');
        $this->assertStringContainsString(
            'Forgot your password?',
            $this->client->getResponse()->getContent(),
            'Check if Logout is ok but get'
        );
        $this->client->request(Request::METHOD_GET, '/passwordreset');
        $this->assertStringContainsString(
            'Enter either your username or email to reset your password.',
            $this->client->getResponse()->getContent(),
            'Check if Logout is ok but get'
        );
    }

    public function testDeniedAccessWithAdminAllowedToNotAdminResultAskAboutTwoFa(): void
    {
        $this->activePlugin(true);
        $username           = 'sales';
        $this->clientServer = [
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW'   => 'mautic',
        ];
        $this->cleanUserPreferences($username);
        $this->client->request('GET', '/s/dashboard');
        $this->assertStringContainsString(
            'Attention: You must use a two-factor authentication application to access the system.',
            $this->client->getResponse()->getContent()
        );
        $crawlers = $this->client->request('GET', '/s/dashboard');
        $this->assertStringContainsString(
            'Please enter the code from your authenticator app',
            $crawlers->html()
        );
        $this->clientServer = [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW'   => 'mautic',
        ];
        $this->setUpSymfony($this->configParams);
        $this->client->request('GET', '/s/dashboard');
        $this->assertStringContainsString(
            'Please enter the code from your authenticator app',
            $crawlers->html()
        );
    }

    private function activePlugin(bool $isPublished = true): void
    {
        $this->client->request('GET', '/s/plugins/reload');
        $nameBundle  = 'LenonLeiteMautic2FABundle';
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => LenonLeiteMautic2FAIntegration::INTEGRATION_NAME]);
        if (empty($integration)) {
            $plugin      = $this->em->getRepository(Plugin::class)->findOneBy(['bundle' => $nameBundle]);
            $integration = new Integration();
            $integration->setName(str_replace('Bundle', '', $nameBundle));
            $integration->setPlugin($plugin);
        }
        $integration->setIsPublished($isPublished);
        $this->em->persist($integration);
        $this->em->flush();
    }
}
