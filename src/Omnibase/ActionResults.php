<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Screen\File;
use Base\Screen\Redirect;
use Base\Screen\Json;
use Base\Screen\Shell;
use Base\Screen\Template;
use Base\Screen\View;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * An action's result answered under Symfony (step R27 of the audit
 * "lesringostars face au master plan"): an action written once for every
 * host - a plain class whose #[Route] Symfony's router reads, as
 * omnibridge/laravel's Routing\ActionRoutes does - returns a View, a
 * Template, Json, a Shell, a File or a Redirect, and the ScreenResponder answers it;
 * null from an action declared to return one of them is no such page (404).
 * A controller that answers a Response itself is left alone.
 */
final class ActionResults
{
    private const RESULTS = [View::class, Template::class, Json::class, Shell::class, File::class, Redirect::class];

    public function __construct(private readonly ScreenResponder $responder)
    {
    }

    public function __invoke(ViewEvent $event): void
    {
        $result = $event->getControllerResult();
        foreach (self::RESULTS as $class) {
            if ($result instanceof $class) {
                $event->setResponse($this->responder->respond($event->getRequest(), $result));

                return;
            }
        }

        if (null === $result && self::answers($event->getRequest()->attributes->get('_controller'))) {
            throw new NotFoundHttpException();
        }
    }

    /** Whether a controller ("Class", "Class::method", [Class, method]) is declared to return an action's result. */
    public static function answers(mixed $controller): bool
    {
        if (\is_array($controller)) {
            $controller = (\is_object($controller[0] ?? null) ? $controller[0]::class : (string) ($controller[0] ?? '')).'::'.($controller[1] ?? '__invoke');
        }
        if (!\is_string($controller) || '' === $controller) {
            return false;
        }
        [$class, $method] = explode('::', $controller, 2) + [1 => '__invoke'];
        if (!method_exists($class, $method)) {
            return false;
        }

        $type = (new \ReflectionMethod($class, $method))->getReturnType();
        $types = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];
        foreach ($types as $each) {
            if ($each instanceof \ReflectionNamedType && !$each->isBuiltin()) {
                foreach (self::RESULTS as $result) {
                    if (is_a($each->getName(), $result, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
