<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Tests\Helper;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

class SettingsTestHelper
{
    private array $settings = [];

    /**
     * @return void
     */
    public function configureBackend($client, $settings = [], $checkAtTheEnd = false)
    {
        $this->settings = self::skeletonSettings();
        $this->settings = array_merge($this->settings, $settings);
        // request config edit page
        $crawler = $client->request(Request::METHOD_GET, '/s/config/edit');
        Assert::assertTrue($client->getResponse()->isOk());

        // Find save & close button
        $buttonCrawler = $crawler->selectButton('config[buttons][save]');
        $form          = $buttonCrawler->form();
        $form->setValues($this->settings);

        $crawler = $client->submit($form);
        Assert::assertTrue($client->getResponse()->isOk());

        // Check for a flash error
        $response = $client->getResponse()->getContent();
        $message  = $crawler->filterXPath("//div[@id='flashes']//span")->count()
            ?
            $crawler->filterXPath("//div[@id='flashes']//span")->first()->text()
            :
            '';
        Assert::assertStringNotContainsString('Could not save updated configuration:', $response, $message);
        if ($checkAtTheEnd) {
            return $client;
        }
        // Check values are unescaped properly in the edit form
        $crawlerCheck = $client->request(Request::METHOD_GET, '/s/config/edit');
        Assert::assertTrue($client->getResponse()->isOk());

        $buttonCrawler = $crawlerCheck->selectButton('config[buttons][save]');
        $form          = $buttonCrawler->form();
        foreach ($this->settings as $key => $value) {
            if (!is_string($form[$key]->getValue())) {
                continue;
            }
            Assert::assertEquals(
                $value,
                $form[$key]->getValue()
            );
        }

        return $client;
    }

    public static function skeletonSettings(): array
    {
        return [
            'config[leadconfig][contact_columns]'                                => ['name', 'email', 'id'],
            'config[coreconfig][site_url]'                                       => 'https://mautic.dvl.to', // required
            'config[code5securityconfig][code5_form_password_no_administrator]'  => rand(6, 10),
            'config[code5securityconfig][code5_form_password_administrator]'     => rand(6, 13),
            'config[code5securityconfig][code5_form_password_special_character]' => 1,
            'config[code5securityconfig][code5_form_password_numbers]'           => 1,
            'config[code5securityconfig][code5_form_password_lowercase]'         => 1,
            'config[code5securityconfig][code5_form_password_uppercase]'         => 1,
            'config[code5securityconfig][code5_2fa_enabled]'                     => 1,
        ];
    }

    public function getSettings(): array
    {
        return $this->settings;
    }
}
