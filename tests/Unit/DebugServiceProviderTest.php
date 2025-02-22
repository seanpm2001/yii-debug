<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Debug\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\Yii\Debug\DebugServiceProvider;
use Yiisoft\Yii\Debug\Collector\EventDispatcherInterfaceProxy;
use Yiisoft\Yii\Debug\Collector\LoggerInterfaceProxy;

final class DebugServiceProviderTest extends TestCase
{
    /**
     * @throws InvalidConfigException
     *
     * @covers \Yiisoft\Yii\Debug\DebugServiceProvider::getDefinitions()
     * @covers \Yiisoft\Yii\Debug\DebugServiceProvider::getExtensions()
     */
    public function testRegister(): void
    {
        $config = ContainerConfig::create()
            ->withDefinitions([
                LoggerInterface::class => NullLogger::class,
                EventDispatcherInterface::class => Dispatcher::class,
                ListenerProviderInterface::class => Provider::class,
            ])
            ->withProviders([
                new DebugServiceProvider(),
            ]);
        $container = new Container($config);

        $this->assertInstanceOf(LoggerInterfaceProxy::class, $container->get(LoggerInterface::class));
        $this->assertInstanceOf(EventDispatcherInterfaceProxy::class, $container->get(EventDispatcherInterface::class));
    }
}
