<?php

namespace Base\Subscriber;

use Base\Attributes\Attribute\IsGranted;
use Base\Attributes\AttributeReader;
use LogicException;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


use function array_key_exists;
use function count;
use function is_array;

class IsGrantedSubscriber implements EventSubscriberInterface
{
    /**
     * @var AuthorizationCheckerInterface|null
     */
    private ?AuthorizationCheckerInterface $authorizationChecker;

    /**
     * @var AttributeReader
     */
    private AttributeReader $attributeReader;

    public function __construct(AttributeReader $attributeReader, ?AuthorizationCheckerInterface $authorizationChecker = null)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->attributeReader = $attributeReader;
    }

    public function onKernelControllerArguments(KernelEvent $event)
    {
        $request = $event->getRequest();

        $controller = $request->attributes->get("_controller");
        $array = is_array($controller) ? $controller : explode("::", $controller ?? "");
        $class = $array[0] ?? null;
        // An invokable controller (an action, "Class") is its __invoke.
        $method = $array[1] ?? "__invoke";

        if (!class_exists($class)) {
            return;
        }

        $configurations = array_merge(
            $this->attributeReader->getClassAttributes($class, IsGranted::class),
            $this->attributeReader->getMethodAttributes($class, IsGranted::class)[$method] ?? []
        );

        if (null === $this->authorizationChecker) {
            throw new LogicException('To use the @IsGranted tag, you need to install symfony/security-bundle and configure your security system.');
        }

        $arguments = $request->attributes->get("_route_parameters");

        foreach ($configurations as $configuration) {

            $subjectRef = $configuration->getSubject();
            $subject = null;

            if ($subjectRef) {
                if (is_array($subjectRef)) {
                    foreach ($subjectRef as $ref) {
                        if (!array_key_exists($ref, $arguments)) {
                            throw $this->createMissingSubjectException($ref);
                        }

                        $subject[$ref] = $arguments[$ref];
                    }
                } else {
                    if (!array_key_exists($subjectRef, $arguments)) {
                        throw $this->createMissingSubjectException($subjectRef);
                    }

                    $subject = $arguments[$subjectRef];
                }
            }

            $attributes = (array) $configuration->getAttributes();
            if(!$attributes) {
                $argsString = $this->getIsGrantedString($configuration);
                throw new RuntimeException(sprintf('The @IsGranted attribute on "%s::%s" must have at least one attribute. Try adding @IsGranted(%s).', $class, $method, $argsString));
            }

            foreach ($attributes as $attribute) {

                if (!$this->authorizationChecker->isGranted($attribute, $subject)) {
                    $argsString = $this->getIsGrantedString($configuration);

                    $message = $configuration->getMessage() ?: sprintf('Access Denied by controller attribute @IsGranted(%s)', $argsString);

                    if ($statusCode = $configuration->getStatusCode()) {
                        throw new HttpException($statusCode, $message);
                    }

                    $accessDeniedException = new AccessDeniedException($message);
                    $accessDeniedException->setAttributes($attributes);
                    $accessDeniedException->setSubject($subject);

                    throw $accessDeniedException;
                }
            }
        }
    }

    /**
     * @param string $subject
     * @return RuntimeException
     */
    private function createMissingSubjectException(string $subject)
    {
        return new RuntimeException(sprintf('Could not find the subject "%s" for the @IsGranted attribute. Try adding a "$%s" argument to your controller method.', $subject, $subject));
    }

    /**
     * @param IsGranted $isGranted
     * @return false|mixed|string
     */
    private function getIsGrantedString(IsGranted $isGranted)
    {
        $attributes = array_map(function ($attribute) {
            return sprintf('"%s"', $attribute);
        }, (array)$isGranted->getAttributes());
        if (1 === count($attributes)) {
            $argsString = reset($attributes);
        } else {
            $argsString = sprintf('[%s]', implode(', ', $attributes));
        }

        if (null !== $isGranted->getSubject()) {
            $argsString = sprintf('%s, %s', $argsString, $isGranted->getSubject());
        }

        return $argsString;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments'];
    }
}
