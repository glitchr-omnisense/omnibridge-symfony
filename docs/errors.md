---
title: Errors
order: 50
---

# Errors

## At compile time

| Error | When |
|---|---|
| `LogicException` `omniauth.yaml turns "<key>" on, which needs <package>: composer require <package>.` | `totp` without omniauth/otp, `passkey` without omniauth/webauthn, `oauth` without omniauth's `Omniauth\OAuth\` |
| `InvalidConfigurationException` `omnimap.cache needs a drive ... or a storage ...` | `omnimap.cache` with neither `drive` nor `storage` |
| `InvalidConfigurationException` (Symfony's) | `omnimap.provider` missing or empty, a value out of range (`email_code.digits` 4 to 9, `totp.digits` 6 to 10, `cache.scale` 1 or 2, a lifetime under 60...), `cache.format` not one of the four |
| none | an adapter whose framework service is missing: removed by `ContractsPass`, the container compiles without that contract |

## At run time

| Exception | Thrown by | When |
|---|---|---|
| `Omnibridge\Exception\MissingContractException` | `Omnibridge\Runtime` | a contract asked that this container does not bind (no SecurityBundle: `Access`, `Sessions`, `Tokens`; `Locales`, `Files`, `Routes` unless the application or omnibase binds them) |
| Symfony's `RouteNotFoundException` | `RouterUrls`, and every place that generates a configured route | a route name the router does not know (`login_route`, `second_factor.form_route`, `oauth.sign_up_route`...) |
| `Omnibridge\Symfony\Auth\Exception\SignInRefused` | `OmniauthAuthenticator` | every refused sign-in: a `CustomUserMessageAuthenticationException` with `factor`, `failure` (omniauth's `Failure` or null), `reason` (`csrf`, `identity-unknown`, omniauth's), `identity` (an unknown provider identity); the message is the page's (`messages`). Security hands it to `onAuthenticationFailure()`, never to the application |
| `LogicException` | `OmniauthAuthenticator::authenticate()` | omniauth's account is not a Symfony `UserInterface` |
| `LogicException` | `Contexts::of()` | no request to sign in from |
| `LogicException` | `CeremonyController::secondFactor()` | no TwigBundle ("The second factor's page is a Twig template..."); a second factor asked with none offered |
| `NotFoundHttpException` | `CeremonyController::oauthStart()` | no identity provider served, or an unknown provider (404) |
| `InvalidArgumentException` | `OAuthProviders::create()` | a provider's `service` is not an omniauth `Provider` |
| `InvalidArgumentException` | `PendingToken` | an empty firewall name; an attribute asked that the token does not hold |
| `NotFoundHttpException` | `SnapshotController::tile()` | no such tile (404) |
| `BadRequestHttpException` | `SnapshotController::export()`, `suppress()` | no picture sent, an invalid picture or signature (400, omnimap's message) |
| `AccessDeniedHttpException` | `SnapshotController::export()`, `suppress()` | the visitor lacks `control`, or no `Access` is bound; an invalid token (403) |
