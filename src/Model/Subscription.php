<?php

namespace Awelty\Component\WebPush\Model;

/**
 * Model of a subscription (equivalent from PushSubscription JS)
 */
class Subscription
{
    private $endpoint;

    private $auth;

    private $p256dh;

    public function __construct($endpoint, $auth, $p256dh)
    {
        $this->endpoint = $endpoint;
        $this->auth = $auth;
        $this->p256dh = $p256dh;
    }

    /**
     * @return mixed
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * @return mixed
     */
    public function getAuth()
    {
        return $this->auth;
    }

    /**
     * @return mixed
     */
    public function getP256dh()
    {
        return $this->p256dh;
    }
}
