<?php
// src/Bot/BotServiceLocator.php
namespace App\Bot;

use Psr\Container\ContainerInterface;

class BotServiceLocator
{
    private static ContainerInterface $container;

    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function get(string $id): mixed
    {
        return self::$container->get($id);
    }
}