<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use Base\Service\SecurityPolicy;
use Symfony\Contracts\Translation\TranslatorInterface;

use Base\Entity\User\Connection;
use Base\Repository\User\Connections;
use Base\Repository\User\Passkeys;
use Base\Repository\Users;

use Base\Form\Type\Login2FAType;
use Base\Attributes\Attribute\Iconize;
use Base\Bridge\Symfony\AccountScreens;
use Base\Bridge\Symfony\ScreenResponder;
use Base\Screen\View;
use Base\Entity\User\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

use Omniauth\Contract\TotpSecrets;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

class UserSettingsController extends AbstractController
{
    private const SESSION_PENDING_SECRET = "2fa_pending_totp_secret";

    /** How many of the account's sessions the settings page lists. */
    private const SESSIONS_SHOWN = 20;

    private Users $userRepository;
    private SecurityPolicy $securityPolicy;
    private TranslatorInterface $translator;

    public function __construct(Users $userRepository, SecurityPolicy $securityPolicy, TranslatorInterface $translator)
    {
        $this->userRepository = $userRepository;
        $this->securityPolicy = $securityPolicy;
        $this->translator = $translator;
    }

    #[Route("/members/qr/totp", name: "qr_code_totp")]
    public function displayTotpQrCode(TotpAuthenticatorInterface $totpAuthenticator, ?TotpSecrets $totpSecrets = null): Response
    {
        $user = $this->getUser();
        // An account of omnibase (scheb's TOTP interface has no setter for the secret shown).
        if (!$user instanceof \Base\Entity\User || !$user->isTotpAuthenticationEnabled()) {
            throw new NotFoundHttpException('Cannot display QR code');
        }

        // The secret as the authenticator application scans it: opened by omniauth's store when the
        // site seals its secrets at rest (omniauth.yaml's totp.secret_key), the column's value otherwise.
        $secret = null !== $totpSecrets ? $totpSecrets->secret($user) : $user->getTotpSecret();
        $shown = clone $user;
        $shown->setTotpSecret($secret);

        return $this->displayQrCode($totpAuthenticator->getQRContent($shown));
    }

    private function displayQrCode(string $qrCodeContent): Response
    {
        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            data: $qrCodeContent,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 200,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        $result = $builder->build();

        return new Response($result->getString(), 200, ['Content-Type' => 'image/png']);
    }

    /**
     * @return string[] plain-text codes (only ever shown once, right after generation)
     */
    private function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3)));
        }
        return $codes;
    }

    /**
     * A demonstration account (the `demo` environment) changes nothing of
     * its second factor: every visitor signs in with it. The settings page
     * says so; this refuses the request all the same.
     */
    private function refuseDemoAccount(): ?Response
    {
        if (!$this->securityPolicy->isDemoAccount($this->getUser())) {
            return null;
        }

        $notification = new Notification("@notifications.demo.locked");
        $notification->send("warning");

        return $this->redirectToRoute('user_settings');
    }

    /**
     * The account's settings: its template and its view model
     * (Base\Screen\Account\SettingsPage, plan "omnibase agnostique", C5) to
     * omnibase's ScreenResponder - each small form an action with its token,
     * the answers stored by no cache; a template the site overrides gets
     * `user`, `sessions` and `policy` as well (deprecated, gone in 4.0).
     */
    #[Route("/settings", name: "user_settings")]
    #[Iconize("fa-solid fa-fw fa-user-cog")]
    public function Settings(Request $request, Connections $connectionRepository, ScreenResponder $responder, AccountScreens $screens)
    {
        $user = $this->getUser();

        // findByUser() is the finder DSL and hands back a Doctrine Query, not
        // results - passing that straight to Twig is why this page said "no
        // sessions" for an account with hundreds of them. Ask plainly, and
        // keep it to a screenful: the oldest of 600-odd rows tell nobody
        // anything, and the point of the list is to spot a session that
        // should not be there.
        $sessions = $connectionRepository->findBy(["user" => $user], ["updatedAt" => "DESC"], self::SESSIONS_SHOWN);

        $template = 'client/user/settings.html.twig';

        return $responder->respond($request, new View($template, $screens->settings($user instanceof \Base\Entity\User ? $user : null, $sessions), 'Account/Settings'), $responder->legacy($template, fn () => [
            'user' => $user,
            'sessions' => $sessions,
            'policy' => $this->securityPolicy,
        ]));
    }

    // The one-off "your account needs a second factor" page and its "not now"
    // (/settings/security-required, /settings/security-required/skip) are actions written once
    // for every host: Base\Controller\Action\Settings\Enrolment and EnrolmentSkip (R29).

    #[Route("/settings/passkeys/{id}/rename", name: "user_settings_passkey_rename", methods: ["POST"])]
    public function RenamePasskey(int $id, Request $request, Passkeys $passkeyRepository)
    {
        if (!$this->isCsrfTokenValid('passkey_rename_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $passkey = $passkeyRepository->find($id);
        if (!$passkey || $passkey->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        $passkey->setLabel(trim((string) $request->request->get('label')) ?: null);
        $passkeyRepository->flush();

        return $this->redirectToRoute('user_settings');
    }

    #[Route("/settings/passkeys/{id}/delete", name: "user_settings_passkey_delete", methods: ["POST"])]
    public function DeletePasskey(int $id, Request $request, Passkeys $passkeyRepository)
    {
        if (!$this->isCsrfTokenValid('passkey_delete_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $passkey = $passkeyRepository->find($id);
        if (!$passkey || $passkey->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        $passkeyRepository->remove($passkey, true);

        $notification = new Notification("@notifications.settings.passkeyDeleted");
        $notification->send("success");

        return $this->redirectToRoute('user_settings');
    }

    #[Route("/settings/sessions/{id}/logout", name: "user_settings_session_logout", methods: ["POST"])]
    public function LogoutSession(int $id, Request $request, Connections $connectionRepository)
    {
        if (!$this->isCsrfTokenValid('session_logout', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        $connection = $connectionRepository->find($id);

        if (!$connection || $connection->getUser() !== $user) {
            throw $this->createNotFoundException();
        }

        $connection->markAsLogout();
        $connectionRepository->flush();

        return $this->redirectToRoute('user_settings');
    }

    #[Route("/settings/2fa", name: "user_settings_2fa")]
    public function TwoFactorAuthentication(Request $request, TotpAuthenticatorInterface $totpAuthenticator, ?TotpSecrets $totpSecrets = null)
    {
        $user = $this->getUser();
        $session = $request->getSession();

        if ($refusal = $this->refuseDemoAccount()) {
            return $refusal;
        }

        // The administrator can switch the whole feature off. Refuse here as
        // well as hiding the button, so a bookmarked URL is refused too.
        if (!$this->securityPolicy->canEnableTwoFactor()) {
            $notification = new Notification("@notifications.settings.twoFactorUnavailable");
            $notification->send("warning");

            return $this->redirectToRoute('user_settings');
        }

        if ($user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('user_settings');
        }

        $pendingSecret = $session->get(self::SESSION_PENDING_SECRET) ?? $totpAuthenticator->generateSecret();
        $session->set(self::SESSION_PENDING_SECRET, $pendingSecret);

        $form = $this->createForm(Login2FAType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Validate against a throwaway clone: the real, tracked $user entity must not carry an
            // unconfirmed secret, since other requests in this app flush() the entity manager as a
            // side effect (e.g. UserTracker::updateConnection()) and would silently persist it.
            $pendingUser = clone $user;
            $pendingUser->setTotpSecret($pendingSecret);

            if ($totpAuthenticator->checkCode($pendingUser, $form->get('code')->getData())) {

                // Kept through omniauth's store when there is one: sealed at rest when the site sets
                // omniauth.yaml's totp.secret_key (Marco's decision QA5), as it is otherwise.
                if (null !== $totpSecrets) {
                    $totpSecrets->enable($user, $pendingSecret);
                } else {
                    $user->setTotpSecret($pendingSecret);
                }
                $backupCodes = $this->generateBackupCodes();
                $user->setBackupCodes($backupCodes);
                $this->userRepository->flush();

                $session->remove(self::SESSION_PENDING_SECRET);

                $notification = new Notification("@notifications.settings.twoFactorEnabled");
                $notification->send("success");

                return $this->render('client/user/settings_2fa_backup_codes.html.twig', [
                    'backupCodes' => $backupCodes,
                ]);
            }

            $form->get('code')->addError(new \Symfony\Component\Form\FormError($this->translator->trans('@notifications.settings.twoFactorInvalidCode')));
        }

        return $this->render('client/user/settings_2fa.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }

    #[Route("/settings/2fa/disable", name: "user_settings_2fa_disable", methods: ["POST"])]
    public function TwoFactorAuthentication_Disable(Request $request, UserPasswordHasherInterface $passwordHasher)
    {
        $user = $this->getUser();

        // Refused before anything is checked: nothing is changed, and the settings page shows a demonstration account no form to send.
        if ($refusal = $this->refuseDemoAccount()) {
            return $refusal;
        }

        if (!$this->isCsrfTokenValid('2fa_disable', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // The precedence rule: while the administrator requires a second
        // factor, the user cannot give theirs up - password or no password.
        if (!$this->securityPolicy->canDisableTwoFactor($user)) {
            $notification = new Notification("@notifications.settings.twoFactorMandatory");
            $notification->send("warning");

            return $this->redirectToRoute('user_settings');
        }

        if (!$passwordHasher->isPasswordValid($user, (string) $request->request->get('password'))) {
            $notification = new Notification("@notifications.settings.wrongPassword");
            $notification->send("danger");
            return $this->redirectToRoute('user_settings');
        }

        $user->setTotpSecret(null);
        $user->setBackupCodes([]);
        $user->invalidateTrustedDevices();
        $this->userRepository->flush();

        $notification = new Notification("@notifications.settings.twoFactorDisabled");
        $notification->send("success");

        return $this->redirectToRoute('user_settings');
    }

    // The e-mailed code turned on or off (/settings/2fa/email) is an action written once for every host:
    // Base\Controller\Action\Settings\EmailCodeToggle (R29).

    #[Route("/settings/2fa/qr-code", name: "user_settings_2fa_qrcode")]
    public function TwoFactorAuthentication_QrCode(Request $request, TotpAuthenticatorInterface $totpAuthenticator)
    {
        $user = $this->getUser();
        $secret = $request->getSession()->get(self::SESSION_PENDING_SECRET);
        if (!$secret) {
            throw new NotFoundHttpException('No pending 2FA setup');
        }

        $pendingUser = clone $user;
        $pendingUser->setTotpSecret($secret);

        return $this->displayQrCode($totpAuthenticator->getQRContent($pendingUser));
    }
}
