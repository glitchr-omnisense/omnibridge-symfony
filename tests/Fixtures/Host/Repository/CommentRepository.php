<?php

namespace App\Repository;

use Base\Database\Repository\Finders;
use Base\Repository\ThreadRepository;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * lesringostars' App\Repository\Article\CommentRepository's shape: a subclass of the core's ThreadRepository taking a
 * service of the application's (a cache pool) beside the finders, autowired as the site's `App\:` resource makes it.
 */
class CommentRepository extends ThreadRepository
{
    public function __construct(public readonly CacheInterface $cache, Finders $finders, ?string $entityName = null)
    {
        parent::__construct($finders, $entityName);
    }
}
