<?php

namespace Awelty\Component\WebPush\Silex;

use Awelty\Component\WebPush\KernelListener;
use Awelty\Component\WebPush\Model\VAPID;
use Awelty\Component\WebPush\PayloadEncrypter;
use Awelty\Component\WebPush\PushManager;
use Awelty\Component\WebPush\VapidHeadersProvider;
use Base64Url\Base64Url;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\EventListenerProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PushServiceProvider implements ServiceProviderInterface, EventListenerProviderInterface
{
    public function register(Container $container)
    {
        $container['vapid.public_key'] = '';
        $container['vapid.private_key'] = '';
        $container['vapid.subject'] = '';

        $container['push.default_options'] = [];

        $container['push.dispatcher'] = function (Container $container) {
            return isset($container['dispatcher']) ? 'dispatcher' : null;
        };

        $container['push.logger'] = function (Container $container) {
            return isset($container['logger']) ? 'logger' : null;
        };

        $container['push.manager'] = function (Container $container) {
            $pushManager = new PushManager($container['push.vapid.headers_provider'], $container['push.payload_encrypter'], $container['push.default_options']);

            $dispatcherName = $container['push.dispatcher'];

            if ($dispatcherName) {
                $pushManager->setEventDispatcher($container[$dispatcherName]);
            }

            $loggerName = $container['push.logger'];

            if ($loggerName) {
                $pushManager->setLogger($container[$loggerName]);
            }

            return $pushManager;
        };

        $container['push.vapid.headers_provider'] = function (Container $container) {
            $vapid = new VAPID($container['vapid.subject'], Base64Url::decode($container['vapid.public_key']), Base64Url::decode($container['vapid.private_key']));

            return new VapidHeadersProvider($vapid);
        };

        $container['push.payload_encrypter'] = function (Container $container) {
            return new PayloadEncrypter();
        };
    }

    public function subscribe(Container $container, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber(new KernelListener($container['push.manager']));
    }
}
