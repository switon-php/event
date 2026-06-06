<?php

declare(strict_types=1);

namespace Switon\Eventing;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ContainerInterface;
use Switon\Core\ServiceProviderInterface;

/**
 * Integrates eventing services during application startup.
 *
 * Guidance: Keep PSR interfaces bound to Switon contracts here so application code can depend on stable PSR entry points.
 * Guidance: Boot order matters: listener discovery before event logger registration.
 *
 * Road-signs:
 * - bind PSR dispatcher to EventDispatcherInterface
 * - bind PSR listener provider to ListenerProviderInterface
 * - boot discovery before event logger
 *
 * @see \Switon\Core\ServiceProviderInterface
 * @see \Switon\Eventing\EventDispatcherInterface
 * @see \Switon\Eventing\ListenerProviderInterface
 * @see \Switon\Eventing\ListenerDiscoveryInterface
 * @see \Switon\Eventing\EventLoggerInterface
 */
class ServiceProvider implements ServiceProviderInterface
{
    #[Autowired] protected ListenerDiscoveryInterface $listenerDiscovery;
    #[Autowired] protected EventLoggerInterface $eventLogger;

    /**
     * {@inheritDoc}
     */
    public function register(ContainerInterface $container): void
    {
        $container->set(PsrEventDispatcherInterface::class, EventDispatcherInterface::class);
        $container->set(PsrListenerProviderInterface::class, ListenerProviderInterface::class);
        $container->set(ListenerProvider::class, ListenerProviderInterface::class);
    }

    /**
     * {@inheritDoc}
     */
    public function boot(): void
    {
        $this->listenerDiscovery->discover();
        $this->eventLogger->boot();
    }
}
