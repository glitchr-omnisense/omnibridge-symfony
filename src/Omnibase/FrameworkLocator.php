<?php

namespace Omnibridge\Symfony\Omnibase;

use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Omnibridge\Doctrine\Omnibase\DoctrineLocator;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\BaseService;
use Base\Service\Locator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Environment;

/**
 * The framework's services BaseTrait handed out before it named contracts
 * only (plan "omnibase agnostic", step F2f): Doctrine's registry and entity
 * manager, the request stack, the token storage, the firewall map, Twig, the
 * slugger, omnibase's router and the BaseService facade, resolved as before
 * through the `base.runtime` locator BaseBundle::boot() seeds and kept on
 * Base\Service\Locator. Each answers null where the host has no such service
 * (a command without a request, a host without Doctrine, Laravel).
 *
 * For the core's host code - BaseService, the validators, the controllers,
 * Notification's Twig until it renders through Omnibridge\Contract\Views
 * (F5a) - and for Base\Traits\FrameworkAccessorsTrait, the deprecated
 * accessors that still reach these through BaseTrait. Its Doctrine half is
 * omnibridge/doctrine's Omnibridge\Doctrine\Omnibase\DoctrineLocator since step F3
 * (the methods here call it); its Symfony half moves with the rest of the host
 * code at F5c.
 */
final class FrameworkLocator
{
    public static function service(): ?BaseService
    {
        return Locator::runtimeGet('instance', 'base.service');
    }

    /** Doctrine's registry: Omnibridge\Doctrine\Omnibase\DoctrineLocator's (step F3), as each of the four below. */
    public static function doctrine(): ?ManagerRegistry
    {
        return DoctrineLocator::doctrine();
    }

    public static function objectManager(object|string $entity): ?ObjectManager
    {
        return DoctrineLocator::objectManager($entity);
    }

    /** The default entity manager; a closed one is null, or recreated when $reopen. */
    public static function entityManager(bool $reopen = false): ?EntityManagerInterface
    {
        return DoctrineLocator::entityManager($reopen);
    }

    public static function repository(object|string $entity): ?ObjectRepository
    {
        return DoctrineLocator::repository($entity);
    }

    public static function classMetadataManipulator(): ?ClassMetadataManipulator
    {
        return DoctrineLocator::classMetadataManipulator();
    }

    public static function tokenStorage(): ?TokenStorageInterface
    {
        return Locator::runtimeGet('tokenStorage', 'security.token_storage');
    }

    public static function requestStack(): ?RequestStack
    {
        return Locator::runtimeGet('requestStack', 'request_stack');
    }

    public static function firewallMap(): ?FirewallMapInterface
    {
        return Locator::runtimeGet('firewallMap', 'security.firewall.map');
    }

    public static function twig(): ?Environment
    {
        return Locator::runtimeGet('twig', 'twig');
    }

    public static function slugger(): ?SluggerInterface
    {
        return Locator::runtimeGet('slugger', 'slugger');
    }

    public static function router(): ?AdvancedRouterInterface
    {
        return Locator::runtimeGet('router', 'advanced_router');
    }
}
