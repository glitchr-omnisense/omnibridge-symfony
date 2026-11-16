<?php

namespace Base\Form\Extension;

use Base\Entity\User;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\Collab\CollabRoomResolver;
use Base\Service\Collab\CollabTicketFactory;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * This class activates real-time presence and autosave for any regular
 * field: an input field, a select field, a select2 field, or another
 * field type. This class is not specific to EditorType. This class adds
 * a new `collab` form option, with three possible values: false (the
 * default value), "autosave", or "live".
 *
 * This class writes plain data-collab-* attributes. This class does not
 * change form_div_layout.html.twig. Twig's own widget_attributes
 * rendering already outputs the content of $view->vars["attr"], set by
 * finishView(). Because of this, every field type receives these
 * attributes automatically, including a field that uses
 * select2_widget's own attribute merge.
 *
 * The file form-type-collab-presence.js, inside
 * assets/styles/js/forms/, is the client-side code for this feature.
 * This file reuses the ux_editorjs_collabTicket action and the
 * ux_editorjs_autosave action. EditorType uses these same two generic
 * actions. This design follows the "one controller" decision in the
 * collaboration plan.
 */
class FormTypeCollabExtension extends AbstractTypeExtension
{
    protected CollabRoomResolver $roomResolver;
    protected TokenStorageInterface $tokenStorage;
    protected CsrfTokenManagerInterface $csrfTokenManager;
    protected AdvancedRouterInterface $router;
    protected ?CollabTicketFactory $ticketFactory;

    public function __construct(CollabRoomResolver $roomResolver, TokenStorageInterface $tokenStorage, CsrfTokenManagerInterface $csrfTokenManager, AdvancedRouterInterface $router, ?CollabTicketFactory $ticketFactory = null)
    {
        $this->roomResolver = $roomResolver;
        $this->tokenStorage = $tokenStorage;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->router = $router;
        $this->ticketFactory = $ticketFactory;
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['collab' => false]);
        $resolver->setAllowedValues('collab', [false, "autosave", "live"]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $collab = $options["collab"] ?? false;
        if (!$collab) {
            return;
        }

        // "live" needs a relay: without one the field would ask
        // ux_editorjs_collabTicket for a ticket the site cannot give (503), so
        // it stays a plain field, as EditorType's collab_live does.
        if ($collab === "live" && !$this->ticketFactory?->isConfigured()) {
            return;
        }

        $entity = $this->roomResolver->resolveEntity($form->getRoot()->getData());
        if (!$entity || !method_exists($entity, "getId") || !$entity->getId()) {
            return;
        }

        $locale = $view->vars["locale"] ?? null;
        $room = $this->roomResolver->buildRoom(get_class($entity), $entity->getId(), $form->getName(), $locale);

        // This room's token, not the session-wide "editorjs": it writes this field only.
        $token = $this->csrfTokenManager->getToken($this->roomResolver->tokenId($room))->getValue();
        $currentUser = $this->tokenStorage->getToken()?->getUser();

        $collabAttr = [
            "data-collab-field" => $view->vars["id"],
            "data-collab-room"  => $room,
            "data-collab-mode"  => $collab,
            "data-collab-token" => $token,
            "data-collab-fqcn"  => get_class($entity),
            "data-collab-id"    => $entity->getId(),
            "data-collab-property" => $form->getName(),
            "data-collab-locale"   => $locale,
        ];

        if ($collab === "live") {
            $collabAttr["data-collab-ticket-url"] = $this->router->generate("ux_editorjs_collabTicket");
            $collabAttr["data-collab-user"] = $currentUser instanceof User ? json_encode([
                "name"  => (string) $currentUser,
                "color" => $currentUser->getColor(),
            ]) : "";
        }

        if ($collab === "autosave") {
            $collabAttr["data-collab-autosave-url"] = $this->router->generate("ux_editorjs_autosave");
        }

        $view->vars["attr"] = ($view->vars["attr"] ?? []) + $collabAttr;
    }
}
