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
final class AnyTourAndromedaNetworkTransportFailure extends RuntimeException {}
