<?php

namespace Awelty\Component\WebPush\Model;

interface PayloadInterface
{
    /**
     * Return the payload to send throw the push
     * Non scalar values will be json encoded
     * @return mixed
     */
    public function getPayload();
}
