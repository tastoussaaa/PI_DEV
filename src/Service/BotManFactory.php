<?php
// src/Service/BotManFactory.php
namespace App\Service;

use App\Bot\BotServiceLocator;
use App\Bot\SymfonyCache;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory as VendorBotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BotManFactory
{
    public function __construct(
        private string $cacheDir,
        private ContainerInterface $container
    ) {}

    public function create(): BotMan
    {
        DriverManager::loadDriver(\BotMan\Drivers\Web\WebDriver::class);

        // Register container in static locator so conversations can access it
        BotServiceLocator::setContainer($this->container);

        $cache = new SymfonyCache(
            new FilesystemAdapter('botman', 1800, $this->cacheDir)
        );

        return VendorBotManFactory::create(
            ['conversation_cache_time' => 30],
            $cache,
            null,
            null
        );
    }
}