<?php

declare(strict_types=1);

namespace Switon\Eventing;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\InvalidArgumentException;
use Switon\Core\Reflections;
use Switon\Core\SenderAwareInterface;
use Switon\Eventing\Attribute\EventListener;
use Switon\Eventing\Event\BusinessRegistered;
use Switon\Eventing\Event\ObservabilityRegistered;
use Switon\Eventing\Event\WildcardBusinessRegistered;
use Switon\Eventing\Event\WildcardObservabilityRegistered;
use Switon\Eventing\Exception\InvalidListenerException;
use WeakMap;

use function count;
use function is_array;
use function is_string;

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
 * - on(null, ...) is any sender; on($sender, ...) is sender-scoped
 * - on() infers event type from handler signature
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

    /** @var array<string, PrioritizedListeners> Listeners for any sender, keyed by event FQCN or wildcard. */
    protected array $listeners = [];

    /** @var WeakMap<object, array<string, PrioritizedListeners>> Sender-scoped listeners keyed by emitter instance. */
    protected WeakMap $senderListeners;

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
        $this->senderListeners = new WeakMap();
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

    /** {@inheritDoc} */
    public function getListenersForSender(object $event): PrioritizedListeners
    {
        if (!$event instanceof SenderAwareInterface) {
            return $this->emptyListeners;
        }

        $events = $this->senderListeners[$event->getSender()] ?? null;
        if ($events === null) {
            return $this->emptyListeners;
        }

        return $events[$event::class] ?? $this->emptyListeners;
    }

    /** {@inheritDoc} */
    public function on(?object $sender, callable $handler, int $priority = 0): void
    {
        foreach ($this->resolveHandlerEventTypes($handler) as $eventType) {
            if ($sender !== null && $eventType === self::WILDCARD) {
                InvalidListenerException::raise('Sender-scoped wildcard listeners are not supported.', ['event' => $eventType]);
            }

            if ($sender === null) {
                $listeners = &$this->listeners;
            } else {
                $this->senderListeners[$sender] ??= [];
                $listeners = &$this->senderListeners[$sender];
            }

            ($listeners[$eventType] ??= new PrioritizedListeners())->add($handler, $priority);

            if ($sender === null && is_array($handler) && isset($handler[0], $handler[1]) && is_object($handler[0])) {
                $this->notifyListenerRegistered($handler[0], $handler[1], $eventType);
            }
        }
    }

    /** {@inheritDoc} */
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

        foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
            if (($eventAttributes = $rMethod->getAttributes(EventListener::class)) === []) {
                continue;
            }

            $method = $rMethod->getName();
            $handler = [$listener, $method];
            $eventListener = $eventAttributes[0]->newInstance();

            $this->on(null, $handler, $eventListener->priority);
        }
    }

    /**
     * @return list<string> Event FQCN or {@see self::WILDCARD}
     */
    protected function resolveHandlerEventTypes(callable $handler): array
    {
        $label = Reflections::describeCallable($handler);

        try {
            $rCallable = Reflections::getCallable($handler);
        } catch (InvalidArgumentException) {
            InvalidListenerException::raise('Unsupported listener handler "{handler}".', ['handler' => $label]);
        }

        if (count($parameters = $rCallable->getParameters()) !== 1) {
            InvalidListenerException::raise('Invalid listener "{handler}": exactly one parameter required.', ['handler' => $label]);
        }

        $rType = $parameters[0]->getType();
        if ($rType === null || $rType instanceof ReflectionIntersectionType) {
            InvalidListenerException::raise('Invalid listener "{handler}": typed event parameter required.', ['handler' => $label]);
        }

        try {
            $typeNames = Reflections::getTypeNames($rType);
        } catch (InvalidArgumentException) {
            InvalidListenerException::raise('Invalid listener "{handler}": unsupported event parameter type.', ['handler' => $label]);
        }

        $eventTypes = [];
        foreach ($typeNames as $typeName) {
            $eventTypes[] = $typeName === 'object' ? self::WILDCARD : $typeName;
        }

        return $eventTypes;
    }

    protected function notifyListenerRegistered(object $listener, string $method, string $eventType): void
    {
        $isObservability = $listener instanceof ObservabilityProbe;

        if ($eventType === self::WILDCARD) {
            $registrationEvent = $isObservability
                ? new WildcardObservabilityRegistered($this, $listener::class, $method)
                : new WildcardBusinessRegistered($this, $listener::class, $method);
        } elseif ($isObservability) {
            $registrationEvent = new ObservabilityRegistered($this, $listener::class, $method, $eventType);
        } else {
            $registrationEvent = new BusinessRegistered($this, $listener::class, $method, $eventType);
        }
        if (isset($this->eventDispatcher)) {
            $this->eventDispatcher->dispatch($registrationEvent);
        }

        $key = $listener::class . '::' . $method;
        if ($isObservability) {
            $this->observabilityListenerRegistry[$key] = true;
        } else {
            $this->businessListenerRegistry[$key] = true;
        }
    }

    /** {@inheritDoc} */
    public function getObservabilityListenerRegistry(): array
    {
        return $this->observabilityListenerRegistry;
    }

    /** {@inheritDoc} */
    public function getBusinessListenerRegistry(): array
    {
        return $this->businessListenerRegistry;
    }

    /** {@inheritDoc} */
    public function getListeners(): array
    {
        $out = [];

        if (isset($this->listeners[self::WILDCARD])) {
            foreach ($this->listeners[self::WILDCARD]->getGroups() as $list) {
                foreach ($list as $handler) {
                    $out[self::WILDCARD][] = Reflections::describeCallable($handler);
                }
            }
        }

        foreach ($this->listeners as $event => $byPriority) {
            if ($event === self::WILDCARD) {
                continue;
            }
            foreach ($byPriority->getGroups() as $list) {
                foreach ($list as $handler) {
                    $out[$event][] = Reflections::describeCallable($handler);
                }
            }
        }

        return $out;
    }
}
