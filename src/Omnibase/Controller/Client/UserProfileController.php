<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use App\Entity\User;
use App\Form\Type\UserProfileType;
use Base\Attributes\Attribute\Iconize;
use Base\Bridge\Symfony\AccountScreens;
use Omnibridge\Symfony\Omnibase\ScreenResponder;
use Base\Auth\Account\Outcome;
use Base\Auth\Account\ProfileAccess;
use Base\Form\Definition\RecordRules;
use Omnibridge\Symfony\Omnibase\Form\FormProcessorInterface;
use Omnibridge\Symfony\Omnibase\Form\FormProxyInterface;
use Base\Repository\Users;
use Base\Screen\View;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Omnibridge\Contract\Validation;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Omnibridge\Symfony\Omnibase\Response\AnswersOutcomes;

/**
 * A member's profile and its editor under Symfony: whose, and who may edit it, is omnibase's ProfileAccess (plan
 * "omnibase agnostic", step F5e slice 5), the same the twin's editor asks; the form and the screen are Symfony's.
 */
class UserProfileController extends AbstractController
{
    use AnswersOutcomes;

    /** The accounts by their interface (step R28), where it asked the entity manager for their repository. */
    public function __construct(protected Users $users, protected FormProxyInterface $formProxy, protected ProfileAccess $profiles)
    {
    }

    #[Route("/profile/{id}/edit/", name: "user_profileEdit")]
    public function Edit(Request $request, Validation $validation, UnitOfWork $unitOfWork, int $id = -1)
    {
        $user = $this->profiles->edit($id);
        if ($user instanceof Outcome) {
            return $this->answer($user);
        }

        return $this->formProxy
            ->createProcessor("profile:user", UserProfileType::class, ["use_model" => true])
            ->setData($user)
            ->onDefault(function (FormProcessorInterface $formProcessor) use ($user) {
                return $this->render('client/user/profile_edit.html.twig', [
                    'user' => $user,
                    "form" => $formProcessor->getForm()->createView()
                ]);
            })
            ->onSubmit(function (FormProcessorInterface $formProcessor, Request $request) use ($user, $validation, $unitOfWork) {
                $user = $formProcessor->hydrate($user);

                // Its rules before it is kept (a name another account has): said under the field, the account
                // read again as it is stored, where the database's refusal answered 500 and signed the session out.
                if ([] !== $violations = RecordRules::violations($validation, $user, ['edit'])) {
                    $unitOfWork->refresh($user);
                    $form = $formProcessor->getForm();
                    foreach ($violations as $violation) {
                        $field = preg_split('/[.\[]/', $violation->path, 2)[0] ?? '';
                        ($field !== '' && $form->has($field) ? $form->get($field) : $form)->addError(new FormError($violation->message));
                    }

                    return $this->render('client/user/profile_edit.html.twig', ['user' => $user, 'form' => $form->createView()]);
                }
                $this->users->flush();

                return $this->redirectToRoute('user_profileEdit');
            })
            ->handleRequest($request)
            ->getResponse();
    }

    /**
     * A member's profile: its template and its view model
     * (Base\Screen\Account\ProfilePage, plan "omnibase agnostique", C5) to
     * omnibase's ScreenResponder - the core's template reads the screen alone,
     * a front end gets it in JSON; a template the site overrides gets `user`
     * as well (deprecated, gone in 4.0).
     */
    #[Route("/profile/{id}", name: "user_profile")]
    #[Iconize("fa-solid fa-fw fa-id-card")]
    public function Show(Request $request, ScreenResponder $responder, AccountScreens $screens, int $id = -1)
    {
        $user = $this->profiles->show($id);
        if ($user instanceof Outcome) {
            return $this->answer($user);
        }
        $template = 'client/user/profile_show.html.twig';

        return $responder->respond($request, new View($template, $screens->profile($user), 'Account/Profile'), $responder->legacy($template, fn () => ['user' => $user]));
    }
}
