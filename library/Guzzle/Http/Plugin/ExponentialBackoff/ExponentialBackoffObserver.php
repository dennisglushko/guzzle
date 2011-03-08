<?php

namespace Guzzle\Http\Plugin\ExponentialBackoff;

use Guzzle\Common\Event\Subject;
use Guzzle\Common\Event\Observer;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Pool\PoolInterface;

/**
 * Observer that should be attached to a Pool object and
 * handles the resending of requests with exponential backoff after a delay.
 *
 * @author Michael Dowling <michael@guzzlephp.org>
 */
class ExponentialBackoffObserver implements Observer
{
    /**
     * @var integer Time at which it is okay to add the request back to the pool
     */
    protected $retryTime;

    /**
     * @var RequestInterface The request object that needs to be retried
     */
    protected $request;

    /**
     * Constructor
     *
     * @param RequestInterface $request The request the needs retrying
     *
     * @param integer $delayTime The time in which retrying the request must be delayed
     */
    public function __construct(RequestInterface $request, $delayTime)
    {
        $this->request = $request;
        $this->retryTime = time() + (int) $delayTime;
    }

    /**
     * Check if the request object is ready to be retried
     *
     * {@inheritdoc}
     */
    public function update(Subject $subject, $event, $context = null)
    {
        if ($subject instanceof PoolInterface && $event == PoolInterface::POLLING) {

            // If the duration of the delay has passed, retry the request using the pool
            if (time() >= $this->retryTime) {

                // Remove the request from the pool and then add it back again
                $subject->removeRequest($this->request);
                $subject->addRequest($this->request);
                
                // Remove this observer from the request
                $subject->getEventManager()->detach($this);

                return true;
            }
        }

        return false;
    }
}