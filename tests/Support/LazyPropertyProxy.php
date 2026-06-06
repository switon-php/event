<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Support;

use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Switon\Core\Lazy;
use Error;

use function call_user_func_array;
use function str_starts_with;

final class LazyPropertyProxy implements Lazy
{
    public function __construct(
        protected ContainerInterface $container,
        protected ReflectionProperty $property,
        protected object             $object,
        protected string             $type,
        protected ?string            $value
    ) {
    }

    protected function resolve(): object
    {
        if ($this->value !== null) {
            $serviceId = str_starts_with($this->value, '#') ? $this->type . $this->value : $this->value;
            $resolved = $this->container->get($serviceId);
        } else {
            $alias = $this->type . '#' . $this->property->getName();
            $resolved = $this->container->has($alias)
                ? $this->container->get($alias)
                : $this->container->get($this->type);
        }

        $this->property->setValue($this->object, $resolved);

        return $resolved;
    }

    public function __get(string $name): mixed
    {
        $service = $this->resolve();

        if (property_exists($service, $name)) {
            return $service->$name;
        }

        $serviceClass = get_class($service);
        throw new Error("Undefined property: {$serviceClass}::\${$name}");
    }

    public function __call(string $name, array $args): mixed
    {
        $service = $this->resolve();

        return call_user_func_array([$service, $name], $args);
    }
}
