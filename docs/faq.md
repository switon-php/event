# Event FAQ {#event-faq}

Short answers for setup, discovery, and common event-flow questions.

> **Prerequisites:** [Event Package](README.md)

## Getting Started {#getting-started}

### How do I create and dispatch one? {#create-and-dispatch}

Define one event class, inject `Psr\EventDispatcher\EventDispatcherInterface`, and call `dispatch()`. See [Quick
Start](README.md#quick-start).

### Are listeners synchronous? {#listeners-synchronous}

Yes. `dispatch()` runs listeners in the current process and call stack. If you need background work, create an
explicit async boundary outside the dispatcher.

### Can `dispatch()` return data? {#return-value}

No. `dispatch()` returns the same event object, not listener return values. Use a service or query object when you
need a result back.

## Configuration {#configuration}

### Where are listeners discovered? {#discovery}

By default, app listeners come from `app/Listener/*Listener.php` and `app/Areas/*/Listener/*Listener.php`. Package listeners also come from Composer metadata under `extra.switon.listeners`. Tune the scan paths through `Switon\Eventing\ListenerDiscoveryInterface.listeners`.

### How do I inspect registered listeners? {#inspect-listeners}

Run `bash bin/console event:listeners`, or narrow it with `bash bin/console event:listeners --filter '<event-fqcn>'`. This command is shipped by `switon/beacon`, not by the Event package itself.

### How do I inspect the current options? {#discover-options}

Run `bash bin/console config:options 'Switon\Eventing\ListenerDiscoveryInterface'` and
`bash bin/console config:options 'Switon\Eventing\EventLoggerInterface'`.

## Logging {#logging}

### How do I control automatic event logging? {#control-logging}

Implement `Switon\Eventing\EventSilent` on an event class to skip logging, add `#[EventLevel(Severity::INFO)]` to change severity, or implement `Switon\Eventing\EventLogInterface` when the event needs custom formatting. Turn the whole `EventLogger` off with `Switon\Eventing\EventLoggerInterface.enabled = false`.

### When should I use `ObservabilityProbe`? {#observability-probe}

Use it on listener classes whose main job is logging, tracing, or metrics. Those listeners stay separate from business
listener counts.

### How do I stop later listeners? {#stop-flow}

Throw `Switon\Core\StopFlow` from a listener when you want an intentional early stop.

## Troubleshooting {#troubleshooting}

### My listener is not firing {#listener-not-firing}

Check these conditions: the method is `public`, it has `#[EventListener]`, it declares exactly one typed parameter, and the class
is under a discovered path or otherwise registered. Untyped or intersection-typed listener methods fail fast during registration.

### Can parent types catch subclass events? {#parent-types}

No. Use a union type or `object` when one listener should cover multiple event classes.

### What happens if no listener provider is wired? {#no-provider}

`dispatch()` returns the original event unchanged and does nothing else.
