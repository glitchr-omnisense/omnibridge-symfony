<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use Base\Form\FormProcessorInterface;
use Base\Form\FormProxy;
use Base\Form\Model\ContactModel;
use Base\Form\Type\ContactType;
use Base\Notifier\Notifier;
use Base\Notifier\NotifierInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class ContactController extends AbstractController
{
    /**
     * @var NotifierInterface
     */
    protected NotifierInterface $notifier;

    /**
     * @var FormProxy
     */
    protected FormProxy $formProxy;

    public function __construct(FormProxy $formProxy, Notifier $notifier)
    {
        $this->formProxy = $formProxy;
        $this->notifier = $notifier;
    }

    #[Route("/contact", name:"base_contact")]
    public function Contact(Request $request, array $options = []): Response
    {
        $user = $this->getUser();

        return $this->formProxy
            ->createProcessor("contact", ContactType::class, array_merge($options, ["use_model" => true]))
            ->setData($user)
            ->onDefault(function (FormProcessorInterface $formProcessor) use ($user) {
                return $this->render('client/contact/index.html.twig', [
                    'user' => $user,
                    "form" => $formProcessor->getForm()->createView()
                ]);
            })
            ->onSubmit(function (FormProcessorInterface $formProcessor, Request $request) use ($user) {
                /**
                 * @var ContactModel $contactModel
                 */
                $contactModel = $formProcessor->getData();
                $this->notifier->sendContactEmail($contactModel);
                $this->notifier->sendContactEmailConfirmation($contactModel);

                return $this->render('client/contact/success.html.twig');
            })
            ->handleRequest($request)
            ->getResponse();
    }
}
