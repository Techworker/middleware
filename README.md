Functional PSR-7 HTTP message middleware
===

This library provides a single function to implement a PHP 7 middleware workflow 
for your PHP applications.

It is heavily inspired from the [relay php](https://http://relayphp.com/) 
implementation by [Paul M. Jones](https://github.com/pmjones) 

## Usage

Import the function and define a list of middlewares.

```php
<?php

use Psr7Middlewares\Middleware;
use Psr7Middlewares\Middleware\ErrorHandler;

use function Techworker\Functional\middleware;

/**
 * This function checks whether the 'Authorization' is given.
 */
$middleware1 = function(
    \Psr\Http\Message\ServerRequestInterface $request,
    \Psr\Http\Message\ResponseInterface $response,
    callable $next
) {
    return $next($request, $response);
};

$middlewares = [$middleware1, \Psr7Middlewares\Middleware::uuid()];

middleware($request, $response, $middlewares);

```

## Installation

Add this to your `composer.json`

```json
"require": {
	"techworker/middleware": "^1.0"
}
```

.. or use the following command on your command line:

`composer require techworker/middleware`

