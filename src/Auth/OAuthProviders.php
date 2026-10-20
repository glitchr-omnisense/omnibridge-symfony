<?php

namespace Omnibridge\Symfony\Auth;

use Omniauth\OAuth\Provider\Provider;
use Omniauth\OAuth\Provider\Providers;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * omniauth/oauth's providers from omniauth.yaml: Providers::fromConfig()
 * (a provider without client id is not served), each one's return address
 * the absolute address of its check route unless it sets one, and a
 * provider given as a service ("service") as it is.
 */
final class OAuthProviders
{
    /** @param array<string, array<string, mixed>> $config omniauth.yaml's oauth.providers */
    public static function create(array $config, UrlGeneratorInterface $urls, ContainerInterface $services, ?ClockInterface $clock = null): Providers
    {
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
            $entry['redirect_uri'] ??= $urls->generate($entry['check_route'], [], UrlGeneratorInterface::ABSOLUTE_URL);
            $settings[$name] = array_filter($entry, static fn ($v) => null !== $v && [] !== $v);
        }

        return new Providers(...$given, ...iterator_to_array(Providers::fromConfig($settings, $clock), false));
    }
}
