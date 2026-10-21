<?php

namespace Omnibridge\Symfony\Auth;

use Omniauth\OAuth\Provider\Provider;
use Omniauth\OAuth\Provider\ProviderFactory;
use Omniauth\OAuth\Provider\Providers;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * omniauth/oauth's providers from omniauth.yaml: Providers::configured()
 * through the provider packages' factories (omniauth/google's GoogleFactory;
 * a provider without client id is not served), and a provider given as a
 * service ("service") as it is.
 *
 * Each one's return address is, unless it sets one, the PATH of its check
 * route - never an absolute address made here: this service is built once
 * per worker (FrankenPHP keeps it between requests), so an absolute address
 * would stay on the host of the first request. omniauth completes the path
 * with the origin of each trip's request (Authorization::returnAddress()),
 * and the exchange repeats that address.
 */
final class OAuthProviders
{
    /**
     * @param array<string, array<string, mixed>>      $config    omniauth.yaml's oauth.providers
     * @param iterable<ProviderFactory>|ContainerInterface $factories the provider packages' (tag omniauth.oauth_provider_factory):
     *                                                            a service locator of them (the extension's: a tagged
     *                                                            iterator inlined by a compiled container lost its
     *                                                            factory and passed the factory's own argument), or
     *                                                            the factories themselves
     */
    public static function create(array $config, UrlGeneratorInterface $urls, ContainerInterface $services, iterable|ContainerInterface $factories = []): Providers
    {
        if ($factories instanceof ContainerInterface) {
            $located = [];
            foreach ($factories instanceof ServiceProviderInterface ? array_keys($factories->getProvidedServices()) : [] as $id) {
                $located[] = $factories->get($id);
            }
            $factories = $located;
        }
        $given = [];
        $settings = [];
        foreach ($config as $name => $entry) {
            if (null !== ($entry['service'] ?? null)) {
                $provider = $services->get($entry['service']);
                if (!$provider instanceof Provider) {
                    throw new \InvalidArgumentException(\sprintf('The "%s" provider\'s service is no %s.', $name, Provider::class));
                }
                $given[] = $provider;
                continue;
            }
            if ('' === (string) ($entry['client_id'] ?? '')) {
                continue;
            }
            $entry['redirect_uri'] ??= $urls->generate($entry['check_route'], [], UrlGeneratorInterface::ABSOLUTE_PATH);
            $settings[$name] = array_filter($entry, static fn ($v) => null !== $v && [] !== $v);
        }

        $made = [];
        foreach ($factories as $factory) {
            if ($factory instanceof ProviderFactory) {
                $made[] = $factory;
            }
        }

        return new Providers(...$given, ...iterator_to_array(Providers::configured($settings, ...$made), false));
    }
}
