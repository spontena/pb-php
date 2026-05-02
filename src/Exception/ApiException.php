<?php

declare(strict_types=1);

namespace Spontena\PbPhp\Exception;

use GuzzleHttp\Exception\BadResponseException;

class ApiException extends PandorabotsException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $responseBody,
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function fromGuzzle(BadResponseException $e): self
    {
        $response = $e->getResponse();
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        // Build a sanitized request descriptor — drop the query string since
        // user_key / botkey are passed there and we must not leak them via
        // exception messages, logs, or stack traces.
        $request = $e->getRequest();
        $uri = $request->getUri();
        $descriptor = sprintf(
            '%s %s://%s%s',
            $request->getMethod(),
            $uri->getScheme(),
            $uri->getHost(),
            $uri->getPath(),
        );

        // Intentionally do NOT pass $e as previous: its default __toString()
        // includes the full URL (with query) which would leak credentials.
        return new self(
            sprintf('Pandorabots API returned HTTP %d for %s: %s', $status, $descriptor, $body),
            $status,
            $body,
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    public function getDecodedBody(): ?\stdClass
    {
        $decoded = json_decode($this->responseBody);
        return $decoded instanceof \stdClass ? $decoded : null;
    }
}
