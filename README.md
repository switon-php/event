# Switon Event Package

[![CI](https://img.shields.io/github/actions/workflow/status/switon-php/event/ci.yml?branch=main&label=CI)](https://github.com/switon-php/event/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's PSR-14 event package for application dispatch, listener discovery, and event logging.

## Highlights

- **Wildcard listeners:** one listener can handle every event with `object`.
- **Attribute-based listeners:** `#[EventListener]` registers listener methods.
- **Automatic listener discovery:** app scan paths and `extra.switon.listeners` entries are included.
- **Event log level:** events can carry a default PSR-3 level.
- **Early-stop support:** listeners can halt the remaining chain when needed.
- **Automatic event logging:** events can be logged automatically with structured output.

## Installation

```bash
composer require switon/event
```

## Quick Start

```php
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Eventing\Attribute\EventListener;

final class UserRegistered
{
    public function __construct(public int $userId, public string $email)
    {
    }
}

class UserService
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;

    public function register(string $email): void
    {
        $this->eventDispatcher->dispatch(new UserRegistered(123, $email));
    }
}

class UserListener
{
    #[EventListener]
    public function onUserRegistered(UserRegistered $event): void
    {
        // react to the event
    }
}
```

Docs: https://docs.switon.dev/latest/event

## License

MIT.
