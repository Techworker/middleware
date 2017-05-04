<?php

namespace Techworker\Functional;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\RequestInterface as Request;

/**
 * PSR-7 middleware implementation for PHP.
 *
 * This method accepts a PSR-7 HTTP message interface and calls the list of
 * handlers defined in the queue where each element in the queue is either a
 * valid callable (www.php.net/is_callable) or a value that can be transformed
 * into a callable by using the given resolver.
 *
 * Each of your callables should have the following function signature:
 *
 * function(
 *     \Psr\Http\Message\RequestInterface $request,
 *     \Psr\Http\Message\ResponseInterface $response,
 *     callable $next
 * );
 *
 * ... where the callable MUST return the response object and MAY call the $next
 * callable.
 *
 * So an example for a valid callback is:
 *
 * <code>
 * function(
 *     \Psr\Http\Message\RequestInterface $request,
 *     \Psr\Http\Message\ResponseInterface $response,
 *     callable $next
 * ) {
 *     // check for oauth bearer token
 *     if(!$request->getHeader('Authorization')) {
 *         return $response->withStatus(403);
 *     }
 *
 *     // call the next middleware that maybe auths the user
 *     $response = $next($request, $response);
 *
 *     // maybe set a user as a body response
 *     $response = $response->getBody()->write('Welcome authorized User');
 *     return $response;
 * }
 * </code>
 *
 * @param Request        $request  a request object instance that implements the
 *                                 PSR-7 HTTP Request interface
 * @param Response       $response a response object instance that implements
 *                                 the PSR-7 Response interface
 * @param callable|array $queue    the queue to poll and complete
 * @param callable       $resolver a function that is able to handle an item
 *                                 from the queue and transform it to a callable
 *
 * @return Response
 */
function middleware(Request $request,
                    Response $response,
                    $queue,
                    callable $resolver = null): Response
{
    // check if there is only one queue item
    if (!is_array($queue)) {
        $queue = [$queue];
    }

    /**
     * Internal function that gets called by a middleware and calls the next
     * middleware.
     *
     * @param Request  $request  the Psr7 request object
     * @param Response $response the Psr7 response object
     *
     * @throws \UnexpectedValueException
     * @throws \DomainException
     *
     * @return Response
     */
    $next = function (Request $request, Response $response) {
        // if its a named queue item, we might get better debugging or exception
        // results
        $key = key($this->queue);

        // check if we have any more queue entries. if someone adds a null value
        // to the queue we can circumstance this through that.
        if ($key === null) {
            return $response;
        }

        /** @var \callable $entry */
        $entry = array_shift($this->queue);

        // we have a queue entry! if a resolver is given call it. Doesn't matter
        // if it's already callable.
        if ($this->resolver) {
            $entry = ($this->resolver)($entry, $key);
        }

        // check if we can call the middleware
        if (!is_callable($entry)) {
            throw new \UnexpectedValueException('Middleware error: ' .
                'Given middleware at key '.$key.' is not callable.'
            );
        }

        // call the queue item
        if($entry instanceof \Closure) {
            $entry = \Closure::bind($entry, $this->attributes);
        }
        $response = $entry($request, $response, $this->next);

        // check if the middleware responded with a Response
        if (!($response instanceof Response)) {
            throw new \DomainException('Middleware error: ' .
                'Given middleware at key '.$key.' did not return a Response.'
            );
        }

        return $response;
    };

    // create the context in which the next function is executed
    $context = (object) [
        'queue' => $queue,
        'resolver' => $resolver,
        'next' => $next,
        'attributes' => new \stdClass()
    ];

    // apply the context so the function can call itself
    $context->next = \Closure::bind($context->next, $context);

    // call next and let it roll
    return ($context->next)($request, $response);
}
