<?php

namespace Base\Subscriber;

use App\Entity\User;
use Base\Entity\User as BaseUser;
use Base\Repository\Users;
use Base\Request\RouterPage;
use Base\Request\Rule\Analytics;
use Base\Service\TranslatorInterface;
use Google\Service\GaService;
use Base\Routing\AdvancedRouterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 * The back office's figures on Symfony's kernel request, answered by omnibase's Analytics rule (step F5e of
 * its plan, slice 2) and put in Twig's globals: the accounts online and active, then Google Analytics'
 * basics where its service is set up (a remote API: the back office's main requests only).
 */
class AnalyticsSubscriber implements EventSubscriberInterface
{
    /**
     * @var TokenStorageInterface
     */
    protected TokenStorageInterface $tokenStorage;
    /**
     * @var AdvancedRouterInterface
     */
    protected AdvancedRouterInterface $router;
    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;
    /**
     * @var Environment
     */
    protected Environment $twig;
    /**
     * @var Users
     */
    protected Users $userRepository;
    /**
     * @var ?GaService
     */
    protected ?GaService $gaService;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AdvancedRouterInterface       $router,
        TranslatorInterface   $translator,
        Environment           $twig,
        Users                 $userRepository,
        ?GaService            $googleAnalyticsService = null
    )
    {
        $this->tokenStorage = $tokenStorage;
        $this->router = $router;

        $this->twig = $twig;
        $this->translator = $translator;

        $this->userRepository = $userRepository;

        $this->gaService = $googleAnalyticsService;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => [['onUserRequest', 8], ['onGoogleAnalyticsRequest', 7]]];
    }

    public function onUserRequest(RequestEvent $event)
    {
        if (is_instanceof(User::class, BaseUser::class) && Analytics::applies(new RouterPage($this->router), $event->isMainRequest())) {
            foreach ($this->rule()->users($this->tokenStorage->getToken()?->getUser(), User::getOnlineDelay(), $this->twig->getGlobals()) as $name => $value) {
                $this->twig->addGlobal($name, $value);
            }
        }
    }

    public function onGoogleAnalyticsRequest(RequestEvent $event)
    {
        if (Analytics::applies(new RouterPage($this->router), $event->isMainRequest()) && isset($this->gaService) && $this->gaService->isEnabled()) {
            $this->twig->addGlobal('user_analytics', $this->rule()->google($this->gaService->getBasics(), $this->twig->getGlobals()));
        }
    }

    private function rule(): Analytics
    {
        return new Analytics($this->userRepository, $this->translator);
    }
}
