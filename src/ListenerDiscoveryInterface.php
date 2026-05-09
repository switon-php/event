<?php

declare(strict_types=1);

namespace Switon\Eventing;

/**
 * Discovers and registers event listeners from all supported sources.
 *
 * Keep package listeners in Composer metadata and app listeners in scanner paths so bootstrap stays deterministic.
 *
 * Road-signs:
 * - scan app listener paths
 * - load composer extra listeners
 * - register into listener provider
 *
 * @see \Switon\Eventing\ListenerDiscovery
 * @see \Switon\Eventing\ListenerProviderInterface
 * @see \Switon\Eventing\ServiceProvider
 */
interface ListenerDiscoveryInterface
{
    /** Discovers and registers listeners into the provider. */
    public function discover(): void;
}
