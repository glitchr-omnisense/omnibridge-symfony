<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass;

// For omnisense/omnibase 4.x (a core without Base\Discovery): BaseBundle registers it in place of DiscoveryPass, which
// compiles the core's declaration of the same registry since step F5e slice 4c. Removed once 4.x is no longer served.

use Base\Contract\Media;
use Base\Contract\Nouns;
use Base\Contract\Pictures;
use Base\Contract\Texts;
use Base\Service\ParameterBagInterface;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Environment;
use Omnibridge\Contract\Files;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Requests;
use Omnibridge\Contract\Roles;
use Omnibridge\Contract\Routes;
use Omnibridge\Contract\Sessions;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Views;
use Omnibridge\Symfony\DependencyInjection\ContractsPass as HostContractsPass;
use Base\Field\Search\RemoteSearch;
use Base\Field\Editor\EditorJs;
use Base\Field\Upload\Dropzone;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mailer\MailerInterface;

/**
 * The locator Base\Contract\Runtime reaches the contracts of the Foundation
 * through (plan "omnibase agnostique", C4), handed to it by
 * BaseBundle::boot() - omnisense/omnibridge's registry since A2, the host's
 * contracts (Omnibridge\Contract\*) with omnibase's own (Nouns, Media,
 * Pictures, Texts, the parameters) and the mailer: each contract by its
 * interface's name, the service the container binds to that name -
 * omnibridge/symfony's adapters and omnibase's (config/services/core.php),
 * the framework's clock, event dispatcher and mailer, or an application's own
 * binding, which wins as any alias does.
 *
 * The building itself is omnibridge/symfony's since A1
 * (Omnibridge\Symfony\DependencyInjection\ContractsPass: only the contracts
 * whose service exists are listed, an adapter of omnibridge/symfony whose
 * framework service is missing removed, a locator of closures); this pass
 * gives it omnibase's list, its former names and its service id.
 */
final class ContractsPass implements CompilerPassInterface
{
    public const SERVICE = 'base.contracts';

    /** @var list<class-string> */
    public const CONTRACTS = [
        Urls::class,
        Routes::class,
        Environment::class,
        ParameterBagInterface::class,
        Translations::class,
        Nouns::class,
        Access::class,
        Sessions::class,
        Locales::class,
        Files::class,
        Pictures::class,
        Media::class,
        Texts::class,
        Tokens::class,
        Requests::class,
        Roles::class,
        Views::class,
        Validation::class,
        ClockInterface::class,
        EventDispatcherInterface::class,
        MailerInterface::class,
        // A select's remote search (plan "omnibase agnostic", F5d): SelectType hands it its payload, as FormView does elsewhere.
        RemoteSearch::class,
        // A dropzone's uploads (F5d, slice 4): FileType hands its payload, as the Upload behaviour does elsewhere.
        Dropzone::class,
        // An editor's routes (F5d, slice 6): EditorType hands their payload and its pictures' addresses, as the Editor behaviour does elsewhere.
        EditorJs::class,
    ];

    /**
     * The former names of the contracts that moved to omnisense/omnibridge
     * (A2): none since omnibase 4's final pass, which removed them (the sites
     * and bundles name Omnibridge\Contract\*). Kept for HostContractsPass's
     * signature, empty.
     *
     * @var array<string, class-string>
     */
    public const FORMER = [];

    /**
     * The contracts bound to another service than their interface's name:
     * the parameters are `parameter_bag` (HotParameterBag, the settings stored
     * in the database over the container's), what BaseTrait's getParameterBag()
     * gives - the interface's name is the plain Base\Service\ParameterBag.
     *
     * @var array<class-string, string>
     */
    public const SERVICES = [
        ParameterBagInterface::class => 'parameter_bag',
    ];

    public function process(ContainerBuilder $container): void
    {
        (new HostContractsPass(self::CONTRACTS, self::SERVICES, self::FORMER, self::SERVICE))->process($container);
    }
}
