<?php

namespace Awelty\Component\WebPush;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Flush notifications at the end of a script
 * You need to use HttpKernel Component to use it, and register the listener to its event_dispatcher
 * If you are using silex, just use the PushServiceProvider.php :)
 */
class KernelListener implements EventSubscriberInterface
{
    /**
     * @var PushManager
     */
    private $pushManager;

    public function __construct(PushManager $pushManager)
    {
        $this->pushManager = $pushManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::TERMINATE => ['onKernelTerminate', -255],
        ];
    }

    public function onKernelTerminate(PostResponseEvent $e)
    {
        $this->pushManager->flush();
    }
}
