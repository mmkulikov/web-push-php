<?php

namespace Awelty\Component\WebPush;

use Awelty\Component\WebPush\Model\Subscription;
use Awelty\Component\WebPush\Model\VAPID;
use Base64Url\Base64Url;
use Jose\Factory\JWKFactory;
use Jose\Factory\JWSFactory;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Primitives\GeneratorPoint;
use Mdanter\Ecc\Serializer\PrivateKey\DerPrivateKeySerializer;
use Mdanter\Ecc\Serializer\PrivateKey\PemPrivateKeySerializer;
use Psr\Http\Message\RequestInterface;

/**
 * Sign requests with vapid headers
 */
class VapidHeadersProvider
{
    /**
     * @var VAPID
     */
    private $vapid;

    /**
     * @var GeneratorPoint
     */
    private $generator;

    /**
     * @var PemPrivateKeySerializer
     */
    private $pemSerializer;

    public function __construct(VAPID $vapid)
    {
        $this->vapid = $vapid;
        $this->generator = EccFactory::getNistCurves()->generator256(); // as it is also used by PayloadEncrypter, this could be injected as a dependency..
        $this->pemSerializer = new PemPrivateKeySerializer(new DerPrivateKeySerializer());
    }

    /**
     * @param RequestInterface $request
     * @param Subscription $subscription
     * @return RequestInterface
     */
    public function sign(RequestInterface $request, Subscription $subscription)
    {
        // I assume every endpoint would be in https
        $audience = 'https://'.parse_url($subscription->getEndpoint(), PHP_URL_HOST);

        $jwtPayload = json_encode(array(
            'aud' => $audience,
            'exp' => time() + 43200, // equal margin of error between 0 and 24h,
            'sub' => $this->vapid->getSubject(),
        ), JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

        $privateKeyObject = $this->generator->getPrivateKeyFrom(gmp_init(bin2hex($this->vapid->getPrivateKey()), 16));

        $jwk = JWKFactory::createFromKey($this->pemSerializer->serialize($privateKeyObject));
        $jws = JWSFactory::createJWSToCompactJSON($jwtPayload, $jwk, [
            'typ' => 'JWT',
            'alg' => 'ES256',
        ]);

        return $request
            ->withHeader('Authorization', 'WebPush '.$jws)
            ->withAddedHeader('Crypto-Key', 'p256ecdsa='.Base64Url::encode($this->vapid->getPublicKey()));
    }

}
