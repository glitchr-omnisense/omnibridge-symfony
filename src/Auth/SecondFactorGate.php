<?php

namespace Omnibridge\Symfony\Auth;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * Between two factors, the second factor's page and nothing else: the visitor
 * holding a PendingToken is sent there from any page, as scheb/2fa's
 * TwoFactorAccessListener and its redirection did - but a page access_control
 * opens to everybody (PUBLIC_ACCESS) or to that token (IS_AUTHENTICATED_2FA_IN_PROGRESS),
 * and the sign-out.
 *
 * Twice: after the firewall (a page access_control does not guard, which
 * Security lets through), and when Security refused a page (the access
 * denied, which would otherwise start a sign-in from scratch).
 */
final class SecondFactorGate implements EventSubscriberInterface
{
    /** @param list<string> $routes the second factor's own routes (its page, its check) */
    public function __construct(
        private readonly TokenStorageInterface $tokens,
        private readonly UrlGeneratorInterface $urls,
        private readonly string $formRoute,
        private readonly array $routes = [],
        private readonly ?AccessMapInterface $accessMap = null,
        private readonly ?AccessDecisionManagerInterface $decisions = null,
        private readonly ?LogoutUrlGenerator $logout = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After the firewall (8).
            KernelEvents::REQUEST => ['onRequest', 7],
            // Before the firewall's exception listener (1).
            KernelEvents::EXCEPTION => ['onException', 2],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }
        $token = $this->tokens->getToken();
        if ($token instanceof PendingToken && !$this->isAccessible($event->getRequest(), $token)) {
            $event->setResponse(new RedirectResponse($this->urls->generate($this->formRoute)));
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $exception = $event->getThrowable();
        do {
            if ($exception instanceof AccessDeniedException) {
                break;
            }
        } while (null !== $exception = $exception->getPrevious());
        if (null === $exception || !$this->tokens->getToken() instanceof PendingToken) {
            return;
        }
        $event->setResponse(new RedirectResponse($this->urls->generate($this->formRoute)));
    }

    private function isAccessible(Request $request, PendingToken $token): bool
    {
        if (\in_array($request->attributes->get('_route'), $this->routes, true)) {
            return true;
        }
        if (null !== $this->accessMap) {
            [$attributes] = $this->accessMap->getPatterns($request);
            if ([AuthenticatedVoter::PUBLIC_ACCESS] === $attributes) {
                return true;
            }
            if (null !== $attributes && [] !== $attributes && null !== $this->decisions && $this->decisions->decide($token, $attributes, $request)) {
                return true;
            }
        }
        if (null !== $this->logout) {
            try {
                $path = (string) strtok($this->logout->getLogoutPath(), '?');
                $base = $request->getBaseUrl();
                if ('' !== $base && str_starts_with($path, $base)) {
                    $path = substr($path, \strlen($base)) ?: '/';
                }
                if ($path === $request->getPathInfo()) {
                    return true;
                }
            } catch (\InvalidArgumentException) {
                // No sign-out on this firewall.
            }
        }

        return false;
    }
}
