<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Support;

use ReflectionClass;
use ReflectionNamedType;
use Switon\Core\ContextAware;
use Switon\Core\ContextManagerInterface;

final class InMemoryContextManager implements ContextManagerInterface
{
    /** @var array<int, object> */
    private array $contexts = [];

    public function getContext(ContextAware $object, int $cid = 0): mixed
    {
        $objectId = spl_object_id($object);

        if (isset($this->contexts[$objectId])) {
            return $this->contexts[$objectId];
        }

        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod('getContext');
        $returnType = $method->getReturnType();

        if (!$returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
            return $this->contexts[$objectId] = new \stdClass();
        }

        $contextClass = $returnType->getName();

        return $this->contexts[$objectId] = new $contextClass();
    }
}
