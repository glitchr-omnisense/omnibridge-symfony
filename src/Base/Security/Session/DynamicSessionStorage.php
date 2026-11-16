<?php

namespace Base\Security\Session;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class DynamicSessionStorage extends NativeSessionStorage
{
    public function setOptions(array $options): void
    {
        // Detect domain from Host header
        $domain = null;
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            // Strip port if any
            $host = explode(':', $host, 2)[0];
            $domain = $host;
        }

        // Decode allowed domains from ENV if present
        $validDomains = [];
        if (!empty($_ENV['HTTP_DOMAIN'])) {
            $decoded = json_decode($_ENV['HTTP_DOMAIN'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $validDomains = $decoded;
            } else {
                // Fallback: single string domain
                $validDomains = [$_ENV['HTTP_DOMAIN']];
            }
        }

        // Apply domain only if validated
        if ($domain && (!count($validDomains) || in_array($domain, $validDomains, true))) {
            $options['cookie_domain'] = $domain;
        }

        parent::setOptions($options);
    }
}
