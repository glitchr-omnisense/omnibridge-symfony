<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Base\Security\Session;

use SessionHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

// Help opcache.preload discover always-needed symbols
// class_exists(DynamicSessionStorage::class);

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class DynamicSessionStorageFactory implements SessionStorageFactoryInterface
{
    private array $options;
    private AbstractProxy|SessionHandlerInterface|null $handler;
    private ?MetadataBag $metaBag;
    private bool $secure;

    /**
     * @see DynamicSessionStorage constructor.
     */
    public function __construct(array $options = [], AbstractProxy|SessionHandlerInterface|null $handler = null, MetadataBag|null $metaBag = null, bool $secure = false)
    {
        $this->options = $options;
        $this->handler = $handler;
        $this->metaBag = $metaBag;
        $this->secure = $secure;
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        $storage = new DynamicSessionStorage($this->options, $this->handler, $this->metaBag);
        if ($this->secure && $request?->isSecure()) {
            $storage->setOptions(['cookie_secure' => true]);
        }

        return $storage;
    }
}
