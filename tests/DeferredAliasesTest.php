<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Symfony\Tests\Fixtures\DeferredAliases\Bundle;
use Omnibridge\Symfony\Tests\Fixtures\DeferredAliases\Listed;
use Omnibridge\Symfony\Tests\Fixtures\DeferredAliases\Moved;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * The pool's aliases are declared when they can first be needed (AbstractBaseBundle::deferAlias()), not each at boot:
 * as soon as their class is loaded - by its name, as a parent or an interface of another, by a former name its file
 * aliases - so that `instanceof` and the types written with the alias, which PHP checks without autoloading, answer as
 * when the alias was declared at once; and when the alias itself is asked, before any other autoloader. Each test in a
 * process of its own: an alias, once declared, stays.
 */
final class DeferredAliasesTest extends TestCase
{
    private const SITE = 'Omnibridge\Symfony\Tests\Fixtures\DeferredAliases\Site\\';

    /** A former name (Fixtures/DeferredAliases/Listed/Former.php), which only its file's class_alias() declares. */
    private const FORMER = 'Omnibridge\Symfony\Tests\Fixtures\DeferredAliases\Listed\Former';

    protected function setUp(): void
    {
        // AbstractBaseBundle's two classes of omnisense/omnibase, which only suggests itself here: a stand-in when the
        // core is not installed (the package alone).
        if (!trait_exists(\Base\Traits\SingletonTrait::class)) {
            eval('namespace Base\Traits; trait SingletonTrait { protected static $_instance = null; public static function hasInstance() { return self::$_instance !== null; } }');
        }
        if (!class_exists(\Base\Service\Model\ClassAliases::class)) {
            eval('namespace Base\Service\Model; final class ClassAliases { public const NAMESPACES = []; public static function base(string $class): ?string { return null; } }');
        }
    }

    #[RunInSeparateProcess]
    public function testAClassLoadedDeclaresItsAlias(): void
    {
        Bundle::defer(Listed\Thing::class, self::SITE.'Thing');

        $this->assertFalse(class_exists(Listed\Thing::class, false), 'deferring loads nothing');
        $this->assertFalse(class_exists(self::SITE.'Thing', false));
        $this->assertSame([Listed\Thing::class => self::SITE.'Thing'], Bundle::getDeferredAliases());

        $thing = new Listed\Thing();

        $this->assertTrue(class_exists(self::SITE.'Thing', false));
        $this->assertTrue(is_a($thing, self::SITE.'Thing'), 'an instanceof without autoloading');
        $this->assertSame(Listed\Thing::class, self::nameOf(self::SITE.'Thing'));
        $this->assertSame([], Bundle::getDeferredAliases());
    }

    #[RunInSeparateProcess]
    public function testTheAliasAskedLoadsItsClass(): void
    {
        Bundle::defer(Listed\Thing::class, self::SITE.'Thing');

        $this->assertTrue(class_exists(self::SITE.'Thing'));
        $this->assertTrue(class_exists(Listed\Thing::class, false));
        $this->assertSame(Listed\Thing::class, self::nameOf(self::SITE.'Thing'));
        $this->assertSame([], Bundle::getDeferredAliases());
    }

    #[RunInSeparateProcess]
    public function testAParentAndAnInterfaceLoadedWithAnotherClassDeclareTheirs(): void
    {
        Bundle::defer(Listed\Shape::class, self::SITE.'Shape');
        Bundle::defer(Listed\Drawable::class, self::SITE.'Drawable');

        $circle = new Listed\Circle();

        $this->assertTrue(is_a($circle, self::SITE.'Shape'));
        $this->assertTrue(is_a($circle, self::SITE.'Drawable'));
        $this->assertTrue(interface_exists(self::SITE.'Drawable', false));
    }

    #[RunInSeparateProcess]
    public function testAFormerNameDeclaresItsAliasByEitherName(): void
    {
        Bundle::defer(self::FORMER, self::SITE.'Former', Moved\Current::class);
        $this->assertFalse(class_exists(Moved\Current::class, false));

        // By the class it became, as the moved class is met (a parent, a service's class).
        $current = new Moved\Current();

        $this->assertTrue(is_a($current, self::SITE.'Former'));
        $this->assertTrue(class_exists(self::FORMER, false), 'the former name its file declares');
        $this->assertSame(Moved\Current::class, self::nameOf(self::SITE.'Former'));
    }

    #[RunInSeparateProcess]
    public function testAFormerNameAskedFirst(): void
    {
        Bundle::defer(self::FORMER, self::SITE.'Former', Moved\Current::class);

        $this->assertTrue(class_exists(self::FORMER));

        $this->assertTrue(class_exists(self::SITE.'Former', false));
        $this->assertSame(Moved\Current::class, self::nameOf(self::SITE.'Former'));
        $this->assertSame([], Bundle::getDeferredAliases());
    }

    #[RunInSeparateProcess]
    public function testAClassAlreadyLoadedIsAliasedAtOnce(): void
    {
        $this->assertTrue(class_exists(Listed\Loaded::class));

        Bundle::defer(Listed\Loaded::class, self::SITE.'Loaded');

        $this->assertTrue(class_exists(self::SITE.'Loaded', false));
        $this->assertSame([], Bundle::getDeferredAliases());
    }

    #[RunInSeparateProcess]
    public function testAnAliasAlreadyAnsweredIsLeftAlone(): void
    {
        class_alias(\ArrayObject::class, self::SITE.'Taken');

        Bundle::defer(Listed\Thing::class, self::SITE.'Taken');

        $this->assertSame([], Bundle::getDeferredAliases());
        $this->assertSame(\ArrayObject::class, self::nameOf(self::SITE.'Taken'));
    }

    #[RunInSeparateProcess]
    public function testUnderTheDebugClassLoader(): void
    {
        Bundle::defer(Listed\Shape::class, self::SITE.'Shape');
        Bundle::defer(Listed\Thing::class, self::SITE.'Thing');

        // Every autoloader wrapped, this one included (Debug::enable() in a debug front controller or bin/console).
        DebugClassLoader::enable();
        try {
            $circle = new Listed\Circle();
            $this->assertTrue(class_exists(self::SITE.'Thing'));
        } finally {
            DebugClassLoader::disable();
        }

        $this->assertTrue(is_a($circle, self::SITE.'Shape'));
        $this->assertSame([], Bundle::getDeferredAliases());
    }

    /** The name the class answering to $alias is declared under. */
    private static function nameOf(string $alias): string
    {
        if (!class_exists($alias, false) && !interface_exists($alias, false)) {
            self::fail(sprintf('"%s" is not declared.', $alias));
        }

        return (new \ReflectionClass($alias))->getName();
    }
}
