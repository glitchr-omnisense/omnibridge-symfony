<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Host;

use Symfony\Component\HttpFoundation\Response;

/** Stands in the test for omnibase's error controller, under its id. */
final class Errors
{
    public function Main(\Throwable $exception): Response
    {
        return new Response('error controller: '.$exception::class, Response::HTTP_NOT_FOUND);
    }
}
