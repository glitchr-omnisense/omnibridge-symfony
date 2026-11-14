<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use App\Entity\User;
use App\Form\Type\SecurityLoginType;
use App\Form\Type\SecurityRegistrationType;
use Base\Attributes\Attribute\Iconize;
use Base\Attributes\Attribute\IsGranted;
use Base\Auth\Account\AlreadySignedIn;
use Base\Auth\Account\Closing;
use Base\Auth\Account\Outcome;
use Base\Auth\Account\Passwords;
use Base\Auth\Account\SignIn;
use Base\Auth\Account\SignOutPage;
use Base\Auth\Account\Verification;
use Base\Auth\Account\WaitingPages;
use Base\Auth\Registration;
use Omnibridge\Symfony\Omnibase\Form\FormProcessorInterface;
use Omnibridge\Symfony\Omnibase\Form\FormProxyInterface;
use Omnibridge\Symfony\Omnibase\Form\Type\SecurityLoginTokenType;
use Omnibridge\Symfony\Omnibase\Form\Type\SecurityResetPasswordConfirmType;
use Omnibridge\Symfony\Omnibase\Form\Type\SecurityResetPasswordType;
use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface;
use Omnibridge\Symfony\Omnibase\Security\LoginFormAuthenticator;
use Omnibridge\Symfony\Omnibase\Security\SignInGuard;
use Base\Security\SignOut;
use Base\Service\ParameterBagInterface;
use Base\Service\ReferrerInterface;
use Base\Service\SpeculativeRequest;
use Base\Validator\Constraints\UniqueEntity;
use Omnibridge\Symfony\Omnibase\Response\AnswersOutcomes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * The account's pages under Symfony: omnibase's Base\Auth\Account rules decide each (plan "omnibase agnostic",
 * step F5e slice 5) - the sign-in and its link, the sign-out, the e-mail's verification and the approval, the
 * account closed and opened again, the passwords, the waiting pages -, the same omnibridge/laravel asks. What
 * stays here is Symfony's: the forms, the firewall's sign-out and its token, the authenticator that signs in.
 */
class SecurityController extends AbstractController
{
    use AnswersOutcomes;

    public function __construct(
        protected SignIn $signIn,
        protected SignOutPage $signOut,
        protected AlreadySignedIn $already,
        protected Verification $verification,
        protected Closing $closing,
        protected Passwords $passwords,
        protected WaitingPages $waiting,
        protected AdvancedRouterInterface $router,
        protected FormProxyInterface $formProxy,
        protected ParameterBagInterface $parameterBag,
        protected ReferrerInterface $referrer,
    ) {
    }

    #[Route("/login", name: "security_login")]
    #[Iconize("fa-solid fa-fw fa-arrow-right-to-bracket")]
    public function Login(Request $request, AuthenticationUtils $authenticationUtils, ?SignInGuard $signInGuard = null): Response
    {
        if ($outcome = $this->signIn->page()) {
            return $this->answer($outcome);
        }

        $lastUsername = $authenticationUtils->getLastUsername();
        $formProcessor = $this->formProxy
            ->createProcessor("form:login", SecurityLoginType::class, [
                "identifier" => $lastUsername,
                "allow_login_token" => $this->parameterBag->get("base.user.login_with_token"),
                // After a few failed sign-ins from this address, the captcha (SignInGuard checks it).
                "guard" => null !== ($gateway = $signInGuard?->required($request))
                    ? ["trap" => false, "min_delay" => 0, "reputation" => false, "challenge" => $gateway, "action" => SignInGuard::ACTION, "captcha_after" => 0]
                    : false,
            ])
            ->handleRequest($request);

        return $this->render('security/login.html.twig', [
            "identifier" => $lastUsername,
            "form" => $formProcessor->getForm()->createView(),
            "last_username" => $lastUsername,
            "error" => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route("/logout", name: "security_logout")]
    #[Iconize("fa-solid fa-fw fa-right-from-bracket")]
    public function Logout(Request $request): Response
    {
        // A prefetch is not a click: nothing to cache, nobody signed out.
        if (SpeculativeRequest::is($request)) {
            return new Response("", Response::HTTP_NO_CONTENT, ["Cache-Control" => "no-store"]);
        }

        if (!$this->getUser()) {
            $this->signOut->left($request->getSession()?->remove("_user"));

            return $this->answer($this->signOut->after($this->router->isSecured($request)));
        }

        // The firewall's sign-out and its token (enable_csrf): asked to confirm without the session's, handed on with it.
        $logout = $this->router->getRouteFirewall($this->router->generate(LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE))?->getLogout() ?? [];
        $parameter = $logout["csrf_parameter"] ?? SignOut::PARAMETER;
        $tokenId = $logout["csrf_token_id"] ?? SignOut::TOKEN_ID;
        $token = $request->query->get($parameter);
        if ($this->signOut->confirms((bool) ($logout["enable_csrf"] ?? false), $tokenId, $token)) {
            return $this->render(SignOutPage::TEMPLATE, [
                "logoutPath" => $this->router->generate(LoginFormAuthenticator::LOGOUT_ROUTE, [$parameter => $this->container->get('security.csrf.token_manager')->getToken($tokenId)->getValue()]),
                "returnUrl" => $this->referrer->getUrl() ?? $this->router->getUrlIndex(),
            ], new Response(null, Response::HTTP_OK, ["Cache-Control" => "no-store"]));
        }

        $response = $this->redirectToRoute(LoginFormAuthenticator::LOGOUT_REQUEST_ROUTE, is_string($token) ? [$parameter => $token] : []);
        $response->headers->clearCookie('REMEMBERME', "/", $this->router->getDomain());

        return $response;
    }

    #[Route("/logout-request", name: "security_logoutRequest")]
    public function LogoutRequest()
    {
        throw new Exception("This page should not be displayed.. Firewall should take over during logout process. Please check your configuration..");
    }

    #[Route("/login/token/{token}", name: "security_loginWithToken")]
    #[Iconize("fa-solid fa-fw fa-arrow-right-to-bracket")]
    public function LoginTokenRequest(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator, ?string $token = null, ?\Omniauth\Omniauth $omniauth = null, ?\Omnibridge\Symfony\Auth\Contexts $contexts = null): Response
    {
        // Where omniauth signs in, it issues the links too (plan "omniauth", QA4): the address goes to it.
        $byOmniauth = null !== $omniauth && null !== $contexts && $omniauth->methods()->has(\Omniauth\Factor::Link);
        if ($outcome = $this->signIn->link($token, $byOmniauth)) {
            return $this->answer($outcome, $this->signingIn($request, $userAuthenticator, $authenticator));
        }

        $formProcessor = $this->formProxy
            ->createProcessor("form:login", SecurityLoginTokenType::class)
            ->onSubmit(function (FormProcessorInterface $formProcessor, Request $request) use ($byOmniauth, $omniauth, $contexts) {
                $email = $formProcessor->getForm()->get('email')->getData();
                if ($byOmniauth) {
                    $omniauth->begin(\Omniauth\Factor::Link, $contexts->of($request), ['identifier' => (string) $email]);
                }

                return $this->answer($this->signIn->linkRequested($email, $byOmniauth));
            })
            ->handleRequest($request);

        return $this->render('security/login_token.html.twig', ["form" => $formProcessor->getForm()->createView()]);
    }

    #[Route("/register", name: "security_register")]
    public function Register(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator, Registration $registration): Response
    {
        if ($outcome = $this->already->check()) {
            return $this->answer($outcome);
        }

        return $this->formProxy->createProcessor("form:login", SecurityRegistrationType::class, ['validation_groups' => ['new'], 'validation_entity' => User::class])
            ->onSubmit(function (FormProcessorInterface $formProcessor, Request $request) use ($userAuthenticator, $authenticator, $registration) {
                // Approved, its password, named, kept: the same on every host (Base\Auth\Registration).
                $signedIn = $this->getUser();
                $newUser = $registration->register($formProcessor->hydrate((new User())), $formProcessor->getData("plainPassword"), $signedIn instanceof User ? $signedIn : null);

                return $this->answer(Outcome::route(AlreadySignedIn::PROFILE_ROUTE)->signingIn($newUser, true), $this->signingIn($request, $userAuthenticator, $authenticator));
            })
            ->onDefault(fn (FormProcessorInterface $formProcessor) => $this->render('security/register.html.twig', [
                'form' => $formProcessor->getForm()->createView(),
                'user' => $formProcessor->getData(),
                // The address already has an account: the page says so, and gives the ways in.
                'account_exists' => $this->addressIsTaken($formProcessor->getForm()),
            ]))
            ->handleRequest($request)
            ->getResponse();
    }

    /** Whether Symfony's validator refused the sign-up because its address already has an account. */
    private function addressIsTaken(FormInterface $form): bool
    {
        foreach ($form->isSubmitted() ? $form->getErrors(true) : [] as $error) {
            $constraint = ($cause = $error->getCause()) instanceof ConstraintViolationInterface ? $cause->getConstraint() : null;
            if ($constraint instanceof UniqueEntity && \in_array("email", (array) $constraint->fields, true)) {
                return true;
            }
        }

        return false;
    }

    #[Route("/verify-email", name: "security_verifyEmail")]
    #[IsGranted("ROLE_USER")]
    public function VerifyEmailRequest(): Response
    {
        return $this->answer($this->verification->request());
    }

    #[Route("/verify-email/{token}", name: "security_verifyEmailWithToken")]
    public function VerifyEmailResponse(Request $request, UserAuthenticatorInterface $userAuthenticator, LoginFormAuthenticator $authenticator, string $token): Response
    {
        if ($found = $this->verification->tokenOwner($token)) {
            $userAuthenticator->authenticateUser($found->getUser(), $authenticator, $request);
        }

        return $this->answer($this->verification->confirm($found));
    }

    #[Route("/admin-approval", name: "security_adminApproval")]
    public function AdminApprovalRequest(): Response
    {
        return $this->answer($this->verification->approval());
    }

    #[Route("/account-goodbye", name: "security_accountGoodbye")]
    public function DisableAccountRequest(): Response
    {
        return $this->answer($this->closing->close());
    }

    #[Route("/welcome-back/{token}", name: "security_accountWelcomeBackWithToken")]
    public function EnableAccountRequest(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator, ?string $token = null): Response
    {
        return $this->answer($this->closing->welcomeBack($token), $this->signingIn($request, $userAuthenticator, $authenticator));
    }

    #[Route("/reset-password", name: "security_resetPassword")]
    public function ResetPasswordRequest(Request $request): Response
    {
        if ($outcome = $this->already->check()) {
            return $this->answer($outcome);
        }

        $form = $this->createForm(SecurityResetPasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && ($outcome = $this->passwords->resetRequested($form->get('email')->getData()))) {
            return $this->answer($outcome);
        }

        return $this->render('security/reset_password_request.html.twig', ['form' => $form->createView()]);
    }

    #[Route("/reset-password/{token}", name: "security_resetPasswordWithToken")]
    public function ResetPasswordResponse(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator, ?string $token = null): Response
    {
        $resetToken = $this->already->check() ?? $this->passwords->resetToken($token);
        if ($resetToken instanceof Outcome) {
            return $this->answer($resetToken);
        }

        $form = $this->createForm(SecurityResetPasswordConfirmType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->answer($this->passwords->reset($resetToken, $form->get('plainPassword')->getData()), $this->signingIn($request, $userAuthenticator, $authenticator));
        }

        return $this->render('security/reset_password.html.twig', ['form' => $form->createView()]);
    }

    /** A password to choose before anything else (user:create's token; PasswordChangeSubscriber brings the account here). */
    #[Route("/change-password", name: "security_changePassword")]
    public function ChangePassword(Request $request, LoginFormAuthenticator $authenticator, UserAuthenticatorInterface $userAuthenticator): Response
    {
        $changeToken = $this->passwords->changeToken();
        if ($changeToken instanceof Outcome) {
            return $this->answer($changeToken);
        }

        $form = $this->createForm(SecurityResetPasswordConfirmType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->answer($this->passwords->change($changeToken, $form->get('plainPassword')->getData()), $this->signingIn($request, $userAuthenticator, $authenticator));
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form->createView(),
            'heading' => '@forms.changePassword.title',
            'description' => '@forms.changePassword.description',
        ]);
    }

    #[Route("/m", name: "security_maintenance")]
    public function Maintenance(): Response
    {
        return $this->answer($this->waiting->maintenance());
    }

    #[Route(["fr" => "/est/bientot/en/ligne", "en" => "/is/coming/soon"], name: "security_launch")]
    public function Launch(): Response
    {
        return $this->answer($this->waiting->launch());
    }

    #[Route(["fr" => "/est/bientot/disponible", "en" => "/is/soon/available"], name: "security_pending")]
    public function Pending(): Response
    {
        return $this->answer($this->waiting->pending());
    }

    #[Route(["fr" => "/en/attente/de/validation", "en" => "/waiting/for/approval"], name: "security_pendingForApproval")]
    #[IsGranted("ROLE_USER")]
    public function PendingForApproval(): Response
    {
        return $this->answer($this->waiting->pendingForApproval());
    }

    /** Symfony's sign-in of an account a rule asks for: the firewall's authenticator, remembered on demand. */
    private function signingIn(Request $request, UserAuthenticatorInterface $userAuthenticator, LoginFormAuthenticator $authenticator): \Closure
    {
        return static function (object $user, bool $remember) use ($request, $userAuthenticator, $authenticator): ?Response {
            $badges = [];
            if ($remember) {
                $badges[] = (new RememberMeBadge())->enable();
            }

            return $userAuthenticator->authenticateUser($user, $authenticator, $request, $badges);
        };
    }
}
