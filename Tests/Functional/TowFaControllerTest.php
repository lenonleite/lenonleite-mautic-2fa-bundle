<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LenonLeiteMautic2FABundle\Integration\LenonLeiteMautic2FAIntegration;
use MauticPlugin\LenonLeiteMautic2FABundle\Tests\Helper\SettingsTestHelper;
use Symfony\Component\HttpFoundation\Request;

class TowFaControllerTest extends MauticMysqlTestCase
{
    private SettingsTestHelper $settingsTestHelper;

    public function setUp(): void
    {
        parent::setUp();
        $this->activePlugin();
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    public function testWhenUserTryAuthAfterLoginNoSuccess(): void
    {
        $this->activePlugin(true);
        $username                                          = 'admin';
        $crawler                                           = $this->client->request('GET', '/s/logout');
        $form                                              = $crawler->selectButton('login')->form();
        $form->setValues(
            [
                '_username' => $username,
                '_password' => 'mautica',
            ]
        );
        $this->client->submit($form);
        $this->assertStringContainsString(
            'forgot your password?',
            $this->client->getResponse()->getContent(),
            'The return must contain text about two factor authentication.'
        );
        $this->cleanUserPreferences($username);
        $crawler = $this->client->request('GET', '/s/logout');

        $form = $crawler->selectButton('login')->form();
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

    private function cleanUserPreferences($username = 'admin'): void
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

    /**
     * @depends testWhenUserTryAuthAfterLoginNoSuccess
     */
    public function testUserDontHasAuthTwoFaFirstAccess()
    {
        $this->configure([
            'code5_2fa_enabled'       => 1,
            'code5_2fa_admin_allowed' => false,
        ]);
        $session = $this->client->getContainer()->get('session');
        $session->set('mautic.code5.2fa.isAuth', false);
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
    public function configure($configParams = [])
    {
        $this->configParams       = array_merge($this->configParams, $configParams);
        $this->useCleanupRollback = false;
        $this->setUpSymfony($this->configParams);
    }

    /**
     * @return void
     */
    public function testUSerHasAuthTwoFaSecondAccessForFirstTimeShowCodeAndNextDontShow()
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
    public function testUserDisabled()
    {
        $session = $this->client->getContainer()->get('session');
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
    public function testWhenUserAccessWithoutTwoFaActivated()
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

    public function testLogoutIsOkByGet()
    {
        $session = $this->client->getContainer()->get('session');
        $session->set('mautic.code5.2fa.isAuth', true);
        $this->client->request(Request::METHOD_GET, '/s/logout');
        $this->assertStringContainsString(
            'keep me logged in',
            $this->client->getResponse()->getContent(),
            'Check if Logout is ok but get'
        );
    }

    public function testIfRecoveryPasswordIsOk()
    {
        $session = $this->client->getContainer()->get('session');
        $session->set('mautic.code5.2fa.isAuth', true);
        $this->client->request(Request::METHOD_GET, '/s/logout');
        $this->assertStringContainsString(
            'keep me logged in',
            $this->client->getResponse()->getContent(),
            'Check if Logout is ok but get'
        );
        $this->client->request(Request::METHOD_GET, '/passwordreset');
        $this->assertStringContainsString(
            'reset password',
            $this->client->getResponse()->getContent(),
            'Check if Logout is ok but get'
        );
    }

    public function testDeniedAccessWithAdminAllowedToNotAdminResultAskAboutTwoFa()
    {
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
