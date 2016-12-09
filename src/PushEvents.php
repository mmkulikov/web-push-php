<?php

namespace Awelty\Component\WebPush;

class PushEvents
{
    /**
     * Trigger when a push is successfully sent to the push service
     * This mean push service respond with a 2xx, not that the client received it.
     *
     * @Event("App\Push\PushEvent")
     */
    const SUCCESS = 'push.success';

    /**
     * Trigger when a push was rejected by the push service
     *
     * @Event("App\Push\PushEvent")
     */
    const FAILED = 'push.failed';


}
