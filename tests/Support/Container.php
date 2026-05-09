<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Support;

use Psr\Log\LoggerInterface;
use Switon\Core\App;
use Switon\Core\ContainerInterface;
use Switon\Core\ContextManagerInterface;
use Switon\Core\PathAlias;
use Switon\Core\PathAliasInterface;
use Switon\Di\Container as DiContainer;
use Switon\Di\ServiceProvider as DiServiceProvider;

final class Container extends DiContainer
{
    public function __construct(array $definitions = [])
    {
        parent::__construct($definitions);

        $diProvider = new DiServiceProvider();
        $diProvider->register($this);
        $diProvider->boot();

        App::setContainer($this);

        if (!isset($this->definitions[ContainerInterface::class])) {
            $this->set(ContainerInterface::class, $this);
        }
        if (!isset($this->definitions[\Switon\Core\ContainerInterface::class])) {
            $this->set(\Switon\Core\ContainerInterface::class, $this);
        }

        if (!isset($this->definitions[PathAliasInterface::class])) {
            $pathAlias = new PathAlias();
            $pathAlias->set('@view', sys_get_temp_dir() . '/switon_event_view_' . uniqid());
            $pathAlias->set('@public', sys_get_temp_dir() . '/switon_event_public_' . uniqid());
            $pathAlias->set('@runtime', sys_get_temp_dir() . '/switon_event_runtime_' . uniqid());
            $this->set(PathAlias::class, $pathAlias);
            $this->set(PathAliasInterface::class, $pathAlias);
        }

        if (!isset($this->definitions[ContextManagerInterface::class])) {
            $this->set(ContextManagerInterface::class, new InMemoryContextManager());
        }

        if (!isset($this->definitions[LoggerInterface::class])) {
            $this->set(LoggerInterface::class, new \Psr\Log\NullLogger());
        }
    }
}
