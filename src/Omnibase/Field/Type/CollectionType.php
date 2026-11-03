<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Field\Behaviour\Collection as Entries;
use Base\Service\TranslatorInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Base\Routing\AdminUrlGeneratorInterface;
use Exception;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class CollectionType extends AbstractType
{
    /** @var array<int,int> total entry count per form instance, pre-windowing */
    private array $entryTotals = [];

    /** @var array<int,array<int|string,mixed>> entries withheld by the window, per form instance */
    private array $withheldEntries = [];

    /** @var array<int,array<int|string,mixed>> pre-submit entries of capped collections, per form instance */
    private array $originalEntries = [];

    /** @var array<int,array<int,true>> indices the client declared as rendered, per form instance */
    private array $renderedIndices = [];

    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;

    /**
     * @var AuthorizationChecker
     */
    protected AuthorizationChecker $authorizationChecker;

    /**
     * @var AdminUrlGeneratorInterface
     */
    protected AdminUrlGeneratorInterface $adminUrlGenerator;

    public function __construct(TranslatorInterface $translator, AuthorizationChecker $authorizationChecker, AdminUrlGeneratorInterface $adminUrlGenerator, private readonly ?\Symfony\Component\HttpFoundation\RequestStack $requestStack = null)
    {
        $this->translator = $translator;

        $this->authorizationChecker = $authorizationChecker;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function getBlockPrefix(): string
    {
        return 'collection2';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // omnisense/omnibase's Collection (Entries): the defaults, the same on every host (its entry here a Symfony type).
        $resolver->setDefaults(array_replace(Entries::DEFAULTS, [
            'entry_type' => HiddenType::class,
            'entry_label' => function ($i, $label) {
                if ($i === "__prototype__") {
                    return false;
                }

                if (!is_object($label)) {
                    return $this->translator->trans("@fields.collection.entry") . " #" . (((int)$i) + 1);
                }

                $_label = $this->translator->transEntity($label) . " #" . (((int)$label->getId()) + 1);
                if (is_stringeable($label)) {
                    $_label .= " : " . ((string)$label);
                }
                return $_label;
            },
            'invalid_message' => function (Options $options, $previousValue) {
                return 'The collection is invalid.';
            },
        ]));

        $resolver->setNormalizer('entry_options', function (Options $options, $value) {
            $value['block_name'] = 'entry';
            $value["label"] = false;
            return $value;
        });

        $resolver->setNormalizer('required', function (Options $options, $value) {
            // Collection is always submitted regardless of its options..
            // It returns at least an empty array..
            // NB: Child fields "required" options are deduced from parents..
            //     .. but collection is not supposed to knows about child requirements, IMO
            return true;
        });

        $resolver->setNormalizer('allow_add', fn(Options $options, $value) => $options["length"] == 0 && $value);
        $resolver->setNormalizer('allow_delete', fn(Options $options, $value) => $options["length"] == 0 && $value);

        $resolver->setAllowedTypes('delete_empty', ['bool', 'callable']);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['allow_add'] && $options['prototype']) {
            $prototypeOptions = $options['entry_options'];
            if (null !== $options['prototype_data']) {
                $prototypeOptions['data'] = $options['prototype_data'];
            }

            if (null !== $options['entry_required']) {
                $prototypeOptions['required'] = $options['entry_required'];
            }

            $prototypeOptions["label"] = "__prototype__";
            $prototypeOptions['attr']['placeholder'] = $prototypeOptions['attr']['placeholder'] ?? $this->translator->trans("@fields.array.value");
            $prototype = $builder->create($options['prototype_name'], $options['entry_type'], $prototypeOptions);
            $builder->setAttribute('prototype', $prototype->getForm());
        }

        // Prevent stringeable issue for entities...
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {
            $form = $event->getForm();
            $data = $event->getData();
            if ($form->getName() == "_collection") { // Special case from association type
                $data ??= $form->getParent()->getData();
            }

            $data ??= [];

            foreach ($data as $id => $entry) {
                if (is_object($entry) && !$options["allow_object"]) {
                    throw new Exception("Object data are not allowed in collection unless you mark it as so.. (use `allow_object` option)");
                }
            }

            $event->setData($data);
        });

        // Cap the number of entries built, when asked and when SAFE to do so.
        //
        // The guard matters: Symfony removes entries that are absent from the
        // submitted data only when allow_delete is true. With allow_delete
        // false, entries we never rendered are left alone on save - so capping
        // is purely a rendering economy. With allow_delete true the same cap
        // would silently delete every entry past the limit the moment the form
        // is submitted, so it is refused outright rather than made optional.
        $maxEntries = $options["max_entries"];
        $entryOffset = max(0, (int) $options["entry_offset"]);

        // Window on GET renders ONLY. A form built for a submission binds every
        // entry natively: the one-off query cost of a save is nothing next to
        // the data-loss paths a windowed submit opens (a deletable collection
        // would read withheld entries as deleted; an edit inside a lazily
        // loaded entry would bind to a child the window never built and be
        // dropped). The N+1 this bounds is a page-VIEW cost, and page views
        // are GETs. This also removes the old allow_delete restriction - with
        // no windowing on POST, deletion semantics stay fully native.
        $method = $this->requestStack?->getCurrentRequest()?->getMethod() ?? 'GET';
        if ('GET' !== $method) {
            $maxEntries = null;
        }

        // Remember how many entries there really were, before any windowing, so
        // the view can offer "load the rest". Keyed per form instance and
        // request-scoped - the type itself is a shared service.
        $capConfigured = \is_int($options["max_entries"]) && $options["max_entries"] > 0;
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($capConfigured): void {
            $data = $event->getData();
            $this->entryTotals[spl_object_id($event->getForm())] = is_countable($data) ? \count($data) : 0;

            // A capped collection remembers its ORIGINAL entries: the rendered-set
            // merge below needs them to restore entries the page never showed.
            if ($capConfigured && (null !== $data)) {
                $this->originalEntries[spl_object_id($event->getForm())] =
                    $data instanceof Collection ? $data->toArray() : (\is_array($data) ? $data : []);
            }
        }, 10);
        if (\is_int($maxEntries) && $maxEntries > 0) {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($maxEntries, $entryOffset) {
                $data = $event->getData();
                if (null === $data) {
                    return;
                }

                $count = is_countable($data) ? \count($data) : 0;
                if (0 === $entryOffset && $count <= $maxEntries) {
                    return;
                }

                // preserve_keys = true is load-bearing, not tidiness: the key IS
                // the entry's index in the collection, and therefore the index in
                // the submitted field name. A lazily fetched entry #12 must come
                // back as [_collection][12][...] or it would bind to the wrong
                // slot (or create a new one) on save.
                $all = $data instanceof Collection ? $data->toArray() : (\is_array($data) ? $data : []);
                $window = \array_slice($all, $entryOffset, $maxEntries, true);

                // Everything outside the window must come BACK on submit. The
                // association mapper rebuilds the collection from the submitted
                // entries (AssociationType does $viewData->clear() then re-adds),
                // so an entry that was never rendered would simply be dropped -
                // and on an inverse-side mapping its owner is nulled too. That is
                // silent data loss, and it is why windowing cannot be a
                // rendering-only concern.
                $this->withheldEntries[spl_object_id($event->getForm())] =
                    \array_diff_key($all, $window);

                $event->setData($data instanceof Collection ? new ArrayCollection($window) : $window);
            });
        }

        // Resize collection according to length option
        if (is_int($options["length"]) && $options["length"] > 0) {
            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$options) {
                $data = $event->getData() ?? [];
                if ($data instanceof Collection) {
                    while (count($data) < $options["length"]) {
                        $data->add(null);
                    }
                } elseif (is_array($data)) {
                    $data = array_pad($data, $options["length"], null);
                }

                $event->setData($data);
            });
        }

        // The client declares WHICH entry indices it actually rendered
        // ("_rendered", emitted by the collection widget when windowed and
        // extended by the lazy loader). Popped here, before ResizeFormListener
        // would mistake it for an entry. Priority 60 > Resize's own PRE_SUBMIT.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data) || !\array_key_exists('_rendered', $data)) {
                return;
            }

            $raw = $data['_rendered'];
            unset($data['_rendered']);
            $event->setData($data);

            $rendered = [];
            foreach (explode(',', \is_string($raw) ? $raw : '') as $piece) {
                $piece = trim($piece);
                if ('' !== $piece && ctype_digit($piece)) {
                    $rendered[(int) $piece] = true;
                }
            }
            $this->renderedIndices[spl_object_id($event->getForm())] = $rendered;

            // Children the page never rendered must not stay in the form: a
            // child with no submitted data maps NULLS into the entity it holds
            // (the classic omitted-field wipe), and since it is the same object
            // instance the flush would persist those nulls even after the
            // collection-level restore below put the "original" back. Removing
            // the child means the entry is simply absent from the mapped data,
            // and the SUBMIT merge reinstates the untouched entity. A child the
            // client DID submit stays, declared or not - trust real data.
            $form = $event->getForm();
            foreach ($form->all() as $childName => $child) {
                if (!ctype_digit((string) $childName)) {
                    continue;
                }
                if (!isset($rendered[(int) $childName]) && !\array_key_exists($childName, $data) && !\array_key_exists((int) $childName, $data)) {
                    $form->remove($childName);
                }
            }
        }, 60);

        // Re-attach the withheld entries once the rendered window has been
        // submitted, keyed by their original index so ordering survives. Runs
        // late (-10) so it sees the resized/validated data, and unconditionally
        // - if nothing was withheld the map is empty and this is a no-op.
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $key = spl_object_id($event->getForm());
            $withheld = $this->withheldEntries[$key] ?? [];
            unset($this->withheldEntries[$key]);

            // Rendered-set contract, for full (non-windowed) submission builds:
            // an original entry that is ABSENT from the submitted data is only a
            // deletion if the client actually rendered it - the operator cannot
            // have deleted a row they were never shown. Entries outside the
            // declared rendered set are restored; entries inside it keep fully
            // native semantics (including deletion). With no declaration at all
            // (a non-lazy form), nothing is restored and behaviour is untouched.
            $rendered = $this->renderedIndices[$key] ?? null;
            unset($this->renderedIndices[$key]);

            $original = $this->originalEntries[$key] ?? [];
            unset($this->originalEntries[$key]);

            $data = $event->getData();
            $merged = $data instanceof Collection ? $data->toArray() : (\is_array($data) ? $data : []);
            $changed = false;

            foreach ($withheld as $index => $entry) {
                $merged[$index] = $entry;
                $changed = true;
            }

            if (null !== $rendered) {
                foreach ($original as $index => $entry) {
                    // !isset (not array_key_exists): an index that mapped to
                    // NULL is as lost as an absent one and must be restored.
                    if (!isset($rendered[$index]) && !isset($merged[$index])) {
                        $merged[$index] = $entry;
                        $changed = true;
                    }
                }
            }

            if (!$changed) {
                return;
            }
            ksort($merged);

            $event->setData($data instanceof Collection ? new ArrayCollection($merged) : $merged);
        }, -10);

        $builder->addEventSubscriber(new ResizeFormListener(
            $options['entry_type'],
            $options['entry_options'],
            $options['allow_add'],
            $options['allow_delete'],
            $options['delete_empty']
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        //
        // Set controller url
        $view->vars['href'] = $options["href"];
        $view->vars['html'] = $options["html"];
        $view->vars['entry_collapsed'] = $options['entry_collapsed'];
        $view->vars['entry_label'] = $options['entry_label'];
        $view->vars['entry_options'] = $options['entry_options'];
        $view->vars['data_class'] = $options['data_class'];
        $view->vars['group'] = $options['group'];
        $view->vars['row_group'] = $options['row_group'];
        $view->vars['allow_add'] = $options['allow_add'];
        $view->vars['allow_delete'] = $options['allow_delete'];

        if ($form->getConfig()->hasAttribute('prototype')) {
            $prototype = $form->getConfig()->getAttribute('prototype');
            $view->vars['prototype'] = $prototype->setParent($form)->createView($view);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['length'] = $options["length"];
        // The window, for the lazy-load control (see collection2_widget).
        $view->vars['entry_offset'] = max(0, (int) $options["entry_offset"]);
        $view->vars['max_entries'] = $options["max_entries"];
        $view->vars['entry_total'] = $this->entryTotals[spl_object_id($form)] ?? null;

        $prefixOffset = -1;
        // check if the entry type also defines a block prefix
        /** @var FormInterface $entry */
        foreach ($form as $entry) {
            if ($entry->getConfig()->getOption('block_prefix')) {
                --$prefixOffset;
            }

            break;
        }

        foreach ($view as $entryView) {
            array_splice($entryView->vars['block_prefixes'], $prefixOffset, 0, $this->getBlockPrefix() . '_entry');
        }

        /** @var FormInterface $prototype */
        if ($prototype = $form->getConfig()->getAttribute('prototype')) {
            $prototypeView = $view->vars['prototype'];
            if ($prototypeView->vars['multipart']) {
                $view->vars['multipart'] = true;
            }

            if ($prefixOffset > -3 && $prototype->getConfig()->getOption('block_prefix')) {
                --$prefixOffset;
            }

            array_splice($prototypeView->vars['block_prefixes'], $prefixOffset, 0, $this->getBlockPrefix() . '_entry');
        }
    }
}
