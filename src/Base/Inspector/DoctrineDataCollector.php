<?php

namespace Base\Inspector;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector as DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DoctrineDataCollector extends DataCollector
{
    private DoctrineDataCollector $innerCollector;

    public function __construct(DoctrineDataCollector $innerCollector)
    {
        $this->innerCollector = $innerCollector;
        parent::__construct($innerCollector->getRegistry());
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // First collect default data
        $this->innerCollector->collect($request, $response, $exception);
        $this->data = $this->innerCollector->getData();

        // Add your custom data (e.g., backtrace)
        foreach ($this->data['queries'] as $connection => &$queries) {
            foreach ($queries as &$query) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
                $caller = array_filter($trace, fn($frame) =>
                    isset($frame['file']) && !str_contains($frame['file'], '/vendor/')
                );
                $query['origin'] = reset($caller)['file'] . ':' . reset($caller)['line'] ?? 'N/A';
            }
        }
    }

    public function getName(): string
    {
        return 'doctrine'; // must match original service
    }
}