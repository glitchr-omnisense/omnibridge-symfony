<?php

namespace App\Repository;

use App\Entity\Note;
use Base\Database\Repository\Finders;
use Base\Database\Repository\ServiceEntityRepository;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * An application's repository with a service of its own beside the finders (lesringostars' CommentRepository: a cache
 * pool), autowired as a site's services.yaml `App\:` resource makes it: Repositories::of() must answer this service.
 *
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(public readonly CacheInterface $cache, Finders $finders, ?string $entityName = null)
    {
        parent::__construct($finders, $entityName);
    }
}
