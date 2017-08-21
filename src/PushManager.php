<?php

namespace Awelty\Component\WebPush;

use Awelty\Component\WebPush\Model\PayloadInterface;
use Awelty\Component\WebPush\Model\Subscription;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Web push feature
 */
class PushManager
{
    /** @var Client  */
    private $client;

    /**
     * @var VapidHeadersProvider
     */
    private $vapidHeadersProvider;

    /**
     * @var PayloadEncrypter
     */
    private $encrypter;

    /**
     * Requests to send
     * @var RequestInterface[]
     */
    private $requests = [];

    /**
     * @var EventDispatcherInterface | null
     */
    private $eventDispatcher;

    /**
     * @var LoggerInterface | null
     */
    private $logger;

    /**
     * Holds default options (or just default headers ?)
     * @var OptionsResolver
     */
    private $optionsResolver;

    public function __construct(VapidHeadersProvider $vapidHeadersProvider, PayloadEncrypter $encrypter, $defaultOptions = [])
    {
        $this->client = new Client();
        $this->vapidHeadersProvider = $vapidHeadersProvider;
        $this->encrypter = $encrypter;

        $this->optionsResolver = new OptionsResolver();
        $this->configureOptions($this->optionsResolver);
        $defaultOptions = $this->optionsResolver->resolve($defaultOptions);
        $this->optionsResolver->setDefaults($defaultOptions);
    }

    private function configureOptions(OptionsResolver $optionsResolver)
    {
        $optionsResolver->setDefault('TTL', 2419200); // 4 weeks
        $optionsResolver->setDefined(['urgency', 'topic']);
        $optionsResolver->setAllowedValues('urgency', ['very-low', 'low', 'normal', 'high']);
        // TODO add validation for TTL & topic ?
    }

    /**
     * Optionnal : Active or disable dispatcher feature
     * @param EventDispatcherInterface|null $eventDispatcher
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher = null)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Optionnal : Active or disable log feature
     * @param LoggerInterface|null $logger
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param Subscription $subscription
     * @param string | array $payload datas to send
     * @param array $headers
     */
    public function push(Subscription $subscription, $payload = null, $headers = [])
    {
        if ($payload instanceof PayloadInterface) {
            $payload = $payload->getPayload();
        }

        if (!is_scalar($payload)) {
            $payload = \GuzzleHttp\json_encode($payload);
        }

        $headers = $this->optionsResolver->resolve($headers);

        $request = new Request('POST', $subscription->getEndpoint(), $headers, $payload);

        try {
            $request = $this->encrypter->encrypt($request, $subscription);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->log(LogLevel::WARNING, 'Push not pushed: encryption failed', ['exception' => $e]);
            }
            return;
        }

        $request = $this->vapidHeadersProvider->sign($request, $subscription);
        $request = $this->mergeCryptoKeyHeaders($request);

        $this->requests[] = $request;
    }

    /**
     * Send stored requests
     */
    public function flush()
    {
        $options = [];

        if ($this->eventDispatcher) {
            $options = [
                'fulfilled' => function(Response $response, $index) {
                    $event = new PushEvent($response);
                    $this->eventDispatcher->dispatch(PushEvents::SUCCESS, $event);
                },
                'rejected' => function(RequestException $error, $index) {
                    $event = new PushEvent($error->getResponse());
                    $this->eventDispatcher->dispatch(PushEvents::FAILED, $event);
                }
            ];
        }

        $pool = new Pool($this->client, $this->requests, $options);
        $pool->promise()->wait();
    }

    private function mergeCryptoKeyHeaders(RequestInterface $request)
    {
        $header = implode(';', $request->getHeader('Crypto-Key'));
        return $request->withHeader('Crypto-Key', $header);
    }
}
