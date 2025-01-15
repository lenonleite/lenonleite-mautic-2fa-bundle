<?php

namespace MauticPlugin\LenonLeiteMautic2FABundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\UserBundle\Entity\User;
use Mautic\UserBundle\Model\UserModel;
use MauticPlugin\LenonLeiteMautic2FABundle\Form\Type\TwoFALoginType;
use MauticPlugin\LenonLeiteMautic2FABundle\Helper\TwoFAAuthHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class TwoFAController extends CommonController
{
    public function __construct(
        protected ManagerRegistry $doctrine,
        protected MauticFactory $factory,
        protected ModelFactory $modelFactory,
        UserHelper $userHelper,
        protected CoreParametersHelper $coreParametersHelper,
        protected EventDispatcherInterface $dispatcher,
        protected Translator $translator,
        FlashBag $flashBag,
        ?RequestStack $requestStack,
        protected ?CorePermissions $security,
        private TwoFAAuthHelper $twoFAAuthHelper,
        private UserModel $userModel,
    ) {
        parent::__construct($doctrine, $factory, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
    }

    /**
     * @return RedirectResponse|JsonResponse|array<string,string>|Response
     */
    public function indexAction(Request $request): RedirectResponse|JsonResponse|array|Response
    {
        $srcQrCode2fa          = '';
        $currentUser           = $this->getUser();
        if (!$currentUser) {
            return $this->accessDenied();
        }

        assert($currentUser instanceof User);
        $twoFAQRCode           = $this->getTwoFAQRCode($currentUser);
        if (
            empty($twoFAQRCode)
            && Request::METHOD_GET === $request->getMethod()
        ) {
            $currentUser             = $this->twoFAAuthHelper->registerTwoFactorAuth($currentUser);
            $srcQrCode2fa            = $this->getTwoFAQRCode($currentUser);
        }

        if (
            Request::METHOD_POST === $request->getMethod()
            && $twoFAQRCode
        ) {
            $twoFaCodeRequest = $request->get('_twofacode');

            $code    = $this->twoFAAuthHelper->getCode($this->getTwoFASecret($currentUser));

            if ($code === $twoFaCodeRequest) {
                $session = $request->getSession();
                $session->set('mautic.2fa.isAuth', true);

                return $this->redirectToRoute('mautic_dashboard_index');
            }
        }

        $form                  = $this->createForm(TwoFALoginType::class, $request->request->all());
        $this->addFlash('error', 'Authenticating with 2FA wrong');

        return $this->delegateView([
            'viewParameters' => [
                'srcQrCode2fa' => $srcQrCode2fa,
                'code'         => $this->twoFAAuthHelper->getCode($this->getTwoFASecret($currentUser)),
                'form'         => $form->createView(),
            ],
            'contentTemplate' => '@LenonLeiteMautic2FA/Security/twoFA.html.twig',
        ]);
    }

    private function getTwoFASecret(User $user): string
    {
        if (
            is_array($user->getPreferences())
            && isset($user->getPreferences()['2fa'])
            && isset($user->getPreferences()['2fa']['twofa_secret'])
        ) {
            return $user->getPreferences()['2fa']['twofa_secret'];
        }

        return '';
    }

    private function getTwoFAQRCode(User $user): string
    {
        $preferences = $user->getPreferences();
        if (
            is_array($preferences)
            && isset($preferences['2fa'])
            && isset($preferences['2fa']['twofa_src_qrcode'])
        ) {
            return $preferences['2fa']['twofa_src_qrcode'];
        }

        return '';
    }

    /**
     * @return RedirectResponse|JsonResponse|array<string,string>
     */
    public function batchRecover2faAction(Request $request): RedirectResponse|JsonResponse|array
    {
        if (
            !$this->security->isGranted('user:users:edit')) {
            return $this->accessDenied();
        }

        $ids = json_decode($request->query->get('ids', ''), true);
        if (empty($ids)) {
            $this->addFlashMessage('mautic.plugin.lenonleitemautic2fa.batch_reset_error');

            return $this->redirectToRoute('mautic_user_index');
        }
        assert(is_array($ids));
        $users = $this->userModel->getEntities($ids);

        foreach ($users as $user) {
            $this->resetTwoFA($user);
        }

        $this->addFlashMessage('mautic.plugin.lenonleitemautic2fa.batch_reset_success', ['%count%' => count($users)]);

        return $this->redirectToRoute('mautic_user_index');
    }

    private function resetTwoFA(User $user): User
    {
        $preferences                                      = $user->getPreferences();

        if (!is_array($preferences)) {
            $preferences = [];
        }

        $preferences['2fa']               = [
            'twofa_secret'     => '',
            'twofa_src_qrcode' => '',
        ];

        $user->setPreferences($preferences);
        $this->userModel->saveEntity($user);

        return $user;
    }
}
