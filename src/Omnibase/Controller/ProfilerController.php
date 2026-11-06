<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Notifier\NotifierInterface;
use Base\Notifier\TestRecipient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The profiler's test e-mail under Symfony: omnibase's Base\Notifier\TestRecipient decides whom it is written to (plan
 * "omnibase agnostic", step F5e slice 5b); the notifier renders or sends it.
 */
class ProfilerController extends AbstractController
{
    public function __construct(protected NotifierInterface $notifier, protected TestRecipient $recipient)
    {
    }

    #[Route("/_profiler/email", name: "_profiler_email", priority: 1)]
    public function Email(): Response
    {
        return $this->notifier->renderTestEmail($this->recipient->recipient());
    }

    #[Route("/_profiler/email/send", name: "_profiler_email_send", priority: 1)]
    public function SendEmail(): Response
    {
        $this->notifier->sendTestEmail($this->recipient->recipient());

        return $this->redirectToRoute("_profiler_email");
    }
}
