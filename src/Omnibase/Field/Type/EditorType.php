<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Field\Behaviour\Editor;
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

    /**
     * The neutral behaviour's options (Base\Field\Behaviour\Editor): the placeholder, the script's entry,
     * collab_autosave (a debounced save through ux_editorjs_autosave, its version hash refusing a stale one)
     * and collab_live (a relay's live editing, its ticket through ux_editorjs_collabTicket; refer to the
     * relay/ directory of the editorjs-yjs repository), each false by default and independent.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults((new Editor())->options([]));
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

    /** An empty document (no block) is no text, as the behaviour reads it back on every host. */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $data = $event->getData();
            $reversed = (new Editor())->reverse($data, $options);
            if ($reversed !== $data) {
                $event->setData($reversed);
            }
        });
    }

    /**
     * The neutral behaviour's vars: the widget's id, the value with its pictures' addresses, the editor's
     * five addresses with their token (Base\Field\Editor\EditorJs) and its settings - with the room of
     * this field when a collaboration is asked and the form's root data is a record already stored (an
     * embedded form or a collection binding a DTO has none: the field is then plain).
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars = array_replace($view->vars, (new Editor())->vars($options + [
            'editor_record' => $form->getRoot()->getData(),
            'editor_field' => $form->getName(),
            'editor_locale' => $view->vars['locale'] ?? null,
        ], $view->vars['value'] ?? null, $view->vars['id']));
    }
}
