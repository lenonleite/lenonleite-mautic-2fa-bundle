<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Tests\Helper;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Symfony\Component\HttpFoundation\Request;

// class UserTestHelper extends MauticMysqlTestCase

class UserTestHelper extends MauticMysqlTestCase
{
    public static function skeletonNewRole(): array
    {
        return [
            'name'        => 'Test',
            'isAdmin'     => 0,
            'description' => '<p>test description</p>',
            'permissions' => [
                'asset:categories' => [
                    0 => 'view',
                ],
                'campaign:categories' => [
                    0 => 'view',
                ],
                'category:categories' => [
                    0 => 'view',
                ],
                'channel:categories' => [
                    0 => 'view',
                ],
                'lead:leads' => [
                    0 => 'viewown',
                ],
                'core:themes' => [
                    0 => 'view',
                ],
                'form:categories' => [
                    0 => 'full',
                ],
                'marketplace:packages' => [
                    0 => 'full',
                ],
            ],
        ];
    }

    /**
     * @return \Symfony\Bundle\FrameworkBundle\KernelBrowser|\Symfony\Component\BrowserKit\AbstractBrowser|null
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return mixed
     */
    public function newUser($client, array $settings = [], bool $isAdmin = false)
    {
        $defaultSettings = self::skeletonNewUser($isAdmin);

        $user['user'] = array_merge($defaultSettings['user'], $settings);

        $crawler = $client->request(Request::METHOD_GET, '/s/users/new');

        $result = $crawler->filter('form[name="user"]')->form()->all();

        $user['user']['_token'] = $result['user[_token]']->getValue();

        $client->request('POST', '/s/users/new', $user);
        $clientResponse        = $client->getResponse();
        $clientResponseContent = $clientResponse->getContent();
        $this->assertTrue($clientResponse->isOk(), 'Return code must be 200.');

        $this->assertStringContainsString(
            'has been created',
            $clientResponseContent,
            'The return must contain success message'
        );

        return $user;
    }

    /**
     * @return array[]
     */
    public static function skeletonNewUser(bool $administrator = false): array
    {
        $idRole = 2;
        if ($administrator) {
            $idRole = 1;
        }

        return [
            'user' => [
                'firstName'     => 'lenon',
                'lastName'      => 'leite',
                'role'          => $idRole,
                'position'      => '',
                'signature'     => 'Best regards, |FROM_NAME|',
                'username'      => 'lenonleite',
                'email'         => md5(mt_rand()).'@gmail.com',
                'plainPassword' => [
                    'password' => '123',
                    'confirm'  => '123',
                ],
                'timezone'    => '',
                'locale'      => '',
                'isPublished' => 1,
                'buttons'     => [
                    'apply' => '',
                ],
            ],
        ];
    }
}
