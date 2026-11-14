<?php

namespace Omnibridge\Symfony\Omnibase\Subscriber;

use Omnibridge\Symfony\Omnibase\BaseBundle;
use Base\Entity\User\Notification;
use Base\Request\Rule\Integrity;
use Omnibridge\Symfony\Omnibase\Legacy\Subscriber\IntegritySubscriber as Legacy;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

if (!class_exists(Integrity::class)) {
    // omnisense/omnibase 4.x: the rule is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    class IntegritySubscriber extends Legacy
    {
    }
} else {
    /**
     * The session's integrity on Symfony's kernel request, decided by omnibase's Integrity rule (step F5e of its
     * plan, slice 2). Registered by no configuration, on purpose, as before: it opens a session on every main
     * request, runs `bin/console cache:clear` inside a web request on a Doctrine TypeError, and signs the user
     * out whenever a check fails. The account's own check (its notifications a live Doctrine collection) is
     * Doctrine's, so this host's.
     */
    class IntegritySubscriber extends Legacy
    {
        public function onKernelRequest(RequestEvent $event)
        {
            if (!$this->router->isMainApplication()) {
                return;
            }
            if (BaseBundle::getInstance()->isInvalid() && $event->isMainRequest()) {
                $this->clearProcess->mustRun();
                throw new \RuntimeException('Application integrity compromised, maybe cache needs to be refreshed ?');
            }
            $rule = new Integrity($this->secret, $this->vault);
            $session = $this->requestStack->getSession();
            if (!$session->get(Integrity::SESSION_DATABASE)) {
                $session->set(Integrity::SESSION_DATABASE, $this->getDoctrineChecksum());
            }
            if (!$session->get(Integrity::SESSION_SECRET)) {
                $session->set(Integrity::SESSION_SECRET, $rule->seal());
            }
            if (!$this->router->hasFirewall()) {
                return;
            }
            $user = $this->tokenStorage->getToken()?->getUser();
            $intact = $this->checkUserIntegrity() && $rule->secretIntact($session->get(Integrity::SESSION_SECRET))
                && Integrity::databaseIntact(null !== $user, $session->get(Integrity::SESSION_DATABASE), $this->getDoctrineChecksum());
            $decision = $rule->decide($intact, $user, $this->router->getRouteName());
            if ($decision->isContinue()) {
                return;
            }
            foreach ($decision->notices as [$key, $parameters, $importance]) {
                (new Notification($key, $parameters))->send($importance);
            }
            $this->referrer->clear();
            $this->tokenStorage->setToken(null);
            $session->remove(Integrity::SESSION_SECRET);
            $session->remove(Integrity::SESSION_DATABASE);
            $cleared = new Response();
            foreach ($decision->cookies as [$name]) {
                $cleared->headers->clearCookie($name);
                $cleared->headers->clearCookie($name, '/', $this->router->getDomain());
            }
            $cleared->sendHeaders();
            $event->setResponse($this->router->redirectToRoute($decision->route));
            $event->stopPropagation();
        }

        protected function getDoctrineChecksum()
        {
            $doctrine = $this->doctrine;

            return Integrity::checksum($doctrine->getConnection($doctrine->getDefaultConnectionName())->getParams());
        }
    }
}
