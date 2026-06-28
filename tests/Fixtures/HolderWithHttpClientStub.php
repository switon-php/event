<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Fixtures;

/** @internal Typical service with an HTTP client on {@see $httpClient}. */
final class HolderWithHttpClientStub
{
    public function __construct(public HttpClientOwnerStubInterface $httpClient)
    {
    }
}
