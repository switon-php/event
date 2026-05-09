<?php

declare(strict_types=1);

namespace Switon\Eventing;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ReflectionClass;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Categorized;
use Switon\Core\ClassName;
use Switon\Core\Categorizable;
use Switon\Eventing\Attribute\EventLevel;
use Throwable;

/**
 * Logs dispatched events to a PSR-3 logger with category and level resolution.
 *
 * Category resolution: <code>Categorizable::getCategory()</code> or <code>EventLoggerInterface::categoryForClass()</code>. Level resolution: <code>#[EventLevel]</code> or <code>LogLevel::DEBUG</code>.
 * Events implementing <code>EventSilent</code> are skipped; <code>EventLogInterface</code> handles its own logging.
 * Guidance: Keep event payload logging structured and non-sensitive; use <code>EventSilent</code> or custom <code>EventLogInterface</code> logic when default payload output is not appropriate.
 *
 * Road-signs:
 * - boot registers wildcard listener
 * - EventSilent skips automatic logging
 * - EventLogInterface handles self-logging
 * - EventLevel controls severity
 * - Categorizable overrides category
 *
 * @see \Switon\Eventing\EventLoggerInterface
 * @see \Switon\Eventing\ListenerProviderInterface
 * @see \Switon\Eventing\EventWrapper
 * @see \Switon\Eventing\EventDispatcherInterface
 * @see \Switon\Eventing\EventLogInterface
 * @see \Switon\Eventing\Attribute\EventLevel
 * @see \Switon\Eventing\EventSilent
 * @see \Switon\Core\Categorizable
 * @see \Switon\Logging\Event\LoggerLogged
 */
class EventLogger implements EventLoggerInterface, ObservabilityProbe
{
    #[Autowired] protected ListenerProviderInterface $listenerProvider;
    #[Autowired] protected LoggerInterface $logger;

    /** @var array<string, string> Cached log levels by event class. */
    #[Autowired] protected array $levels = [];

    /** @var array<string, string> Cached categories by event class. */
    #[Autowired] protected array $categories = [];

    /** Enables/disables automatic event logging listener registration. */
    #[Autowired] protected bool $enabled = true;

    /**
     * Logs one event with resolved category and level.
     *
     * Event-log formatting or event payload extraction failures are downgraded to one
     * logger-side info record so observability problems stay visible without adding a
     * second reporting channel here.
     * Keep this path single-hop on purpose: do not add nested fallback reporting such as
     * <code>error_log()</code> unless the component contract changes.
     */
    public function onEvent(object $event): void
    {
        // Skip silent events (e.g., LoggerLogged to prevent infinite recursion)
        if ($event instanceof EventSilent) {
            return;
        }

        try {
            $name = $event::class;

            // If event implements EventLogInterface, it handles its own logging
            if ($event instanceof EventLogInterface) {
                $event->log($event, $this->logger);
                return;
            }

            if (!isset($this->categories[$name])) {
                $level = $this->levels[$name] = $this->parseLevel($name);
                $category = $this->categories[$name] = $this->parseCategory($event, $name);
            } else {
                $level = $this->levels[$name];
                $category = $this->categories[$name];
            }

            $wrapper = new EventWrapper($event);
            $this->logger->$level(Categorized::of($category), $wrapper->jsonSerialize());
        } catch (Throwable $e) {
            // Keep logger failures on the same channel; no secondary fallback here.
            $this->logger->info('Event logging failed: {error}', [
                'error' => $e->getMessage(),
                'event' => $event::class,
            ]);
        }
    }

    /**
     * Resolves event log level from class metadata.
     *
     * @throws \ReflectionException
     */
    protected function parseLevel(string $eventClass): string
    {
        $rClass = new ReflectionClass($eventClass);
        if (($attributes = $rClass->getAttributes(EventLevel::class)) !== []) {
            /** @var EventLevel $eventLevel */
            $eventLevel = $attributes[0]->newInstance();
            return $eventLevel->severity->value;
        }
        return LogLevel::DEBUG;
    }

    /** {@inheritDoc} */
    public function categoryForClass(string $eventClass): string
    {
        return ClassName::dotId($eventClass, ['\\Event\\']);
    }

    /** Resolves category from event contract or class name convention. */
    protected function parseCategory(object $event, string $eventClass): string
    {
        if ($event instanceof Categorizable) {
            return $event->getCategory();
        }
        return $this->categoryForClass($eventClass);
    }

    public function boot(): void
    {
        if ($this->enabled) {
            $this->listenerProvider->on('*', [$this, 'onEvent']);
        }
        $this->logger->info('Event logger boot', [
            'enabled' => $this->enabled,
        ]);
    }

    public function getCategoryMapping(): array
    {
        return $this->categories;
    }

    public function getLevelMapping(): array
    {
        return $this->levels;
    }
}
