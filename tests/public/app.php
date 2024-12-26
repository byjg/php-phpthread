<?php

use ByJG\PHPThread\Thread;
use ByJG\PHPThread\ThreadPool;
use ByJG\RestServer\HttpRequest;
use ByJG\RestServer\HttpRequestHandler;
use ByJG\RestServer\HttpResponse;
use ByJG\RestServer\OutputProcessor\JsonOutputProcessor;
use ByJG\RestServer\Route\Route;
use ByJG\RestServer\Route\RouteList;

require __DIR__ . "/../../vendor/autoload.php";

$routeDefinition = new RouteList();

$routeDefinition->addRoute(Route::get("/ping")
    ->withOutputProcessor(JsonOutputProcessor::class)
    ->withClosure(function (HttpResponse $response, HttpRequest $request) {
        $response->write(['ack' => php_sapi_name()]);
    })
);

$routeDefinition->addRoute(Route::get("/thread")
    ->withOutputProcessor(JsonOutputProcessor::class)
    ->withClosure(function (HttpResponse $response, HttpRequest $request) {
        $closure = function ($arg) {
            sleep(1 * $arg);
            return $arg * 3;
        };

        $thr1 = Thread::create($closure);
        $thr2 = Thread::create($closure);

        // Start Threads
        $thr1->start(2);
        $thr2->start(1);

        // Wait to Finish
        $thr1->join();
        $thr2->join();

        // Get the thread result
        $response->write([
            'thr1' => $thr1->getResult(), // 6
            'thr2' => $thr2->getResult()  // 3
        ]);
    })
);

$routeDefinition->addRoute(Route::get("/threadpool")
    ->withOutputProcessor(JsonOutputProcessor::class)
    ->withClosure(function (HttpResponse $response, HttpRequest $request) {
        $closure = function ($arg) {
            sleep(1 * $arg);
            return $arg * 3;
        };

        $pool = new ThreadPool();

        $th1 = $pool->addWorker($closure, 3);
        $th2 = $pool->addWorker($closure, 2);
        $this->assertEquals(0, $pool->countActiveWorkers());

        $pool->startAll();

        $th3 = $pool->addWorker($closure, 1);

        $pool->waitForCompletion();

        $response->write([
            'thr1' => $pool->getThreadResult($th1), // 9
            'thr2' => $pool->getThreadResult($th2), // 6
            'thr3' => $pool->getThreadResult($th3)  // 3
        ]);
    })
);

$routeDefinition->addRoute(Route::get('/promise')
    ->withOutputProcessor(JsonOutputProcessor::class)
    ->withClosure(function (HttpResponse $response, HttpRequest $request) {
        $response->write(\ByJG\PHPThread\Promise::resolve(3)->await());
    })
);

$restServer = new HttpRequestHandler();
$restServer->handle($routeDefinition);