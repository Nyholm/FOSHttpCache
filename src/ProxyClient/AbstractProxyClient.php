<?php

/*
 * This file is part of the FOSHttpCache package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\HttpCache\ProxyClient;

use FOS\HttpCache\Exception\HttpClientException;
use FOS\HttpCache\HttpClient\Guzzle3Adapter;
use FOS\HttpCache\HttpClient\HttpClientInterface;
use FOS\HttpCache\ProxyClient\Request\InvalidationRequest;
use FOS\HttpCache\ProxyClient\Request\RequestQueue;
use Guzzle\Http\ClientInterface;

/**
 * Abstract caching proxy client
 *
 * @author David de Boer <david@driebit.nl>
 */
abstract class AbstractProxyClient implements ProxyClientInterface
{
    /**
     * HTTP client
     *
     * @var ClientInterface
     */
    private $client;

    /**
     * Request queue
     *
     * @var RequestQueue
     */
    protected $queue;

    /**
     * Constructor
     *
     * @param array           $servers Caching proxy server hostnames or IP addresses,
     *                                 including port if not port 80.
     *                                 E.g. array('127.0.0.1:6081')
     * @param string          $baseUrl Default application hostname, optionally
     *                                 including base URL, for purge and refresh
     *                                 requests (optional). This is required if
     *                                 you purge and refresh paths instead of
     *                                 absolute URLs.
     * @param HttpClientInterface|ClientInterface $client HTTP client (optional).
     *                                 If no HTTP client is supplied, a default
     *                                 one will be created.
     */
    public function __construct(array $servers, $baseUrl = null, $client = null)
    {
        if ($client instanceof ClientInterface) {
            // Only for BC; Guzzle 3 for PHP 5.3 compatibility
            $this->client  = new Guzzle3Adapter($client);
        } elseif ($client instanceof HttpClientInterface) {
            $this->client = $client;
        } elseif (null === $client) {
            $this->client = new Guzzle3Adapter();
        } else {
            throw new HttpClientException();
        }
        
        $this->initQueue($servers, $baseUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        if (0 === $this->queue->count()) {
            return 0;
        }
        
        $queue = clone $this->queue;
        $this->queue->clear();
        $this->client->sendRequests($queue);
        
        return $queue->count();
    }
    
    protected function queueRequest($method, $url, array $headers = array())
    {
        $this->queue->add(new InvalidationRequest($method, $url, $headers));
    }
    
    protected function initQueue(array $servers, $baseUrl)
    {
        $this->queue = new RequestQueue($servers, $baseUrl);
    }

    /**
     * Get schemes allowed by caching proxy
     *
     * @return string[] Array of schemes allowed by caching proxy, e.g. 'http'
     *                  or 'https'
     */
    abstract protected function getAllowedSchemes();
}
