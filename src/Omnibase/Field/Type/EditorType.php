<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Entity\User;
use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface;
use Base\Service\Collab\CollabRoomResolver;
use Base\Service\Collab\CollabTicketFactory;
use Base\Service\Model\Wysiwyg\MediaEnhancerInterface;
use Base\Service\ObfuscatorInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class EditorType extends AbstractType
{

    /** @var ParameterBagInterface */
    protected ParameterBagInterface $parameterBag;

    protected AdvancedRouterInterface $router;
    protected CsrfTokenManagerInterface $csrfTokenManager;
    protected ObfuscatorInterface $obfuscator;

    protected TranslatorInterface $translator;
    protected MediaEnhancerInterface $mediaEnhancer;

    protected CollabRoomResolver $roomResolver;
    protected TokenStorageInterface $tokenStorage;
    protected ?CollabTicketFactory $ticketFactory;

    public function __construct(ParameterBagInterface $parameterBag, TranslatorInterface $translator, AdvancedRouterInterface $router, CsrfTokenManagerInterface $csrfTokenManager, ObfuscatorInterface $obfuscator, MediaEnhancerInterface $mediaEnhancer, CollabRoomResolver $roomResolver, TokenStorageInterface $tokenStorage, ?CollabTicketFactory $ticketFactory = null)
    {
        $this->parameterBag = $parameterBag;
        $this->router = $router;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->obfuscator = $obfuscator;
        $this->translator = $translator;
        $this->mediaEnhancer = $mediaEnhancer;
        $this->roomResolver = $roomResolver;
        $this->tokenStorage = $tokenStorage;
        $this->ticketFactory = $ticketFactory;
    }

    public function getParent(): ?string
    {
        return HiddenType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'editor';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'empty_data' => null,
            'placeholder' => $this->translator->trans("@fields.editor.placeholder"),
            "webpack_entry" => "form.editor",

            // These two options are optional. The default value of each
            // option is false. A form can activate the two options
            // independently.
            //
            // collab_autosave: this option activates a debounced,
            // periodic save action, through ux_editorjs_autosave. This
            // option also activates an optimistic-concurrency conflict
            // guard. This option, by itself, uses no WebSocket relay.
            //
            // collab_live: this option activates real-time presence and
            // real-time collaboration, through the collab relay. Refer to
            // the relay/ directory of the editorjs-yjs repository for the
            // relay code. This option creates a join ticket, through
            // ux_editorjs_collabTicket. This option does not require the
            // collab_autosave option. The relay's own persistence bridge
            // provides durable saving for this option.
            //
            // Refer to Base\Controller\UX\EditorController::Autosave()
            // and Base\Controller\UX\EditorController::CollabTicket() for
            // the related PHP code.
            "collab_autosave" => false,
            "collab_live"     => false,
        ]);
    }

    /**
     * @param $view
     * @return string|null
     */
    public function getFormID($view): ?string
    {
        $parent = $view->parent;
        while ($parent->parent) {
            $parent = $parent->parent;
        }

        return $parent->vars["attr"]["id"] ?? null;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$options) {

            $data = $event->getData();
            
            $json = json_decode($data);
            if($json && count($json->blocks) < 1) {
                $event->setData(null);
            }
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars["id"] = str_replace("-", "_", $view->vars["id"]);

        // Editor options
        $editorOpts = [];
        $editorOpts["placeholder"] = $options["placeholder"];

        $token = $this->csrfTokenManager->getToken("editorjs")->getValue();
        $data = $this->obfuscator->encode(["token" => $token], ObfuscatorInterface::NO_SHORT);

        // This code captures the raw value here, before the
        // mediaEnhancer service changes the image block URLs below. The
        // autosave conflict guard needs a version hash of the value in
        // the database, with the raw origin URLs. The guard must not use
        // a hash of the display-enhanced JSON sent to the browser. If
        // this code used the wrong value, every save of a block with an
        // image would show a false conflict.
        $rawValue = $view->vars["value"];

        $value = $view->vars["value"];
        if(is_json($value)) {
            $value = \json_decode($value ?? "{}");
            if(!$value || !property_exists($value, "blocks")) {
                $value = (object) ["time" => time(), "blocks" => []];
            }
            
            foreach($value->blocks as $k => $block) {
                if ($block->type === "image" && property_exists($block->data, "file") && property_exists($block->data->file, "url")) {
                    $block->data->file->origin ??= $block->data->file->url; // @deprecated - to be removed in favor of "storageId" property later on
                    $block->data->file->url = $this->mediaEnhancer->enhance($block->data->file->origin, ["storage" => $this->parameterBag->get("base.twig.editor.storage")], [], []);
                    $value->blocks[$k] = $block;
                }
            }
        }

        $view->vars["value"] = (is_object($value) || is_array($value)) ? json_encode($value) : $view->vars["value"];
        $view->vars["uploadByFile"] = $this->router->generate("ux_editorjs_uploadByFile", ["data" => $data]);
        $view->vars["uploadByUrl"]  = $this->router->generate("ux_editorjs_uploadByUrl", ["data" => $data]);

        $view->vars["endpointByUser"]    = $this->router->generate("ux_editorjs_endpointByUser", ["data" => $data]);
        $view->vars["endpointByThread"]  = $this->router->generate("ux_editorjs_endpointByThread", ["data" => $data]);
        $view->vars["endpointByKeyword"] = $this->router->generate("ux_editorjs_endpointByKeyword", ["data" => $data]);

        // This code adds the optional "collab" block only in two
        // conditions. First, the form must activate the collab_autosave
        // option or the collab_live option. Second, the form's root data
        // must resolve to a real, mapped entity. An embedded form or a
        // collection form can bind a separate DTO object instead. In
        // that case, this code adds no "collab" key. The field then
        // shows plain, non-collaborative behavior.
        $collabAutosave = $options["collab_autosave"] ?? false;
        $collabLive     = $options["collab_live"] ?? false;

        // collab_live needs a relay (COLLAB_TICKET_SECRET and COLLAB_RELAY_WS_URL):
        // without one the browser would ask ux_editorjs_collabTicket for a ticket
        // the site cannot give (503, a console error on every edit screen), so the
        // field is offered as if the form had not asked for it, autosave aside.
        if ($collabLive && !$this->ticketFactory?->isConfigured()) {
            $collabLive = false;
        }

        if ($collabAutosave || $collabLive) {
            $entity = $this->roomResolver->resolveEntity($form->getRoot()->getData());
            if ($entity && method_exists($entity, "getId") && $entity->getId()) {
                // This code does not yet resolve the active locale tab
                // for translatable content. This code uses one
                // locale-agnostic room instead, until that work is
                // complete. Because of this limit, a multi-locale form
                // must not activate collab_autosave or collab_live yet.
                $locale = $view->vars["locale"] ?? null;
                $room = $this->roomResolver->buildRoom(get_class($entity), $entity->getId(), $form->getName(), $locale);

                // This room's token, not the session-wide "editorjs": it writes this field only.
                $token = $this->csrfTokenManager->getToken($this->roomResolver->tokenId($room))->getValue();
                $currentUser = $this->tokenStorage->getToken()?->getUser();

                $editorOpts["collab"] = [
                    "autosave"    => $collabAutosave,
                    "live"        => $collabLive,
                    "fqcn"        => get_class($entity),
                    "id"          => $entity->getId(),
                    "field"       => $form->getName(),
                    "locale"      => $locale,
                    "room"        => $room,
                    "autosaveUrl" => $this->router->generate("ux_editorjs_autosave"),
                    "ticketUrl"   => $collabLive ? $this->router->generate("ux_editorjs_collabTicket") : null,
                    "token"       => $token,
                    "version"     => $this->roomResolver->hash($rawValue),
                    "user"        => $currentUser instanceof User ? [
                        "id"     => $currentUser->getId(),
                        "name"   => (string) $currentUser,
                        "avatar" => $currentUser->getAvatar(),
                        "color"  => $currentUser->getColor(),
                    ] : null,
                ];
            }
        }

        $view->vars["editor"] = json_encode($editorOpts);
    }
}
