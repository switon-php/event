<?php

declare(strict_types=1);

namespace Switon\Eventing\Tests\Unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Core\ClassScannerInterface;
use Switon\Core\FilesystemInterface;
use Switon\Eventing\ListenerDiscovery;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\Tests\TestCase;
use ArrayIterator;

#[AllowMockObjectsWithoutExpectations]
class ListenerDiscoveryTest extends TestCase
{
    protected ListenerProviderInterface&MockObject $listenerProvider;
    protected FilesystemInterface&MockObject $filesystem;

    protected function setUpContainer(): void
    {
        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->method('all')->willReturn([]);
        $composerExtra->method('collect')->willReturn([]);
        $this->container->replace(ComposerExtraInterface::class, $composerExtra);

        parent::setUpContainer();
        $this->container->get(\Switon\Core\PathAliasInterface::class)->set('@app', '/app');
        $this->listenerProvider = $this->createMock(ListenerProviderInterface::class);
        $this->filesystem = $this->createMock(FilesystemInterface::class);
        $this->container->set(ListenerProviderInterface::class, $this->listenerProvider);
        $this->container->set(FilesystemInterface::class, $this->filesystem);
    }

    protected function makeDiscovery(array $listeners): ListenerDiscovery
    {
        return $this->container->make(ListenerDiscovery::class, [
            'listeners' => new ArrayIterator($listeners),
        ]);
    }

    public function testDiscoverScansAppListenersAndRegistersComposerListeners(): void
    {
        $classScanner = $this->createMock(ClassScannerInterface::class);
        $classScanner->expects($this->once())
            ->method('scan')
            ->willReturn([]);

        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->expects($this->once())
            ->method('collect')
            ->with('switon.listeners')
            ->willReturn([
                'App\\Listener\\AListener',
                'App\\Listener\\BListener',
                'App\\Listener\\CListener',
            ]);

        $provider = $this->createMock(ListenerProviderInterface::class);
        $added = [];
        $provider->expects($this->exactly(3))
            ->method('register')
            ->willReturnCallback(static function (string $class) use (&$added): void {
                $added[] = $class;
            });

        $this->container->set(ClassScannerInterface::class, $classScanner);
        $this->container->set(ComposerExtraInterface::class, $composerExtra);
        $this->container->set(ListenerProviderInterface::class, $provider);
        $discovery = $this->container->make(ListenerDiscovery::class);

        $discovery->discover();

        $this->assertSame(
            ['App\\Listener\\AListener', 'App\\Listener\\BListener', 'App\\Listener\\CListener'],
            $added
        );
    }

    public function testDiscoverFiltersInvalidOrEmptyListenerValues(): void
    {
        $classScanner = $this->createMock(ClassScannerInterface::class);
        $classScanner->expects($this->once())->method('scan')->willReturn([]);

        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->method('collect')
            ->with('switon.listeners')
            ->willReturn(['App\\Listener\\ValidListener']);

        $provider = $this->createMock(ListenerProviderInterface::class);
        $provider->expects($this->once())
            ->method('register')
            ->with('App\\Listener\\ValidListener');

        $this->container->set(ClassScannerInterface::class, $classScanner);
        $this->container->set(ComposerExtraInterface::class, $composerExtra);
        $this->container->set(ListenerProviderInterface::class, $provider);
        $discovery = $this->container->make(ListenerDiscovery::class);

        $discovery->discover();
    }

    public function testDiscoverStillScansWhenComposerExtraHasNoListeners(): void
    {
        $classScanner = $this->createMock(ClassScannerInterface::class);
        $classScanner->expects($this->once())->method('scan')->willReturn([]);

        $composerExtra = $this->createMock(ComposerExtraInterface::class);
        $composerExtra->method('collect')->with('switon.listeners')->willReturn([]);

        $provider = $this->createMock(ListenerProviderInterface::class);
        $provider->expects($this->never())->method('register');

        $this->container->set(ClassScannerInterface::class, $classScanner);
        $this->container->set(ComposerExtraInterface::class, $composerExtra);
        $this->container->set(ListenerProviderInterface::class, $provider);
        $discovery = $this->container->make(ListenerDiscovery::class);

        $discovery->discover();
    }

    public function testAppScanWithDirectClassName(): void
    {
        $this->defineClass('App\\Listener\\MyListener');

        $discovery = $this->makeDiscovery(['App\Listener\MyListener']);

        $this->listenerProvider->expects($this->once())
            ->method('register')
            ->with('App\Listener\MyListener');

        $discovery->discover();
    }

    public function testAppScanWithGlobPattern(): void
    {
        $this->defineClass('App\\Listener\\UserListener');

        $this->filesystem->expects($this->once())
            ->method('glob')
            ->with('@app/Listener/*Listener.php')
            ->willReturn(['/app/Listener/UserListener.php']);

        $this->listenerProvider->expects($this->once())
            ->method('register')
            ->with('App\Listener\UserListener');

        $this->makeDiscovery(['@app/Listener/*Listener.php' => 'App\\Listener\\*Listener'])->discover();
    }

    public function testAppScanWithLegacyFormat(): void
    {
        $this->defineClass('App\\Areas\\Admin\\Listener\\AdminListener');

        $this->filesystem->expects($this->once())
            ->method('glob')
            ->with('@app/Areas/*/Listener/*Listener.php')
            ->willReturn(['/app/Areas/Admin/Listener/AdminListener.php']);

        $this->listenerProvider->expects($this->once())
            ->method('register')
            ->with('App\Areas\Admin\Listener\AdminListener');

        $this->makeDiscovery([
            '@app/Areas/*/Listener/*Listener.php' => 'App\Areas\*\Listener\*Listener',
        ])->discover();
    }

    public function testAppScanWithMultipleListeners(): void
    {
        $this->defineClass('App\\Listener\\DemoListener');
        $this->defineClass('App\\Areas\\Area1\\Listener\\AreaListener');

        $callCount = 0;
        $this->filesystem->expects($this->exactly(2))
            ->method('glob')
            ->willReturnCallback(function ($pattern) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    $this->assertSame('@app/Listener/*Listener.php', $pattern);
                    return ['/app/Listener/DemoListener.php'];
                }
                $this->assertSame('@app/Areas/*/Listener/*Listener.php', $pattern);
                return ['/app/Areas/Area1/Listener/AreaListener.php'];
            });

        $addCalls = [];
        $this->listenerProvider->expects($this->exactly(2))
            ->method('register')
            ->willReturnCallback(function ($className) use (&$addCalls) {
                $addCalls[] = $className;
            });

        $this->makeDiscovery([
            '@app/Listener/*Listener.php' => 'App\\Listener\\*Listener',
            '@app/Areas/*/Listener/*Listener.php' => 'App\\Areas\\*\\Listener\\*Listener',
        ])->discover();

        $this->assertSame(['App\Listener\DemoListener', 'App\Areas\Area1\Listener\AreaListener'], $addCalls);
    }

    public function testAppScanWithEmptyGlobResult(): void
    {
        $this->filesystem->expects($this->once())
            ->method('glob')
            ->with('@app/Listener/*Listener.php')
            ->willReturn([]);

        $this->listenerProvider->expects($this->never())->method('register');

        $this->makeDiscovery(['@app/Listener/*Listener.php' => 'App\\Listener\\*Listener'])->discover();
    }

    protected function defineClass(string $className): void
    {
        if (class_exists($className)) {
            return;
        }

        $pos = strrpos($className, '\\');
        $namespace = substr($className, 0, $pos);
        $shortName = substr($className, $pos + 1);

        eval("namespace {$namespace}; class {$shortName} {}");
    }
}
