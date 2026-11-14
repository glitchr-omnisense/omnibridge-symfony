<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use Base\Attributes\Attribute\Iconize;
use Base\Auth\Account\Devices;
use Base\Auth\Account\SecondFactor;
use Base\Bridge\Symfony\AccountScreens;
use Omnibridge\Symfony\Omnibase\ScreenResponder;
use Omnibridge\Symfony\Omnibase\Form\Type\Login2FAType;
use Base\Screen\View;
use Base\Service\SecurityPolicy;
use Omnibridge\Symfony\Omnibase\Response\AnswersOutcomes;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The account's settings under Symfony: omnibase's Base\Auth\Account\SecondFactor and Devices decide (plan "omnibase
 * agnostic", step F5e slice 5b) - the authenticator application turned on and off, its QR codes, the sessions and
 * passkeys -; the form, scheb's TOTP authenticator (the secret, the code's check, the QR's content) and the answers
 * are Symfony's.
 */
class UserSettingsController extends AbstractController
{
    use AnswersOutcomes;

    public function __construct(
        private readonly SecondFactor $secondFactor,
        private readonly Devices $devices,
        private readonly SecurityPolicy $securityPolicy,
    ) {
    }

    #[Route("/members/qr/totp", name: "qr_code_totp")]
    public function displayTotpQrCode(TotpAuthenticatorInterface $totpAuthenticator): Response
    {
        $shown = $this->secondFactor->enabled() ?? throw new NotFoundHttpException('Cannot display QR code');

        return new Response(SecondFactor::png($totpAuthenticator->getQRContent($shown)), 200, ['Content-Type' => 'image/png']);
    }

    /**
     * The account's settings: its template and its view model (Base\Screen\Account\SettingsPage) to omnibase's
     * ScreenResponder; a template the site overrides gets `user`, `sessions` and `policy` as well (deprecated).
     */
    #[Route("/settings", name: "user_settings")]
    #[Iconize("fa-solid fa-fw fa-user-cog")]
    public function Settings(Request $request, ScreenResponder $responder, AccountScreens $screens)
    {
        $user = $this->getUser();
        $sessions = $this->devices->sessions();
        $template = 'client/user/settings.html.twig';

        return $responder->respond($request, new View($template, $screens->settings($user instanceof \Base\Entity\User ? $user : null, $sessions), 'Account/Settings'), $responder->legacy($template, fn () => [
            'user' => $user,
            'sessions' => $sessions,
            'policy' => $this->securityPolicy,
        ]));
    }

    // The one-off "your account needs a second factor" page and its "not now", and the e-mailed code turned on or off,
    // are actions written once for every host: Base\Controller\Action\Settings\{Enrolment, EnrolmentSkip, EmailCodeToggle}.

    #[Route("/settings/passkeys/{id}/rename", name: "user_settings_passkey_rename", methods: ["POST"])]
    public function RenamePasskey(int $id, Request $request)
    {
        return $this->answer($this->devices->renamePasskey($id, $request->request->get('_token'), $request->request->get('label')));
    }

    #[Route("/settings/passkeys/{id}/delete", name: "user_settings_passkey_delete", methods: ["POST"])]
    public function DeletePasskey(int $id, Request $request)
    {
        return $this->answer($this->devices->deletePasskey($id, $request->request->get('_token')));
    }

    #[Route("/settings/sessions/{id}/logout", name: "user_settings_session_logout", methods: ["POST"])]
    public function LogoutSession(int $id, Request $request)
    {
        return $this->answer($this->devices->signOut($id, $request->request->get('_token')));
    }

    #[Route("/settings/2fa", name: "user_settings_2fa")]
    public function TwoFactorAuthentication(Request $request, TotpAuthenticatorInterface $totpAuthenticator)
    {
        if ($outcome = $this->secondFactor->enrolment()) {
            return $this->answer($outcome);
        }
        $user = $this->getUser();
        $secret = $this->secondFactor->pending($totpAuthenticator->generateSecret(...));
        $form = $this->createForm(Login2FAType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $valid = $totpAuthenticator->checkCode(SecondFactor::carrying($user, $secret), (string) $form->get('code')->getData());
            if ($outcome = $this->secondFactor->confirm($user, $secret, $valid)) {
                return $this->answer($outcome);
            }
            $form->get('code')->addError(new FormError($this->secondFactor->invalidCode()));
        }

        return $this->render(SecondFactor::FORM_TEMPLATE, ['form' => $form->createView(), 'user' => $user]);
    }

    #[Route("/settings/2fa/disable", name: "user_settings_2fa_disable", methods: ["POST"])]
    public function TwoFactorAuthentication_Disable(Request $request)
    {
        return $this->answer($this->secondFactor->disable($request->request->get('_token'), $request->request->get('password')));
    }

    #[Route("/settings/2fa/qr-code", name: "user_settings_2fa_qrcode")]
    public function TwoFactorAuthentication_QrCode(TotpAuthenticatorInterface $totpAuthenticator)
    {
        $waiting = $this->secondFactor->waiting() ?? throw new NotFoundHttpException('No pending 2FA setup');

        return new Response(SecondFactor::png($totpAuthenticator->getQRContent($waiting)), 200, ['Content-Type' => 'image/png']);
    }
}
