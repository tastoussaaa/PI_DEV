<?php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
class BotManSessionListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (str_starts_with($request->getPathInfo(), '/botman')) {
            // Mark request as having no session instead of passing null
            $request->attributes->set('_stateless', true);
        }
    }
}