<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Symfony\Auth\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

/**
 * omniauth.yaml's oauth.providers.<name>: the keys the tree declares are the ones a factory reads
 * (omniauth/google's GoogleFactory: client_id, client_secret, redirect_uri, scopes, hosted_domain,
 * type) and the bridge's own (service, the routes); any other key is handed to the factories as it is.
 */
final class AuthConfigurationTest extends TestCase
{
    public function testAProvidersKeysAreTheFactoriesAndAnyOtherIsHandedOn(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'oauth' => ['enabled' => true, 'providers' => [
                'google' => ['client_id' => 'id', 'client_secret' => 'secret'],
                'acme' => ['type' => 'acme', 'client_id' => 'id', 'fields' => ['email' => 'mail'], 'tenant' => 'x'],
            ]],
        ]]);
        $providers = $config['oauth']['providers'];

        foreach (['class', 'options', 'fields'] as $key) {
            self::assertArrayNotHasKey($key, $providers['google'], "$key: read by no factory, not declared");
        }
        self::assertSame(['email' => 'mail'], $providers['acme']['fields'], 'a key of its own factory, handed on');
        self::assertSame('x', $providers['acme']['tenant']);
    }
}
