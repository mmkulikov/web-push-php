<?php

namespace Awelty\Component\WebPush\Model;

interface NotificationPayloadInterface extends PayloadInterface
{
    /**
     * @return string
     */
    public function getTitle();

    /**
     * @return string
     */
    public function getBody();

    /**
     * @return string
     */
    public function getIcon();

    /**
     * @return string | null
     */
    public function getTag();

    /**
     * @return string | null
     */
    public function getUrl();

}
