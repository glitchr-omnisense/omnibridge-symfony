<?php

/*
 * omniauth on omnibase's tables (plan "omniauth", §2.2, steps AU4 and AU7):
 * where omnisense/omniauth is installed (it is only suggested), each of its
 * stores bound by its contract's name to Base\Auth\'s - the accounts, the
 * passwords, the sign-in links, the e-mailed codes, the backup codes, the
 * trusted devices' version, the identities at providers, the TOTP secrets
 * and the passkeys where their packages are -, the e-mails sent by omnibase's
 * Notifier (NotifierSender), Base\Auth\Policy around omniauth's second-factor
 * rule, PolicyLinking for omniauth's address linking, and the
 * listeners of omniauth's events. omnibridge/symfony's OmniauthExtension
 * (config/packages/omniauth.yaml) reads them; without that file they are
 * never used, and without omniauth none of this is declared.
 */

use Base\Auth\Listener\TrackConnections;
use Base\Auth\Listener\UpdateAccount;
use Base\Auth\Listener\WelcomeBack;
use Base\Auth\NotifierSender;
use Base\Auth\Policy;
use Base\Auth\PolicyLinking;
use Base\Auth\UserAccounts;
use Base\Auth\UserBackupCodes;
use Base\Auth\UserEmailCodes;
use Base\Auth\UserIdentities;
use Base\Auth\UserLinkTokens;
use Base\Auth\UserPasskeys;
use Base\Auth\UserPasswords;
use Base\Auth\UserTotpSecrets;
use Base\Auth\UserTrustedVersion;
use Base\Bridge\Symfony\AuthListeners;
use Base\Repository\User\ConnectionRepository;
use Base\Security\SignInGuard;
use Base\Security\UserTracker;
use Base\Service\SecurityPolicy;
use Omniauth\Contract\Accounts;
use Omniauth\Contract\BackupCodes;
use Omniauth\Contract\EmailCodes;
use Omniauth\Contract\Identities;
use Omniauth\Contract\LinkTokens;
use Omniauth\Contract\Passwords;
use Omniauth\Contract\SecondFactorPolicy;
use Omniauth\Contract\Sender;
use Omniauth\Contract\TotpSecrets;
use Omniauth\Contract\TrustedVersion;
use Omniauth\Event\AccountRefused;
use Omniauth\Event\Revoking;
use Omniauth\Event\SignedIn;
use Omniauth\Event\SignInFailed;
use Omniauth\Event\Verifying;
use Omniauth\OAuth\NoSecondFactorLinking;
use Omniauth\WebAuthn\Contract\Passkeys;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    if (!interface_exists(Accounts::class)) {
        return;
    }
    $services = $container->services();
    // The unit of work, Doctrine's under Symfony (plan "omnibase agnostic", step F2d).
    $unitOfWork = service(\Omnibridge\Persistence\UnitOfWork::class);

    $stores = [
        UserAccounts::class => Accounts::class,
        UserPasswords::class => Passwords::class,
        UserLinkTokens::class => LinkTokens::class,
        UserEmailCodes::class => EmailCodes::class,
        UserBackupCodes::class => BackupCodes::class,
        UserTrustedVersion::class => TrustedVersion::class,
        UserIdentities::class => Identities::class,
        UserTotpSecrets::class => TotpSecrets::class,
    ];
    if (interface_exists(Passkeys::class)) {
        $stores[UserPasskeys::class] = Passkeys::class;
    }
    foreach ($stores as $class => $contract) {
        $services->set($class)->args([$unitOfWork]);
        $services->alias($contract, $class);
    }

    $services->set(NotifierSender::class)->args([service('Base\Notifier\Notifier')]);
    $services->alias(Sender::class, NotifierSender::class);

    // omnibase's rule around omniauth's (EnrolledSecondFactors, omnibridge/symfony's omniauth.policy.default).
    $services->set(Policy::class)
        ->decorate(SecondFactorPolicy::class, null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([service(SecurityPolicy::class), service('.inner')])
        ->tag('kernel.event_listener', ['event' => Revoking::class, 'method' => 'onRevoking']);
    if (class_exists(NoSecondFactorLinking::class)) {
        $services->set(PolicyLinking::class)->args([
            service(Policy::class),
            inline_service(NoSecondFactorLinking::class)->args([service('omniauth.second_factors')]),
        ]);
    }

    // The listeners of omniauth's events. TrackConnections before WarnNewDevice, which looks for the connection a sign-in made.
    $services->set(TrackConnections::class)
        ->args([service(UserTracker::class), $unitOfWork, service(Accounts::class)])
        ->tag('kernel.event_listener', ['event' => SignedIn::class, 'method' => 'onSignedIn', 'priority' => 16])
        ->tag('kernel.event_listener', ['event' => SignInFailed::class, 'method' => 'onSignInFailed']);
    $services->set('Base\Auth\Listener\WarnNewDevice')
        ->factory([AuthListeners::class, 'warnNewDevice'])
        ->args([service(SecurityPolicy::class), service(UserTracker::class), service(ConnectionRepository::class), service('request_stack'), service('Omniauth\Contract\State')->nullOnInvalid()])
        ->tag('kernel.event_listener', ['event' => SignedIn::class, 'method' => 'onSignedIn', 'priority' => 8]);
    $services->set(UpdateAccount::class)
        ->args([$unitOfWork])
        ->tag('kernel.event_listener', ['event' => SignedIn::class, 'method' => 'onSignedIn']);
    $services->set(WelcomeBack::class)
        ->args([$unitOfWork, service('Base\Notifier\Notifier')])
        ->tag('kernel.event_listener', ['event' => AccountRefused::class, 'method' => 'onAccountRefused']);
    $services->set('Base\Auth\Listener\GuardSignIn')
        ->factory([AuthListeners::class, 'guardSignIn'])
        ->args([service(SignInGuard::class), service('request_stack')])
        ->tag('kernel.event_listener', ['event' => Verifying::class, 'method' => 'onVerifying'])
        ->tag('kernel.event_listener', ['event' => SignInFailed::class, 'method' => 'onSignInFailed'])
        ->tag('kernel.event_listener', ['event' => SignedIn::class, 'method' => 'onSignedIn']);
};
