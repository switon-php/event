# Switon Event Package

PSR-14 event dispatching and listener discovery for Switon Framework.

## Installation

```bash
composer require switon/event
```

**Requirements:** PHP 8.3+

## Quick Start

```php
use App\Event\UserCreated;
use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Attribute\EventListener;

class UserService
{
    #[Autowired] protected EventDispatcherInterface $events;

    public function register(string $email): void
    {
        $this->events->dispatch(new UserCreated(123, $email));
    }
}

class UserListener
{
    #[EventListener]
    public function onUserCreated(UserCreated $event): void
    {
        // react to the event
    }
}
```

Docs: https://docs.switon.dev/latest/event

## License

MIT.
