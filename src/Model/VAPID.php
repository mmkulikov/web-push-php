<?php

namespace Awelty\Component\WebPush\Model;

/**
 * Hold the vapid keys of you application
 */
class VAPID
{
    /**
     * a "malto:" or an url
     * @var string
     */
    private $subject;

    /**
     * a 65 bytes long Base64Url decoded string
     * @var string
     */
    private $publicKey;

    /**
     * a 32 bytes long Base64Url decoded string
     * @var string
     */
    private $privateKey;

    public function __construct($subject, $publicKey, $privateKey)
    {
        if (mb_strlen($publicKey, '8bit') !== 65) {
            throw new \ErrorException('[VAPID] Public key should be 65 bytes long when decoded.');
        }

        if (mb_strlen($privateKey, '8bit') !== 32) {
            throw new \ErrorException('[VAPID] Public key should be 32 bytes long when decoded.');
        }

        $this->subject = $subject;
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return string
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }
}
