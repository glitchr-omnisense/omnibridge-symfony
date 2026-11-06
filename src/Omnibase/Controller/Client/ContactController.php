<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use Base\Form\FormProcessorInterface;
use Base\Form\FormProxy;
use Base\Form\Type\ContactType;
use Base\Notifier\ContactMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The contact page under Symfony: omnibase's Base\Notifier\ContactMessage sends the message and its confirmation (plan
 * "omnibase agnostic", step F5e slice 5b); the form and the pages are Symfony's.
 */
class ContactController extends AbstractController
{
    public function __construct(protected FormProxy $formProxy, protected ContactMessage $message)
    {
    }

    #[Route("/contact", name: "base_contact")]
    public function Contact(Request $request, array $options = []): Response
    {
        $user = $this->getUser();

        return $this->formProxy
            ->createProcessor("contact", ContactType::class, array_merge($options, ["use_model" => true]))
            ->setData($user)
            ->onDefault(fn (FormProcessorInterface $formProcessor) => $this->render(ContactMessage::TEMPLATE, ['user' => $user, "form" => $formProcessor->getForm()->createView()]))
            ->onSubmit(function (FormProcessorInterface $formProcessor) {
                $page = $this->message->send($formProcessor->getData());

                return $this->render($page->template, $page->data);
            })
            ->handleRequest($request)
            ->getResponse();
    }
}
