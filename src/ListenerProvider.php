<?php

declare(strict_types=1);

namespace Switon\Eventing;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Switon\Core\Attribute\Autowired;
use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\Event\BusinessRegistered;
use Switon\Eventing\Event\ObservabilityRegistered;
use Switon\Eventing\Event\WildcardBusinessRegistered;
use Switon\Eventing\Event\WildcardObservabilityRegistered;
use Switon\Eventing\Exception\InvalidListenerException;

use function count;
use function is_string;
use function spl_object_id;

/**
 * Registers and resolves listeners for PSR-14 event dispatching.
 *
 * Supports priority ordering, wildcard listeners, and attribute-based registration via <code>register()</code>.
 * Listener objects implementing <code>ObservabilityProbe</code> are tagged as observability listeners.
 * Event lookup matches the concrete event class only.
 * Guidance: Mark listener methods with <code>#[EventListener]</code> and keep exactly one parameter per listener method, otherwise registration fails fast.
 * Guidance: Listener parameters should be concrete event classes, <code>object</code> for wildcard listeners, or unions of event classes.
 * Guidance: Keep listener registration deterministic; the provider deduplicates listener objects by instance.
 *
 * Road-signs:
 * - register via on/register
 * - reflect EventListener attributes on public methods
 * - object parameter means wildcard
 * - lower priority runs first
 * - emit *Registered events
 *
 * @see \Switon\Eventing\Attribute\EventListener
 * @see \Switon\Eventing\ObservabilityProbe
 * @see \Switon\Eventing\EventDispatcherInterface
 * @see \Switon\Eventing\ListenerDiscoveryInterface::discover() Typical registrar
 * @see \Switon\Eventing\ListenerProviderInterface
 * @see \Switon\Eventing\PrioritizedListeners
 * @see \Switon\Eventing\Event\BusinessRegistered
 * @see \Switon\Eventing\Event\ObservabilityRegistered
 * @see \Switon\Eventing\Event\WildcardBusinessRegistered
 * @see \Switon\Eventing\Event\WildcardObservabilityRegistered
 */
class ListenerProvider implements ListenerProviderInterface
{
    /** Wildcard event key used for listeners that accept <code>object</code>. */
    protected const string WILDCARD = '*';

    /** @var array<string, PrioritizedListeners> */
    protected array $listeners = [];

    /** Shared empty listener bucket returned for event types with no registered listeners. */
    protected PrioritizedListeners $emptyListeners;

    /** @var array<int, object> Registered listener objects keyed by object ID. */
    protected array $registered = [];

    /** @var array<string, true> Observability listener keys. */
    protected array $observabilityListenerRegistry = [];

    /** @var array<string, true> Business listener keys. */
    protected array $businessListenerRegistry = [];

    #[Autowired] protected ContainerInterface $container;
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    public function __construct()
    {
        $this->emptyListeners = new PrioritizedListeners();
    }

    /** {@inheritDoc} */
    public function getListenersForEvent(object $event): PrioritizedListeners
    {
        return $this->listeners[$event::class] ?? $this->emptyListeners;
    }

    /** {@inheritDoc} */
    public function getListenersForWildcard(): PrioritizedListeners
    {
        return $this->listeners[self::WILDCARD] ?? $this->emptyListeners;
    }

    /**
     * Register one listener for one event type.
     *
     * Lower priority values run earlier.
     */
    public function on(string $event, callable $handler, int $priority = 0): void
    {
        $listeners = $this->listeners[$event] ??= new PrioritizedListeners();
        $listeners->add($handler, $priority);
    }

    /**
     * Registers listener methods marked with <code>#[EventListener]</code>.
     *
     * Class-string input is resolved through the container. Re-registering the same object instance is ignored.
     * Listener methods must be public and declare exactly one parameter.
     * Listener parameter types outside event lookup contracts are caller mistakes, not auto-corrected here.
     * Parameter type <code>object</code> registers a wildcard listener; union types register one listener per type.
     *
     * <code>NotFoundExceptionInterface</code> from <code>get()</code> → return, no throw.
     *
     * @throws InvalidListenerException When an attributed method has an invalid signature
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    public function register(string|object $listener): void
    {
        if (is_string($listener)) {
            try {
                $listener = $this->container->get($listener);
            } catch (NotFoundExceptionInterface) {
                // Optional listener from composer extra may reference a class from
                // an optional package; ignore missing classes to keep boot resilient.
                return;
            }
        }

        $objectId = spl_object_id($listener);
        if (isset($this->registered[$objectId])) {
            return;
        }
        $this->registered[$objectId] = $listener;

        $rClass = new ReflectionClass($listener);
        $isObservability = $listener instanceof ObservabilityProbe;

        foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
            // Check if method has #[EventListener] attribute
            if (($eventAttributes = $rMethod->getAttributes(EventListener::class)) === []) {
                continue;
            }

            // Missing #[EventListener] is ignored, but an attributed method with the
            // wrong signature is a configuration bug and should fail fast.
            if (count($rParameters = $rMethod->getParameters()) !== 1) {
                InvalidListenerException::raise(
                    'Invalid listener "{listener}::{method}": exactly one parameter required.',
                    ['listener' => $listener::class, 'method' => $rMethod->getName()]
                );
            }

            $rParameter = $rParameters[0];
            $method = $rMethod->getName();
            $priority = $eventAttributes[0]->newInstance()->priority;

            $rType = $rParameter->getType();
            if ($rType === null || $rType instanceof ReflectionIntersectionType) {
                InvalidListenerException::raise(
                    'Invalid listener "{listener}::{method}": typed event parameter required.',
                    ['listener' => $listener::class, 'method' => $method]
                );
            }

            if ($rType instanceof ReflectionUnionType) {
                /** @var ReflectionNamedType $rNamedType */
                foreach ($rType->getTypes() as $rNamedType) {
                    if (!$rNamedType instanceof ReflectionNamedType) {
                        InvalidListenerException::raise(
                            'Invalid listener "{listener}::{method}": unsupported event parameter type.',
                            ['listener' => $listener::class, 'method' => $method]
                        );
                    }
                    $eventType = $rNamedType->getName() === 'object' ? self::WILDCARD : $rNamedType->getName();
                    $this->on($eventType, [$listener, $method], $priority);

                    // Create and dispatch listener registration event
                    if ($eventType === self::WILDCARD) {
                        $registrationEvent = $isObservability
                            ? new WildcardObservabilityRegistered($listener::class, $method)
                            : new WildcardBusinessRegistered($listener::class, $method);
                    } else {
                        $registrationEvent = $isObservability
                            ? new ObservabilityRegistered($listener::class, $method, $eventType)
                            : new BusinessRegistered($listener::class, $method, $eventType);
                    }
                    $this->eventDispatcher->dispatch($registrationEvent);
                }
            } elseif ($rType instanceof ReflectionNamedType) {
                $type = $rType->getName();
                $eventType = $type === 'object' ? self::WILDCARD : $type;
                $this->on($eventType, [$listener, $method], $priority);

                // Create and dispatch listener registration event
                if ($eventType === self::WILDCARD) {
                    $registrationEvent = $isObservability
                        ? new WildcardObservabilityRegistered($listener::class, $method)
                        : new WildcardBusinessRegistered($listener::class, $method);
                } elseif ($isObservability) {
                    $registrationEvent = new ObservabilityRegistered($listener::class, $method, $eventType);
                } else {
                    $registrationEvent = new BusinessRegistered($listener::class, $method, $eventType);
                }
                $this->eventDispatcher->dispatch($registrationEvent);
            } else {
                InvalidListenerException::raise(
                    'Invalid listener "{listener}::{method}": unsupported event parameter type.',
                    ['listener' => $listener::class, 'method' => $method]
                );
            }

            // Record registered listener for debugging
            $key = $listener::class . '::' . $method;
            if ($isObservability) {
                $this->observabilityListenerRegistry[$key] = true;
            } else {
                $this->businessListenerRegistry[$key] = true;
            }
        }
    }

    /**
     * @return array<string, true>
     */
    public function getObservabilityListenerRegistry(): array
    {
        return $this->observabilityListenerRegistry;
    }

    /**
     * @return array<string, true>
     */
    public function getBusinessListenerRegistry(): array
    {
        return $this->businessListenerRegistry;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getListeners(): array
    {
        $out = [];

        // * first
        if (isset($this->listeners[self::WILDCARD])) {
            foreach ($this->listeners[self::WILDCARD]->getGroups() as $priority => $list) {
                foreach ($list as $handler) {
                    $out[self::WILDCARD][] = $this->normalizeCallable($handler);
                }
            }
        }

        foreach ($this->listeners as $event => $byPriority) {
            if ($event === self::WILDCARD) {
                continue;
            }
            foreach ($byPriority->getGroups() as $priority => $list) {
                foreach ($list as $handler) {
                    $out[$event][] = $this->normalizeCallable($handler);
                }
            }
        }

        return $out;
    }

    protected function normalizeCallable(callable $handler): string
    {
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            [$target, $method] = $handler;

            if (is_object($target)) {
                return $target::class . '::' . $method;
            }

            return $target . '::' . $method;
        }

        if (is_string($handler)) {
            return $handler;
        }

        if ($handler instanceof Closure) {
            return 'Closure';
        }

        if (is_object($handler)) {
            return $handler::class . '::__invoke';
        }

        return 'callable';
    }

}
