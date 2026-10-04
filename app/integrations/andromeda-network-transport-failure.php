<?php
declare(strict_types=1);

/**
 * Explicit marker for a proven network/transport failure.
 *
 * Throw this type only when an HTTP request was actually handed to the transport
 * layer and no supplier/application response is available because transfer execution
 * failed. Local validation/setup, HTTP responses, supplier errors, schema failures
 * and response-size guards must use other exception types/messages.
 */
final class AnyTourAndromedaNetworkTransportFailure extends RuntimeException
{
    private ?int $curlError = null;

    /** Numeric transfer fact only; never retain curl_error(), URL or response. */
    public static function fromCurlError(int $errno): self
    {
        $failure = new self('ANDROMEDA_NETWORK_TRANSPORT_FAILURE');
        $failure->curlError = $errno > 0 && $errno <= 999 ? $errno : null;
        return $failure;
    }

    public function curlErrorCode(): ?int { return $this->curlError; }
}
