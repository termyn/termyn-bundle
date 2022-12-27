<?php

declare(strict_types=1);

namespace Termyn\Bundle\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Termyn\Cqrs\CommandHandler;
use Termyn\Cqrs\Messaging\Messenger\Middleware\AckHandledCommandMiddleware;
use Termyn\Cqrs\Messaging\Messenger\Middleware\AckSentCommandMiddleware;
use Termyn\Cqrs\Messaging\Messenger\Middleware\ResolveHandledQueryResultMiddleware;
use Termyn\Cqrs\Messaging\Messenger\Middleware\ValidateMessageMiddleware;
use Termyn\Cqrs\QueryHandler;
use Termyn\Ddd\DomainEventHandler;

final class TermynExtension extends Extension implements ExtensionInterface, PrependExtensionInterface
{
    private FileLocator $fileLocator;

    public function __construct()
    {
        $this->fileLocator = new FileLocator(__DIR__ . '/../Resources/config');
    }

    public function prepend(
        ContainerBuilder $container,
    ): void {
        $this->setUpBuses($container);
        $this->registerHandlersForAutoconfiguration($container);
    }

    public function load(
        array $configs,
        ContainerBuilder $containerBuilder,
    ): void {
        $loader = new XmlFileLoader($containerBuilder, $this->fileLocator);
        $loader->load('services.xml');
    }

    private function setUpBuses(
        ContainerBuilder $container,
    ): void {
        $configs = $this->resolveMessengerConfigs($container);

        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'default_bus' => $configs['default_bus'] ?? 'termyn.cqrs.command_bus',
                'buses' => [
                    'termyn.cqrs.command_bus' => [
                        'middleware' => [
                            ValidateMessageMiddleware::class,
                            AckHandledCommandMiddleware::class,
                            AckSentCommandMiddleware::class,
                        ],
                    ],
                    'termyn.cqrs.query_bus' => [
                        'middleware' => [
                            ValidateMessageMiddleware::class,
                            ResolveHandledQueryResultMiddleware::class,
                        ],
                    ],
                    'termyn.ddd.domain_event_bus' => [
                        'default_middleware' => 'allow_no_handlers',
                    ],
                ],
            ],
        ]);
    }

    private function registerHandlersForAutoconfiguration(
        ContainerBuilder $container,
    ): void {
        $container->registerForAutoconfiguration(CommandHandler::class)
            ->addTag('termyn.cqrs.command_handler')
            ->addTag('messenger.message_handler', [
                'bus' => 'termyn.cqrs.command_bus',
            ]);

        $container->registerForAutoconfiguration(QueryHandler::class)
            ->addTag('termyn.cqrs.query_handler')
            ->addTag('messenger.message_handler', [
                'bus' => 'termyn.cqrs.query_bus',
            ]);

        $container->registerForAutoconfiguration(DomainEventHandler::class)
            ->addTag('termyn.ddd.domain_event_handler')
            ->addTag('messenger.message_handler', [
                'bus' => 'termyn.ddd.domain_event_bus',
            ]);
    }

    private function resolveMessengerConfigs(
        ContainerBuilder $container,
    ): array {
        return $this->processConfiguration(
            configuration: new Configuration(false),
            configs: $container->getExtensionConfig('framework')
        )['messenger'] ?? [];
    }
}
