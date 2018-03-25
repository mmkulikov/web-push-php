<?php

namespace Awelty\Component\WebPush;

use AESGCM\AESGCM;
use Awelty\Component\WebPush\Model\Subscription;
use Base64Url\Base64Url;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Math\GmpMathInterface;
use Mdanter\Ecc\Primitives\CurveFp;
use Mdanter\Ecc\Primitives\GeneratorPoint;
use Mdanter\Ecc\Serializer\Point\UncompressedPointSerializer;
use Psr\Http\Message\RequestInterface;

/**
 * encrypt the body of a push request
 */
class PayloadEncrypter
{
    /**
     * @var GmpMathInterface
     */
    private $math;

    /**
     * @var UncompressedPointSerializer
     */
    private $pointSerializer;

    /**
     * @var GeneratorPoint
     */
    private $generatorPoint;

    /**
     * @var CurveFp
     */
    private $curve;

    /**
     * @var bool
     */
    private $nativePayloadEncryptionSupport;

    public function __construct()
    {
        $this->generatorPoint = EccFactory::getNistCurves()->generator256(); // as it is also used by VapidHeadersProvider, this could be injected as a dependency..
        $this->math = EccFactory::getAdapter();
        $this->pointSerializer = new UncompressedPointSerializer($this->math);
        $this->curve = EccFactory::getNistCurves()->curve256();
        $this->nativePayloadEncryptionSupport = version_compare(phpversion(), '7.1', '>=');
    }

    /**
     * @param RequestInterface $request
     * @param Subscription $subscription
     * @return RequestInterface
     */
    public function encrypt(RequestInterface $request, Subscription $subscription)
    {
        // There is no payload to encrypt
        if (!$request->getBody()->getSize()) {
            return $request;
        }

        $payload = $request->getBody()->getContents();
        $payload = $this->pad($payload);

        $userPublicKey = Base64Url::decode($subscription->getP256dh());
        $userAuthToken = Base64Url::decode($subscription->getAuth());

        // get local key pair
        $localPrivateKeyObject = $this->generatorPoint->createPrivateKey();
        $localPublicKeyObject = $localPrivateKeyObject->getPublicKey();
        $localPublicKey = hex2bin($this->pointSerializer->serialize($localPublicKeyObject->getPoint()));

        // get user public key object
        $pointUserPublicKey = $this->pointSerializer->unserialize($this->curve, bin2hex($userPublicKey));
        $userPublicKeyObject = $this->generatorPoint->getPublicKeyFrom($pointUserPublicKey->getX(), $pointUserPublicKey->getY(), $this->generatorPoint->getOrder());

        // get shared secret from user public key and local private key
        $sharedSecret = hex2bin($this->math->decHex(gmp_strval($userPublicKeyObject->getPoint()->mul($localPrivateKeyObject->getSecret())->getX())));

        // generate salt
        $salt = openssl_random_pseudo_bytes(16);

        // section 4.3
        $ikm = !empty($userAuthToken) ?
            $this->hkdf($userAuthToken, $sharedSecret, 'Content-Encoding: auth'.chr(0), 32) :
            $sharedSecret;

        // section 4.2
        $context = $this->createContext($userPublicKey, $localPublicKey);

        // derive the Content Encryption Key
        $contentEncryptionKeyInfo = $this->createInfo('aesgcm', $context);
        $contentEncryptionKey = $this->hkdf($salt, $ikm, $contentEncryptionKeyInfo, 16);

        // section 3.3, derive the nonce
        $nonceInfo = $this->createInfo('nonce', $context);
        $nonce = $this->hkdf($salt, $ikm, $nonceInfo, 12);

        // encrypt
        $tag = null;
        // "The additional data passed to each invocation of AEAD_AES_128_GCM is a zero-length octet sequence."
        if ($this->nativePayloadEncryptionSupport) {
            $encryptedText = openssl_encrypt($payload, 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag); // base 64 encoded
        } else {
            list($encryptedText, $tag) = AESGCM::encrypt($contentEncryptionKey, $nonce, $payload, '');
        }

        return $request
            ->withBody(\GuzzleHttp\Psr7\stream_for($encryptedText.$tag))
            ->withHeader('Content-Length', mb_strlen($encryptedText.$tag, '8bit'))
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Encoding', 'aesgcm')
            ->withHeader('Encryption', 'salt='.Base64Url::encode($salt))
            ->withHeader('Crypto-Key', 'dh='.Base64Url::encode($localPublicKey));
    }

    private function pad($payload)
    {
        $payloadLen = mb_strlen($payload, '8bit');
        $padLen = 0;

        return pack('n*', $padLen).str_pad($payload, $padLen + $payloadLen, chr(0), STR_PAD_LEFT);
    }

    /**
     * HMAC-based Extract-and-Expand Key Derivation Function (HKDF).
     *
     * This is used to derive a secure encryption key from a mostly-secure shared
     * secret.
     *
     * This is a partial implementation of HKDF tailored to our specific purposes.
     * In particular, for us the value of N will always be 1, and thus T always
     * equals HMAC-Hash(PRK, info | 0x01).
     *
     * See {@link https://www.rfc-editor.org/rfc/rfc5869.txt}
     * From {@link https://github.com/GoogleChrome/push-encryption-node/blob/master/src/encrypt.js}
     *
     * @param $salt string A non-secret random value
     * @param $ikm string Input keying material
     * @param $info string Application-specific context
     * @param $length int The length (in bytes) of the required output key
     *
     * @return string
     */
    private function hkdf($salt, $ikm, $info, $length)
    {
        // extract
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        // expand
        return substr(hash_hmac('sha256', $info.chr(1), $prk, true), 0, $length);
    }

    /**
     * Creates a context for deriving encyption parameters.
     * See section 4.2 of
     * {@link https://tools.ietf.org/html/draft-ietf-httpbis-encryption-encoding-00}
     * From {@link https://github.com/GoogleChrome/push-encryption-node/blob/master/src/encrypt.js}.
     *
     * @param $clientPublicKey string The client's public key
     * @param $serverPublicKey string Our public key
     *
     * @return string
     *
     * @throws \ErrorException
     */
    private function createContext($clientPublicKey, $serverPublicKey)
    {
        if (mb_strlen($clientPublicKey, '8bit') !== 65) {
            throw new \ErrorException('Invalid client public key length');
        }

        // This one should never happen, because it's our code that generates the key
        if (mb_strlen($serverPublicKey, '8bit') !== 65) {
            throw new \ErrorException('Invalid server public key length');
        }

        $len = chr(0).'A'; // 65 as Uint16BE

        return chr(0).$len.$clientPublicKey.$len.$serverPublicKey;
    }

    /**
     * Returns an info record. See sections 3.2 and 3.3 of
     * {@link https://tools.ietf.org/html/draft-ietf-httpbis-encryption-encoding-00}
     * From {@link https://github.com/GoogleChrome/push-encryption-node/blob/master/src/encrypt.js}.
     *
     * @param $type string The type of the info record
     * @param $context string The context for the record
     *
     * @return string
     *
     * @throws \ErrorException
     */
    private function createInfo($type, $context)
    {
        if (mb_strlen($context, '8bit') !== 135) {
            throw new \ErrorException('Context argument has invalid size');
        }

        return 'Content-Encoding: '.$type.chr(0).'P-256'.$context;
    }
}
