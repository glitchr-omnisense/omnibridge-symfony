<?php

namespace Base\Form\Traits;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @deprecated in favor of using Symfony Form Flows
 */
trait FormProcessorTrait
{
    protected function getPost(): array
    {
        return $this->getSession()["POST"] ?? [];
    }

    protected function getFiles(): array
    {
        return $this->getSession()["FILES"] ?? [];
    }

    protected function getUploadedFiles(): array
    {
        $uploadedFiles = [];

        if ($session = $this->getSession()) {
            $files = $session["FILES"];
            foreach ($files as $name => $file) {
                $uploadedFiles[$name] = new UploadedFile(
                    $file["tmp_name"],
                    $file["name"],
                    $file["type"],
                    $file["error"]
                );
            }
        }

        return $uploadedFiles;
    }


    /**
     * @return true
     */
    protected function hasSession()
    {
        return true;
        // $session = $this->bindSession();
        // if(!$session) return false;
        // if(!$session->has("_form_flow"))
        //     $session->set("_form_flow", []);

        // $flowFormId  = $this->getOption("form_flow_id") ?? null;
        // $flowForm    = $session->get("_form_flow");
        // return array_key_exists($flowFormId, $flowForm);
    }

    protected function getSession(?Request $request = null): ?array
    {
        return null;
        // $session = $this->bindSession($request?->getSession());
        // if (!$session) return null;

        // $flowFormId  = $this->getOption("form_flow_id") ?? null;
        // $flowForm    = $session->get("_form_flow");
        // return $flowForm[$flowFormId] ?? null;
    }

    /**
     * @param array $entry
     * @param SessionInterface|null $session
     * @return $this
     */
    protected function setSession(array $entry, ?SessionInterface $session = null)
    {
        return $this;
        // $session = $this->bindSession($session);

        // $flowFormId            = $this->getOption("form_flow_id") ?? null;
        // $flowForm              = $session->get("_form_flow");
        // $flowForm[$flowFormId] = $entry;

        // $session->set("_form_flow", $flowForm);
    }

    /**
     * @param Request $request
     * @return $this
     */
    protected function appendPost(Request $request)
    {
        // Disabled: the request's data is not copied into the form's session.
        return $this;
    }

    /**
     * @param Request $request
     * @return $this
     */
    protected function appendFiles(Request $request)
    {
        // Disabled: the request's data is not copied into the form's session.
        return $this;
    }

    /**
     * @param SessionInterface|null $session
     * @return true
     */
    public function killSession(?SessionInterface $session = null)
    {
        return true;
        // $session = $this->bindSession($session);
        // if(!$session) return false;

        // $flowFormId  = $this->getOption("form_flow_id") ?? null;
        // $flowForm = $session->get("_form_flow");

        // // Delete temporary files
        // $formFiles = $flowForm[$flowFormId]["FILES"] ?? [];
        // foreach($formFiles as $file) {

        //     // Check if really a tmp file
        //     if( str_starts_with($file["tmp_name"], sys_get_temp_dir()) )
        //         unlink($file["tmp_name"]);
        // }

        // // Delete corresponding formId
        // unset($flowForm[$flowFormId]);
        // $session->set("_form_flow", $flowForm);

        // return true;
    }
}
