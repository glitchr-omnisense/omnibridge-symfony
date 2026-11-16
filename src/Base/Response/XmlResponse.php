<?php

namespace Base\Response;

use DOMDocument;
use Symfony\Component\HttpFoundation\Response;

class XmlResponse extends Response
{
    /**
     * @param $data
     * @param int $status
     * @param array $headers
     */
    public function __construct($data = null, int $status = 200, array $headers = [])
    {
        parent::__construct($data, $status, array_merge($headers, ['Content-Type' => 'text/xml']));
    }

    /**
     * Factory method for chainability.
     *
     * Example:
     *
     *     return JsonResponse::fromJsonString('{"key": "value"}')
     *         ->setSharedMaxAge(300);
     *
     * @param string $source
     * @param int|null $options
     * @param int $status The response status code
     * @param array $headers An array of response headers
     *
     * @return static
     */
    public static function fromXmlString(string $source, ?int $options = null, int $status = 200, array $headers = []): Response
    {
        $dom = new DOMDocument();
        $dom->loadXML($source, $options);
        return new static($dom->saveXML(), $status, $headers);
    }

    public static function fromXmlFile(string $filename, ?int $options = null, int $status = 200, array $headers = []): Response
    {
        $dom = new DOMDocument();
        $dom->load($filename, $options);
        return new static($dom->saveXML(), $status, $headers);
    }
}
