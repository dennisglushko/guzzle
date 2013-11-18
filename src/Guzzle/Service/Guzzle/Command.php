<?php

namespace Guzzle\Service\Guzzle;

use Guzzle\Common\HasDataTrait;
use Guzzle\Common\HasDispatcherTrait;
use Guzzle\Service\Command\CommandInterface;

class Command implements CommandInterface
{
    use HasDataTrait;
    use HasDispatcherTrait;

    protected $operation;
    protected $serializer;
    protected $request;
    protected $response;
    protected $result;

    public function __construct(array $args)
    {
        $this->data = $args;
        //$this->operation = $operation;
    }

    public function getOperation()
    {
        return $this->operation;
    }

    public function getRequest()
    {
        if (!$this->request) {
            if (!isset($this['client'])) {
                throw new \RuntimeException('A client must be specified on the command');
            }
            $this->request = $this['client']->createRequest('GET', 'https://raw.github.com/aws/aws-sdk-core-ruby/master/apis/CloudFront-2012-05-05.json');
        }

        return $this->request;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function prepare()
    {
        $this->request = $this->response = null;

        return $this->getRequest();
    }

    public function getResult()
    {
        return $this->result;
    }
}
