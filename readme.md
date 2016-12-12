# Web Push PHP

> Web Push library for PHP.

This is a huge refactoring of @Minishlink [Web Push library](https://github.com/web-push-libs/web-push-php)

## Installation

```composer require awelty/web-push-php```

## Create the push manager

### With Silex...
We are providing a Silex ServiceProvider: 

```
<?php 

use Awelty\Component\WebPush\Silex\PushServiceProvider;

$app->register(new PushServiceProvider(), [
    
    'vapid.public_key' => 'publicKey...',
    'vapid.private_key' => 'privateKey...',
    'vapid.subject' => 'subject...',
    'push.dispatcher' => 'dispatcher' // false to disable events feature, otherwise the name of an EventDispatcherService
]);
```

This will define a **push.manager** service, with which we will sending pushes.

### Or manually
This will be a little more verbose:

```
<?php 

use Awelty\Component\WebPush\Model\VAPID;
use Awelty\Component\WebPush\PayloadEncrypter;
use Awelty\Component\WebPush\PushManager;
use Awelty\Component\WebPush\VapidHeadersProvider;
use Base64Url\Base64Url;

// create the VapidHeadersProvider
$vapid = new VAPID($subject, Base64Url::decode($publicKey), Base64Url::decode($privateKey));
$vapidHeadersProvider = new VapidHeadersProvider($vapid);

// create the PushManager
$pushManager = new PushManager($vapidHeadersProvider, new PayloadEncrypter(), $defaultOptions = []);

// optionnal : enable events feature (link vers doc chapter)
$pushManager->setEventDispatcher($eventDispatcher);
```

## Usage

```
<?php 

// Get a subscription, for exemple from your database.. 
// This is a model of the subscription you get from the front
$subscription = new Subscription($userSubscription->endpoint, $userSubscription->keys->auth, $userSubscription->keys->p256dh);

// push as a "ping"
$pushManager->push($subscription);

// push with some payload, can be a string, an object, an array.. (non scalar values will be json_encoded)
$pushManager->push($subscription, 'test');
$pushManager->push($subscription, ['title' => 'This is a push']);

// and you can provide some other options than default one for each notifications
$pushManager->push($subscription, 'test', ['TTL' => 86400]);
```

#### Options

##### TTL
##### urgency
##### topic
