<?php

namespace Omnibridge\Symfony\Auth\DependencyInjection;

use Omniauth\EnrolledSecondFactors;
use Omniauth\Factor;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * omniauth.yaml (plan "omniauth", §4.1): the methods mounted - each present
 * key turns its method on, a method whose package is missing cannot be -,
 * their settings, the stores (service ids, by default each contract's own
 * name), where the forms post and what their fields are called, the routes
 * of the ceremonies, the throttle and the pages' messages.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('omniauth');
        $root = $tree->getRootNode();
        $root
            ->children()
                ->scalarNode('secret')->defaultValue('%kernel.secret%')->info('Keys the e-mailed codes\' hashes and the trusted devices\' tokens (scheb/2fa signed them with kernel.secret)')->end()
                ->scalarNode('login_route')->defaultValue('security_login')->info('The sign-in page: where a refused or abandoned attempt goes back to')->end()
                ->arrayNode('stores')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('accounts')->defaultValue('Omniauth\Contract\Accounts')->end()
                        ->scalarNode('passwords')->defaultValue('Omniauth\Contract\Passwords')->end()
                        ->scalarNode('link_tokens')->defaultValue('Omniauth\Contract\LinkTokens')->end()
                        ->scalarNode('email_codes')->defaultValue('Omniauth\Contract\EmailCodes')->end()
                        ->scalarNode('backup_codes')->defaultValue('Omniauth\Contract\BackupCodes')->end()
                        ->scalarNode('trusted_version')->defaultValue('Omniauth\Contract\TrustedVersion')->end()
                        ->scalarNode('totp_secrets')->defaultValue('Omniauth\Contract\TotpSecrets')->end()
                        ->scalarNode('identities')->defaultValue('Omniauth\Contract\Identities')->end()
                        ->scalarNode('passkeys')->defaultValue('Omniauth\WebAuthn\Contract\Passkeys')->end()
                        ->scalarNode('sender')->defaultValue('Omniauth\Contract\Sender')->end()
                    ->end()
                ->end()
                ->append($this->password())
                ->arrayNode('link')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('lifetime')->defaultValue(900)->min(60)->end()
                        ->scalarNode('route')->defaultNull()->info('The route of the link\'s page, which signs in when it carries a token')->end()
                        ->scalarNode('parameter')->defaultValue('token')->end()
                    ->end()
                ->end()
                ->arrayNode('email_code')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('digits')->defaultValue(6)->min(4)->max(9)->end()
                        ->integerNode('lifetime')->defaultValue(600)->min(60)->end()
                    ->end()
                ->end()
                ->arrayNode('backup_codes')
                    ->canBeEnabled()
                    ->children()->integerNode('count')->defaultValue(8)->min(1)->end()->end()
                ->end()
                ->arrayNode('trusted_device')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('lifetime')->defaultValue(2592000)->min(60)->end()
                        ->scalarNode('cookie')->defaultValue('trusted_device')->end()
                        ->scalarNode('firewall')->defaultNull()->info('The tokens\' "fwl" claim; null: the firewall of the request')->end()
                    ->end()
                ->end()
                ->arrayNode('totp')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('leeway')->defaultValue(0)->min(0)->end()
                        ->integerNode('period')->defaultValue(30)->min(1)->end()
                        ->integerNode('digits')->defaultValue(6)->min(6)->max(10)->end()
                        ->scalarNode('issuer')->defaultNull()->end()
                        ->scalarNode('server_name')->defaultNull()->end()
                        ->scalarNode('secret_key')->defaultNull()->info('The key the secrets are sealed with at rest (omniauth/otp\'s SodiumSecretBox): base64 of 32 bytes, from the vault or the environment - \'%env(OMNIAUTH_TOTP_KEY)%\' -, never written in the repository; the same on every host of the database. Unset: the secrets are kept as the store has them')->end()
                    ->end()
                ->end()
                ->arrayNode('passkey')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('rp_id')->defaultNull()->end()
                        ->scalarNode('rp_name')->defaultNull()->end()
                        ->arrayNode('origins')->scalarPrototype()->end()->end()
                        ->integerNode('timeout')->defaultNull()->end()
                        ->booleanNode('remember')->defaultTrue()->info('Security\'s remember-me after a passkey (the JSON posted may say otherwise: "remember")')->end()
                        ->scalarNode('options_path')->defaultValue('/login/passkey/options')->end()
                        ->scalarNode('options_route')->defaultValue('passkey_login_options')->end()
                        ->scalarNode('path')->defaultValue('/login/passkey')->end()
                        ->scalarNode('route')->defaultValue('passkey_login')->end()
                    ->end()
                ->end()
                ->append($this->oauth())
                ->arrayNode('second_factor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('after')
                            ->defaultValue(array_map(static fn (Factor $f) => $f->value, EnrolledSecondFactors::AFTER))
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('form_path')->defaultValue('/login/2fa')->end()
                        ->scalarNode('form_route')->defaultValue('2fa_login')->end()
                        ->scalarNode('check_path')->defaultValue('/login/2fa/check')->end()
                        ->scalarNode('check_route')->defaultValue('2fa_login_check')->end()
                        ->scalarNode('template')->defaultValue('security/login_2fa.html.twig')->end()
                        ->scalarNode('code')->defaultValue('_auth_code')->end()
                        ->scalarNode('trusted')->defaultValue('_trusted')->end()
                        ->append($this->csrf('_csrf_token', 'two_factor'))
                    ->end()
                ->end()
                ->arrayNode('throttle')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('pool')->defaultValue('cache.app')->info('A PSR-6 pool (Symfony\'s cache pools are): the tries counted, and the TOTP steps used')->end()
                        ->arrayNode('limits')
                            ->info('Tries per window in seconds, by scope (omniauth\'s CacheThrottle::LIMITS by default): { password: [5, 60] }')
                            ->useAttributeAsKey('scope')
                            ->arrayPrototype()->integerPrototype()->end()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('messages')
                    ->info('The message of a failure, or of "failure:reason" ("%reason%" replaced), as the page translates it')
                    ->useAttributeAsKey('failure')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $tree;
    }

    private function password(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('password'))->getRootNode();
        $node
            ->canBeDisabled()
            ->children()
                ->arrayNode('routes')->info('The routes a sign-in form posts to')->defaultValue(['security_login'])->scalarPrototype()->end()->end()
                ->scalarNode('form')->defaultNull()->info('The form\'s name, its fields read under it; null: at the top of the request')->end()
                ->scalarNode('identifier')->defaultValue('_username')->end()
                ->scalarNode('password')->defaultValue('_password')->end()
                ->scalarNode('remember')->defaultValue('_remember_me')->end()
                ->scalarNode('target_path_parameter')->defaultValue('_target_path')->end()
                ->scalarNode('default_target_path')->defaultValue('/')->end()
                ->scalarNode('user_class')->defaultNull()->info('The class whose hasher security.yaml sets (password_hashers): the same hasher checks the passwords')->end()
                ->append($this->csrf('_csrf_token', 'authenticate'))
            ->end();

        return $node;
    }

    private function oauth(): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('oauth'))->getRootNode();
        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('link_by_email')->defaultNull()->info('Link an account by an address its provider vouches for: true, false, or a service (omniauth\'s EmailLinking); null: never one with a second factor')->end()
                ->scalarNode('sign_up_route')->defaultNull()->info('Where an identity no account is linked to goes (its PendingIdentity in the session); null: the sign-in page')->end()
                ->arrayNode('providers')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->ignoreExtraKeys(false)
                        ->children()
                            ->scalarNode('client_id')->defaultValue('')->end()
                            ->scalarNode('client_secret')->defaultValue('')->end()
                            ->scalarNode('redirect_uri')->defaultNull()->info('An absolute address, or a path each trip\'s host completes; null: the path of check_route')->end()
                            ->arrayNode('scopes')->scalarPrototype()->end()->end()
                            ->scalarNode('hosted_domain')->defaultNull()->end()
                            ->scalarNode('type')->defaultNull()->end()
                            ->scalarNode('service')->defaultNull()->info('A service that is the provider (omniauth\'s Provider), in place of the settings above')->end()
                            ->scalarNode('start_path')->defaultNull()->end()
                            ->scalarNode('start_route')->defaultNull()->end()
                            ->scalarNode('check_path')->defaultNull()->end()
                            ->scalarNode('check_route')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private function csrf(string $field, string $intent): ArrayNodeDefinition
    {
        $node = (new TreeBuilder('csrf'))->getRootNode();
        $node
            ->canBeDisabled()
            ->children()
                ->scalarNode('field')->defaultValue($field)->end()
                ->scalarNode('intent')->defaultValue($intent)->end()
            ->end();

        return $node;
    }
}
