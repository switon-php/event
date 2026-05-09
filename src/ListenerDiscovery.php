<?php

declare(strict_types=1);

namespace Switon\Eventing;

use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\ClassScannerInterface;

/**
 * Discovers listeners from application scan paths and framework package Composer extra metadata.
 *
 * Guidance: Put package listeners in composer.json <code>extra.switon.listeners</code>, and use scanner paths for app listeners.
 *
 * Road-signs:
 * - scanner loads app listeners from configured paths
 * - composer extra loads package listeners
 * - each class is registered into listener provider
 *
 * @see \Switon\Eventing\ListenerDiscoveryInterface
 * @see \Switon\Core\ClassScannerInterface
 * @see \Switon\ComposerExtra\ComposerExtraInterface
 * @see \Switon\Eventing\ListenerProviderInterface
 * @see \Switon\Eventing\Attribute\EventListener
 * @see \Switon\Eventing\ServiceProvider
 */
class ListenerDiscovery implements ListenerDiscoveryInterface
{
    #[Autowired] protected ClassScannerInterface $classScanner;
    #[Autowired] protected ComposerExtraInterface $composerExtra;
    #[Autowired] protected ListenerProviderInterface $listenerProvider;
    /** @var array<string, string> */
    #[Autowired] protected array $listeners
        = [
            '@app/Listener/*Listener.php' => 'App\\Listener\\*Listener',
            '@app/Areas/*/Listener/*Listener.php' => 'App\\Areas\\*\\Listener\\*Listener',
        ];

    /** {@inheritDoc} */
    public function discover(): void
    {
        foreach ($this->classScanner->scan($this->listeners) as $className) {
            $this->listenerProvider->register($className);
        }

        foreach ($this->composerExtra->getClasses('switon.listeners') as $class) {
            $this->listenerProvider->register($class);
        }
    }
}
