<?php

declare(strict_types=1);

namespace Termyn\Bundle\Messaging\Test\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Termyn\Bundle\DependencyInjection\TermynExtension;
use Termyn\Cqrs\CommandHandler;
use Termyn\Cqrs\Messaging\CommandBus;
use Termyn\Cqrs\Messaging\QueryBus;
use Termyn\Cqrs\QueryHandler;
use Termyn\Ddd\DomainEventHandler;
use Termyn\Ddd\Messaging\DomainEventBus;

final class TermynExtensionTest extends TestCase
{
    private TermynExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new TermynExtension();
        $this->container = new ContainerBuilder();

        parent::setUp();
    }

    public function testItContainsMessengerConfigs(): void
    {
        $this->extension->prepend($this->container);

        $messengerConfigs = $this->resolveMessengerConfig();

        $this->assertArrayHasKey('default_bus', $messengerConfigs);
        $this->assertArrayHasKey('buses', $messengerConfigs);
    }

    /**
     * @dataProvider provideBusIds
     */
    public function testItRegistersExpectedBuses(string $busId): void
    {
        $this->extension->prepend($this->container);

        $this->assertArrayHasKey($busId, $this->resolveMessengerConfig()['buses']);
    }

    public function provideBusIds(): array
    {
        return [
            'command-bus' => [
                'busId' => 'termyn.cqrs.command_bus',
            ],
            'query-bus' => [
                'busId' => 'termyn.cqrs.query_bus',
            ],
            'domain-event-bus' => [
                'busId' => 'termyn.ddd.domain_event_bus',
            ],
        ];
    }

    /**
     * @dataProvider provideHandlerSettings
     */
    public function testItRegistersAutoconfigurationOfHandlers(string $id): void
    {
        $this->extension->prepend($this->container);

        $this->assertArrayHasKey($id, $this->container->getAutoconfiguredInstanceof());
    }

    /**
     * @dataProvider provideHandlerSettings
     */
    public function testItSetsAutoconfigurationTags(string $id, array $tags): void
    {
        $this->extension->prepend($this->container);

        $autoconfiguration = $this->container->getAutoconfiguredInstanceof()[$id];

        foreach ($tags as $tag => $configs) {
            $this->assertArrayHasKey($tag, $autoconfiguration->getTags());
            $this->assertContainsEquals($configs, $autoconfiguration->getTags()[$tag]);
        }
    }

    public function provideHandlerSettings(): array
    {
        return [
            'command-handler' => [
                'id' => CommandHandler::class,
                'tags' => [
                    'termyn.cqrs.command_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'termyn.cqrs.command_bus',
                    ],
                ],
            ],
            'query-handler' => [
                'id' => QueryHandler::class,
                'tags' => [
                    'termyn.cqrs.query_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'termyn.cqrs.query_bus',
                    ],
                ],
            ],
            'domain-event-handler' => [
                'id' => DomainEventHandler::class,
                'tags' => [
                    'termyn.ddd.domain_event_handler' => [],
                    'messenger.message_handler' => [
                        'bus' => 'termyn.ddd.domain_event_bus',
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideServiceIds
     */
    public function testItRegistersExpectedServices(string $serviceId): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasDefinition($serviceId));
    }

    public function provideServiceIds(): array
    {
        return [
            'command-bus' => [
                'serviceId' => CommandBus::class,
            ],
            'query-bus' => [
                'serviceId' => QueryBus::class,
            ],
            'domain-event-bus' => [
                'serviceId' => DomainEventBus::class,
            ],
        ];
    }

    private function resolveMessengerConfig(): array
    {
        return $this->resolveFrameworkConfig()['messenger'];
    }

    private function resolveFrameworkConfig(): array
    {
        return $this->container->getExtensionConfig('framework')[0];
    }
}
