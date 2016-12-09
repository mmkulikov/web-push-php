<?php

namespace Awelty\Component\WebPush;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Hold the response of a push request
 */
class PushEvent extends Event
{
    /**
     * @var ResponseInterface
     */
    private $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }
}
