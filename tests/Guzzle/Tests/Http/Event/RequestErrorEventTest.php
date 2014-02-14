<?php

namespace Guzzle\Tests\Http\Event;

use Guzzle\Http\Adapter\Transaction;
use Guzzle\Http\Client;
use Guzzle\Http\Event\ErrorEvent;
use Guzzle\Http\Exception\RequestException;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Event\RequestEvents;

/**
 * @covers Guzzle\Http\Event\ErrorEvent
 */
class ErrorEventTest extends \PHPUnit_Framework_TestCase
{
    public function testInterceptsWithEvent()
    {
        $client = new Client();
        $request = new Request('GET', '/');
        $response = new Response(404);
        $transaction = new Transaction($client, $request);
        $except = new RequestException('foo', $request, $response);
        $event = new ErrorEvent($transaction, $except);

        $this->assertSame($except, $event->getException());
        $this->assertSame($response, $event->getResponse());
        $this->assertSame($request, $event->getRequest());

        $res = null;
        $request->getEmitter()->on(RequestEvents::COMPLETE, function ($e) use (&$res) {
            $res = $e;
        });

        $good = new Response(200);
        $event->intercept($good);
        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame($res->getClient(), $event->getClient());
        $this->assertSame($good, $res->getResponse());
    }
}
