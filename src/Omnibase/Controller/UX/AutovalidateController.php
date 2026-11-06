<?php

namespace Omnibridge\Symfony\Omnibase\Controller\UX;

use Base\Field\Validation\Autovalidate;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A field's validation while the form is filled, under Symfony: omnibase's Base\Field\Validation\Autovalidate decides
 * (plan "omnibase agnostic", step F5e slice 5b) - the payload, its token, the method expected.
 */
#[Route(priority: -1)]
class AutovalidateController extends AbstractController
{
    public function __construct(private readonly Autovalidate $autovalidate)
    {
    }

    /** @param array<string, mixed> $array */
    public function encode(array $array): string
    {
        return $this->autovalidate->encode($array);
    }

    /** @return array<string, mixed> */
    public function decode(string $hash): array
    {
        return $this->autovalidate->decode($hash);
    }

    #[Route("/ux/validation/{hashid}", name: "ux_validation")]
    public function Main(Request $request, string $hashid): Response
    {
        $json = $this->autovalidate->answer($hashid, $request->getMethod());

        return new JsonResponse($json->data, $json->status);
    }
}
