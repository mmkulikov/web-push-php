<?php

namespace Awelty\Component\WebPush\Model;

abstract class AbstractNotification implements NotificationPayloadInterface
{
    public function getPayload()
    {
        $payload = [
            'title' => $this->getTitle(),
            'body' => $this->getBody(),
            'icon' => $this->getIcon(),
            'tag' => $this->getTag(),
            'url' => $this->getUrl(),
        ];

        // remove null values
        return array_filter($payload);
    }
}
