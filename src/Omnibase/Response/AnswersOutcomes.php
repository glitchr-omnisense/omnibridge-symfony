<?php

namespace Omnibridge\Symfony\Omnibase\Response;

use Base\Auth\Account\Outcome;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * An account page's Outcome (omnibase's Base\Auth\Account rules, plan "omnibase agnostic", step F5e slice 5)
 * answered by an AbstractController under Symfony, as the controller answered before: a route by
 * redirectToRoute(), an address by redirect(), the index by its route or its address (AdvancedRouter's), the
 * page the visitor was going to by the referrer, a page by render(), a refusal by Security's exception, no
 * such page by a 404; the account signed in first through $signIn(user, remember) - the host's authenticator -,
 * whose response is the answer where the rule asks nothing more.
 *
 * A controller answering the index or the page the visitor was going to has its AdvancedRouterInterface as
 * $this->router and its ReferrerInterface as $this->referrer.
 */
trait AnswersOutcomes
{
    /** @param (\Closure(object, bool): ?Response)|null $signIn */
    protected function answer(Outcome $outcome, ?\Closure $signIn = null): Response
    {
        $signed = null !== $outcome->signIn && null !== $signIn ? $signIn($outcome->signIn, $outcome->remember) : null;

        return match ($outcome->kind) {
            Outcome::ROUTE => $this->redirectToRoute((string) $outcome->route, $outcome->parameters),
            Outcome::URL => $this->redirect((string) $outcome->url),
            Outcome::INDEX => $this->redirectToRoute($this->router->getRouteIndex()),
            Outcome::HOME => $this->redirect($this->router->getUrlIndex()),
            Outcome::BACK => $this->redirect($this->referrer->getUrl() ?? $this->router->getUrlIndex()),
            Outcome::PAGE => $this->render($outcome->page->template, $outcome->page->data, new Response(null, $outcome->page->status)),
            Outcome::SIGNED_IN => $signed ?? $this->redirectToRoute($this->router->getRouteIndex()),
            Outcome::DENIED => throw new AccessDeniedException($outcome->reason ?? 'Access Denied.'),
            default => throw new NotFoundHttpException($outcome->reason ?? 'Not Found'),
        };
    }
}
