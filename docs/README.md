# Event Package {#event-package}

`switon/event` gives Switon apps class-based, synchronous, in-process dispatch with attribute-discovered listeners, wildcard listeners, and optional automatic event logging.

**Key features:**

- **PSR-14 entrypoint:** inject `Psr\EventDispatcher\EventDispatcherInterface` and call `dispatch()`.
- **Class-based events:** the class names the event; the object carries the payload.
- **Attribute discovery:** `#[EventListener]` registers public listener methods from scan paths or Composer `extra.switon.listeners`.
- **Wildcard listeners:** a method parameter typed `object` runs for every event.
- **Priority:** `#[EventListener(priority: N)]` runs lower numbers first.
- **Observability split:** listener classes implementing `ObservabilityProbe` are counted separately from business listeners.
- **Built-in event logging:** `EventLogger` registers itself as a wildcard listener and defaults to `debug` severity.

---

## Installation {#installation}

**Requirements:** PHP 8.3+

```bash
composer require switon/event
```

By default, `Psr\EventDispatcher\EventDispatcherInterface` resolves to `Switon\Eventing\EventDispatcherInterface`, and `Psr\EventDispatcher\ListenerProviderInterface` resolves to `Switon\Eventing\ListenerProviderInterface`.

---

## Quick Start {#quick-start}

Define one event, dispatch it, and handle it with one listener.

```php
namespace App\Event;

final class UserCreated
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
    ) {
    }
}
```

```php
namespace App\Service;

use App\Event\UserCreated;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;

class UserService
{
    #[Autowired] protected EventDispatcherInterface $events;

    public function register(string $email): void
    {
        $this->events->dispatch(new UserCreated(123, $email));
    }
}
```

```php
namespace App\Listener;

use App\Event\UserCreated;
use Switon\Eventing\Attribute\EventListener;

class UserListener
{
    #[EventListener]
    public function onUserCreated(UserCreated $event): void
    {
        // react here
    }
}
```

By default, listener classes under `app/Listener/*Listener.php` and `app/Areas/*/Listener/*Listener.php` are discovered. Listener methods must be public, carry `#[EventListener]`, and declare exactly one parameter typed as a concrete event class, `object`, or a union of event classes. Untyped or intersection-typed listener methods fail fast at registration, and a method that declares zero or more than one parameter fails fast as well.

`dispatch()` runs listeners synchronously in the current process and returns the same event object.

---

## Terms at a Glance {#terms}

- `event class` = one PHP class that names the event and carries its payload
- `listener` = one public method with `#[EventListener]` and exactly one typed parameter
- `wildcard listener` = a listener method whose parameter type is `object`
- `observability listener` = a listener class implementing `ObservabilityProbe`
- `StopFlow` = an intentional early stop for remaining listeners
- `EventLogger` = the built-in wildcard listener that can log dispatched events

---

## Core Concepts {#core-concepts}

### Events {#events}

- Use plain PHP classes; the class name is the identity and the object carries the payload.
- Keep payloads explicit and stable. Prefer IDs, enums, and short summaries over large mutable objects or sensitive raw data.

### Listeners {#listeners}

Listeners are public methods marked with `#[EventListener]`.

- Use a concrete event class for one event, `object` for every event, or a union of event classes.
- `#[EventListener(priority: N)]` runs lower numbers first; same priority keeps registration order.
- Event matching uses the concrete event class only; parent or interface types do not catch subclass events.

### Create and Dispatch {#create-and-dispatch}

- Wildcard listeners run first, then event-specific listeners.
- `dispatch()` returns the same event object; listener return values are ignored.
- Throw `Switon\Core\StopFlow` from a listener to stop remaining listeners intentionally.
- If no listener provider is wired (for example in a minimal bootstrap), `dispatch()` is a no-op.

### Event Logging {#event-logging}

`EventLogger` is itself a wildcard listener; when enabled it logs every dispatched event at `debug` severity through the bound PSR-3 logger.

- Implement `Switon\Eventing\EventSilent` on an event class to skip automatic logging.
- Add `#[EventLevel(Severity::INFO)]` (or any `Switon\Eventing\Severity`) on the event class to change the default severity.
- Implement `Switon\Eventing\EventLogInterface` when the event needs custom log formatting; the default `EventLogger` will defer to it.
- `Switon\Eventing\EventWrapper` serializes public scalar properties by default and skips object-valued properties unless the event implements `JsonSerializable`.
- Listener classes implementing `Switon\Eventing\ObservabilityProbe` are tagged so their invocations are counted separately from business listeners.

---

## Configuration {#configuration}

- `Switon\Eventing\ListenerDiscoveryInterface.listeners` controls app scan paths. The defaults are `@app/Listener/*Listener.php` and `@app/Areas/*/Listener/*Listener.php`; package listeners come from Composer `extra.switon.listeners`.
- `Switon\Eventing\EventLoggerInterface.enabled` controls automatic event logging. Set it to `false` to turn logging off without affecting dispatch.

---

## Option Discovery {#option-discovery}

Use the current option surface directly:

```bash
bash bin/console config:options 'Switon\Eventing\ListenerDiscoveryInterface'
bash bin/console config:options 'Switon\Eventing\EventLoggerInterface'
```

---

## Keep in Mind {#keep-in-mind}

- `dispatch()` is in-process and synchronous; use queues or RPC for async or durable work.
- Listener return values are ignored; use a service or query object when you need a result back.
- Listener methods need `public`, `#[EventListener]`, exactly one typed parameter, and registration through a discovery path or Composer metadata.

---

## Next {#next}

- **To check listener registration and common setup questions:** [FAQ](faq.md#event-faq)
- **To understand why dispatch stays synchronous and observability-aware:** [Design Rationale](design.md#design-rationale)
- **To connect event output to logging policy:** [Log Package](../../log/docs/README.md)
