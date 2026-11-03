<?php

namespace Omnibridge\Symfony\Omnibase\Controller\UX;

use Base\Contract\Runtime;
use Hashids\Hashids;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(priority: -1)]
class AutovalidateController extends AbstractController
{
    private TranslatorInterface $translator;
    private UnitOfWork $unitOfWork;
    private Hashids $hashIds;

    public function __construct(TranslatorInterface $translator, UnitOfWork $unitOfWork)
    {
        $this->hashIds = new Hashids(Runtime::environment()->secret());
        $this->unitOfWork = $unitOfWork;
        $this->translator = $translator;
    }

    public function encode(array $array): string
    {
        $hex = bin2hex(serialize($array));
        return $this->hashIds->encodeHex($hex);
    }

    public function decode(string $hash): array
    {
        $hex = $this->hashIds->decodeHex($hash);
        return $hex ? unserialize(hex2bin($hex)) : [];
    }

    #[Route("/ux/validation/{hashid}", name:"ux_validation")]
    public function Main(Request $request, string $hashid): Response
    {
        $dict = $this->decode($hashid);
        $token = $dict["token"] ?? null;
        $value = $dict["value"] ?? null;
        $formType = $dict["form_type"] ?? null;

        $expectedMethod = Runtime::environment()->isDebug() ? "GET" : "POST";
        if ($this->isCsrfTokenValid("validation", $token) && $request->getMethod() == $expectedMethod) {
            $array = ["status" => "Fine"];
            return new JsonResponse($array);
        }

        /*
         * Add some checks here.
         */
        
        return new JsonResponse("Invalid request", 500);
    }
}
