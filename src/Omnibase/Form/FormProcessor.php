<?php

namespace Omnibridge\Symfony\Omnibase\Form;

use Base\Database\Entity\EntityHydrator;
use Base\Form\Common\FormModelInterface;
use Omnibridge\Symfony\Omnibase\Form\Traits\FormProcessorTrait;
use Base\Traits\BaseTrait;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormInterface;

/** @TODO: Implement multi-step submission including $_POST,$_GET,$_FILES forwarding */
class FormProcessor implements FormProcessorInterface
{
    use BaseTrait;
    use FormProcessorTrait;

    protected FormInterface $form;

    public array $flowSessions = [];
    public array $flowCallbacks = [];

    public function __construct(FormInterface $form)
    {
        $this->form = $form;

        $this->onDefaultCallback = null;
        $this->onInvalidCallback = null;
        $this->onSubmitCallbacks = [];
    }

    public function getForm(): FormInterface
    {
        return $this->form;
    }

    public function getFormType(): string
    {
        return get_class($this->form);
    }

    public function getOption(string $name): mixed
    {
        return $this->getOptions()[$name] ?? null;
    }

    public function getOptions(): array
    {
        return $this->form->getConfig()->getType()->getOptionsResolver()->resolve();
    }

    public function getData(?string $childName = null): mixed
    {
        $form = $childName ? $this->form->get($childName) : $this->form;
        $data = $form->getData();

        // Special case for buttons
        if ($form && $data instanceof FormModelInterface) {

            object_init($data);

            foreach ($form->all() as $childName => $child) { // @TODO Use array_map_recursive()
                if (!$child instanceof ClickableInterface) {
                    continue;
                }
                object_hydrate($data, [$childName => $child->isClicked()]);
            }
        }

        return $data;
    }

    public function setData(mixed $data): self
    {
        $array = is_array($data) ? $data : $this->getEntityHydrator()->dehydrate($data) ?? [];
        $array = array_map(fn($c) => $c instanceof PersistentCollection ? $this->getEntityHydrator()->dehydrate($c) : $c, $array);

        $formData = $this->form->getData();
        if (is_object($this->form->getData())) {
            $this->form->setData(object_hydrate($this->form->getData(), $array));
        } elseif (!$this->form->isSubmitted()) {
            $this->form->setData($formData ?? $data);
        }

        if (!$this->form->isSubmitted()) {
            foreach ($this->form->all() as $childName => $child) { // @TODO Use array_map_recursive()
                $child->setData($array[$childName] ?? null);
            }
        }

        return $this;
    }

    public function hydrate(mixed $data): mixed
    {
        if ($data == null) {
            return null;
        }

        $ignoredFields = [];
        foreach ($this->form as $childName => $child) {
            if (!$child->getConfig()->getMapped()) {
                $ignoredFields[] = $childName;
            }
        }

        return $this->getEntityHydrator()->hydrate($data, $this->form->getData(), $ignoredFields, EntityHydrator::CLASS_METHODS | EntityHydrator::FETCH_ASSOCIATIONS);
    }

    protected $onDefaultCallback;

    public function onDefault(callable $callback): static
    {
        $this->onDefaultCallback = $callback;
        return $this;
    }

    protected $onInvalidCallback;

    public function onInvalid(callable $callback): static
    {
        $this->onInvalidCallback = $callback;
        return $this;
    }

    protected array $onSubmitCallbacks;

    public function onSubmit(callable ...$callbacks): static
    {
        $this->onSubmitCallbacks = $callbacks;
        return $this;
    }

    protected ?Response $response;

    public function hasResponse(): bool
    {
        return $this->response instanceof Response;
    }

    public function getResponse(): Response
    {
        if (!$this->response instanceof Response) {
            if ($this->form->isSubmitted()) {
                if ($this->form->isValid()) {
                    throw new Exception("Unexpected returned value from " . get_class($this) . "::onSubmit(" . $this->form->getName() . ")#" . ($this->getStep() - 1) . ": instance of " . Response::class . " expected");
                }

                throw new Exception("Unexpected returned value from " . get_class($this) . "::onInvalid(" . $this->form->getName() . ")#" . ($this->getStep() - 1) . ": instance of " . Response::class . " expected");
            }

            throw new Exception("Unexpected returned value from " . get_class($this) . "::onDefault(" . $this->form->getName() . "): instance of " . Response::class . " expected");
        }

        return $this->response;
    }

    public function getDto(): ?FormModelInterface
    {
        $data = $this->getData();
        return $data instanceof FormModelInterface ? $data : null;
    }


    protected $entity;

    /**
     * @return mixed
     */
    public function getEntity()
    {
        return $this->entity;
    }

    public function handleRequest(Request $request): static
    {
        if (!$this->form) {
            throw new Exception("No form provided in FormProcessor");
        }

        $this->form->handleRequest($request);

        $step = 0;
        $stepMax = 0;

        $step = $this->getStep();
        $stepMax = $this->getStepMax();

        $formType = $this->getFormType();
        $session = $this->getSession();

        $nextStep = true; //false;

        $submitCount = count($this->onSubmitCallbacks);
        if ($stepMax > 0 && $submitCount > 1 && $stepMax != $submitCount) {
            throw new Exception("Number of FormProcessor::onSubmit() calls is not matching the number of steps in " . $formType);
        }

        // Bind session to form (retrieve previous step information)
        // $this->bindSession($request->getSession());
        // $formSession = $this->getSession();

        // Check if tmp files are still available..
        // $fileExpirationTriggered = false;
        // $formFiles = $formSession["FILES"] ?? [];
        // foreach ($formFiles as $file)
        //     if ($fileExpirationTriggered = !file_exists($file["tmp_name"])) break;

        // // If form expired/session got destroyed
        // if ($step && ($fileExpirationTriggered || !$session)) {

        //     $notification = new Notification("The form you were filling has expired. Please try again.");
        //     $notification->send("danger");

        //     $this->form->reset();
        // }

        // Go to next step
        // if($nextStep) {

        //     // Handle multi-step form
        //     $step = $this->form->getNextStep();
        //     $this->form->setStep($step);

        //     // Create new form if required
        //     $this->appendFiles($request);
        //     $this->appendPost($request);
        // }

        if ($this->form->isSubmitted()) {
            // Prepare response either calling onDefault or onSubmit step
            if ($this->form->isValid()) {
                $this->response = count($this->onSubmitCallbacks) > $step - 1 ? call_user_func($this->onSubmitCallbacks[$step - 1], $this, $request) : null;
            } else {
                $this->response = $this->onInvalidCallback ? call_user_func($this->onInvalidCallback, $this, $request) : null;
            }

            if ($step >= $stepMax) {
                $this->killSession($session);
            }
        }

        if (!isset($this->response)) {
            $this->response = $this->onDefaultCallback ? call_user_func($this->onDefaultCallback, $this, $request) : null;
        }

        if (is_string($this->response)) {
            $this->response = new Response($this->response);
        }

        // Create one view.. make sure assets are loaded
        $this->getForm()->createView();

        return $this;
    }

    // Form flow methods
    public function reset()
    {
        $this->flowCallbacks = [];
        $this->setStep(0);
    }

    /**
     * @return string|null
     */
    public function getToken()
    {
        $options = $this->getConfig()->getOptions();
        $name = $options['flow_form_id'] ?? "";
        $token = $_POST[$name] ?? "";

        if (!empty($token) && preg_match("/(.*)#([0-9]*)/", $token, $matches)) {
            return $matches[1];
        }

        return rand_str();
    }

    public function addStep(callable $callback)
    {
        $this->flowCallbacks[] = $callback;
    }

    /**
     * @return null
     */
    public function addConfirmStep()
    {
        $options = $this->getConfig()->getOptions();
        return $this->addStep($options, function (FormBuilderInterface $builder, array $options) {
            //@TODO..
        });
    }

    /**
     * @return int
     */
    public function getPreviousStep()
    {
        $step = $this->getStep();
        return ($step > 0) ? $step - 1 : 0;
    }

    /**
     * @return int|null
     */
    public function getStepMax()
    {
        return count($this->flowCallbacks);
    }

    /**
     * @return int|null
     */
    public function getNextStep()
    {
        $step = $this->getStep();
        $stepMax = $this->getStepMax();
        return ($step < $stepMax) ? $step + 1 : $stepMax;
    }

    /**
     * @param $step
     * @return void
     */
    public function setStep($step)
    {
        // $token = $this->getToken();

        // $name  = $options['flow_form_name'] ?? "";
        // if(empty($name)) throw new Exception("Unexpected option provided for \"flow_form_name\"");

        // $_POST[$name] = $token . "#" . $step;
    }

    /**
     * @return int
     */
    public function getStep()
    {
        return 1;
    }
}
