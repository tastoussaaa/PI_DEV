<?php
namespace App\Bot;

use BotMan\BotMan\Interfaces\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;

class SymfonyCache implements CacheInterface
{
    private FilesystemAdapter $cache;

    public function __construct(FilesystemAdapter $cache)
    {
        $this->cache = $cache;
    }

    public function has($key): bool
    {
        return $this->cache->getItem($this->sanitize($key))->isHit();
    }

    public function get($key, $default = null)
    {
        $item = $this->cache->getItem($this->sanitize($key));
        return $item->isHit() ? $item->get() : $default;
    }

    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function put($key, $value, $minutes)
    {
        $item = $this->cache->getItem($this->sanitize($key));
        $item->set($value);
        $item->expiresAfter($minutes * 60);
        $this->cache->save($item);
    }

    public function forget($key)
    {
        $this->cache->deleteItem($this->sanitize($key));
    }

    private function sanitize(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    }
}