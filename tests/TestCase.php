<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Switon\Core\InjectorInterface;
use Switon\Eventing\Tests\Support\Container;

/**
 * Base test case for Event tests.
 */
abstract class TestCase extends BaseTestCase
{
    protected Container $container;
    protected InjectorInterface $injector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
        $this->injector = $this->container->get(InjectorInterface::class);
        $this->setUpContainer();
        $this->injector->inject($this);
    }

    protected function setUpContainer(): void
    {
    }
}
