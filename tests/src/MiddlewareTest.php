<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use function \Techworker\Functional\middleware;

class MiddlewareTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Returns a single set with an empty request and response.
     *
     * @return array
     */
    public function requestResponseProvider()
    {
        return [
            [
                new \Zend\Diactoros\ServerRequest(),
                new \Zend\Diactoros\Response
            ]
        ];
    }

    /**
     * @dataProvider requestResponseProvider
     * @param Request $fixRequest
     * @param Response $fixResponse
     */
    public function testEmptyQueue(Request $fixRequest, Response $fixResponse)
    {
        $responseFromMiddleware = middleware($fixRequest, $fixResponse, []);

        // should return an untouched response instance
        static::assertEquals($fixResponse, $responseFromMiddleware);
    }

    /**
     * @dataProvider requestResponseProvider
     * @param Request $fixRequest
     * @param Response $fixResponse
     */
    public function testSingleQueue(Request $fixRequest, Response $fixResponse)
    {
        $responseFromMiddleware = middleware($fixRequest, $fixResponse, [
            $this->createNextCallingMiddleware(500)
        ]);
        static::assertEquals(500, $responseFromMiddleware->getStatusCode());
    }

    /**
     * @dataProvider requestResponseProvider
     * @param Request $fixRequest
     * @param Response $fixResponse
     */
    public function testSingleQueueTransformArray(Request $fixRequest, Response $fixResponse)
    {
        $responseFromMiddleware = middleware($fixRequest, $fixResponse, $this->createNextCallingMiddleware(500));
        static::assertEquals(500, $responseFromMiddleware->getStatusCode());
    }

    /**
     * @dataProvider requestResponseProvider
     * @param Request $fixRequest
     * @param Response $fixResponse
     */
    public function testMultiQueueSimple(Request $fixRequest, Response $fixResponse)
    {
        $middlewares = [
            $this->createNextCallingMiddleware(201),
            $this->createNextCallingMiddleware(500)
        ];

        $responseFromMiddleware = middleware($fixRequest, $fixResponse, $middlewares);
        static::assertEquals(500, $responseFromMiddleware->getStatusCode());
    }

    /**
     * @dataProvider requestResponseProvider
     * @param Request $fixRequest
     * @param Response $fixResponse
     */
    public function testMultiQueueStops(Request $fixRequest, Response $fixResponse)
    {
        $middlewares = [
            $this->createStoppingMiddleware(201),
            $this->createNextCallingMiddleware(500)
        ];

        $responseFromMiddleware = middleware($fixRequest, $fixResponse, $middlewares);
        static::assertEquals(201, $responseFromMiddleware->getStatusCode());
    }

    /**
     * @dataProvider requestResponseProvider
     * @param Request $fixRequest
     * @param Response $fixResponse
     */
    public function testResolver(Request $fixRequest, Response $fixResponse)
    {
        $resolved = 0;
        $resolver = function ($entry, $key) use (&$resolved) {
            switch ($key) {
                case 0:
                    static::assertTrue(true);
                    $resolved++;
                    return $entry;
                case 'test':
                    static::assertTrue(true);
                    $resolved++;
                    return $entry;
                default:
                    static::assertTrue(false);
                    break;
            }
        };

        $middlewares = [
            0 => $this->createNextCallingMiddleware(201),
            'test' => $this->createNextCallingMiddleware(500)
        ];
        middleware($fixRequest, $fixResponse, $middlewares, $resolver);
        static::assertEquals(2, $resolved);
    }

    /**
     * @dataProvider requestResponseProvider
     * @param Request $fixRequest
     * @param Response $fixResponse
     *
     * @expectedException \UnexpectedValueException
     */
    public function testNotCallable(Request $fixRequest, Response $fixResponse)
    {
        $middlewares = [null];

        middleware($fixRequest, $fixResponse, $middlewares);
    }


    /**
     * @dataProvider requestResponseProvider
     * @param Request $fixRequest
     * @param Response $fixResponse
     *
     * @expectedException \DomainException
     */
    public function testBadMiddleware(Request $fixRequest, Response $fixResponse)
    {
        $middlewares = [function () {
            return null;
        }];

        middleware($fixRequest, $fixResponse, $middlewares);
    }

    /**
     * @dataProvider requestResponseProvider
     * @param Request $fixRequest
     * @param Response $fixResponse
     */
    public function testUserlandMiddleware(\Psr\Http\Message\ServerRequestInterface $fixRequest, Response $fixResponse)
    {
        $that = $this;
        $middlewares = [
            \Psr7Middlewares\Middleware::clientIp(),
            // check request altering
            function (Request $request, Response $response, callable $next) use($that) {
                $middlewareAtts = $request->getAttribute(\Psr7Middlewares\Middleware::KEY);
                $clientIpAttr = $middlewareAtts[\Psr7Middlewares\Middleware\ClientIp::KEY];
                $that->assertNotNull($clientIpAttr);

                return $next($request, $response);
            },
            \Psr7Middlewares\Middleware::responseTime(),
            \Psr7Middlewares\Middleware::uuid()
        ];

        $response = middleware($fixRequest, $fixResponse, $middlewares);
        static::assertTrue($response->hasHeader('X-Uuid'));
        static::assertTrue($response->hasHeader('X-Response-Time'));
    }

    /**
     * Returns a closure that, when called as a middleware, calls the next handler
     * and applies the initially given return code to the response
     *
     * @param int $newStatusCode
     * @return Closure
     */
    protected function createNextCallingMiddleware(int $newStatusCode)
    {
        return function (Request $request, Response $response, callable $next) use ($newStatusCode) {
            $response = $response->withStatus($newStatusCode);
            return $next($request, $response);
        };
    }

    /**
     * Returns a closure that, when called as a middleware, immediately applies
     * the initially given return code to the response.
     *
     * @param int $newStatusCode
     * @return Closure
     */
    protected function createStoppingMiddleware(int $newStatusCode)
    {
        return function (Request $request, Response $response, callable $next) use ($newStatusCode) {
            return $response->withStatus($newStatusCode);
        };
    }
}
