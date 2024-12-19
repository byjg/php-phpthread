<?php


use PHPUnit\Framework\TestCase;

class PromiseTest extends TestCase
{
    public function saveToFile($content, $append = true)
    {
        if ($content instanceof \ByJG\PHPThread\PromiseStatus) {
            $content = $content->value;
        }
        file_put_contents("/tmp/promise.txt", $content . "\n", $append ? FILE_APPEND : 0);
    }

    public function validatePromises(\ByJG\PHPThread\PromiseInterface $promise): void
    {
        $this->saveToFile("A", false);
        $this->saveToFile($promise->getPromiseStatus());

        $promise
            ->then(
                function ($value) {
                    $this->saveToFile("Success: $value");
                },
                function ($value) {
                    $this->saveToFile("Failure: $value");
                }
            );

        $this->saveToFile("B");
        $this->saveToFile($promise->getPromiseStatus());

        $this->saveToFile("C");
        $result = $promise->await();
        $this->saveToFile(print_r($result, true));

        $this->saveToFile("D");
        $this->saveToFile($promise->getPromiseStatus());

        $this->saveToFile("E");
        $promise
            ->then(
                function ($value) {
                    $this->saveToFile("New Success: $value");
                },
                function ($value) {
                    $this->saveToFile("New Failure: $value");
                }
            );
    }

    public function testPromiseResolve()
    {
        if (extension_loaded('parallel')) {
            $this->markTestSkipped(
                'Promise test is not compatible with parallel extension'
            );
        }
        $promise = new \ByJG\PHPThread\Promise(function ($resolve, $reject) {
            sleep(1);
            $resolve("Promise is fulfilled!");
        });

        $this->validatePromises($promise);

        $this->assertEquals(
<<<'EOT'
A
pending
B
pending
C
Success: Promise is fulfilled!
Array
(
    [0] => Promise is fulfilled!
)

D
fulfilled
E
New Success: Promise is fulfilled!

EOT
            ,
            file_get_contents("/tmp/promise.txt")
        );
    }

    public function testPromiseResolveNested()
    {
        if (extension_loaded('parallel')) {
            $this->markTestSkipped(
                'Promise test is not compatible with parallel extension'
            );
        }

        $promise = new \ByJG\PHPThread\Promise(function ($resolve, $reject) {
            sleep(1);
            $resolve("Promise is fulfilled!");
        });

        $this->saveToFile("A", false);
        $this->saveToFile($promise->getPromiseStatus());

        $promise
            ->then(
                function ($value) {
                    usleep(500);
                    $this->saveToFile("Success: $value");
                },
                function ($value) {
                    $this->saveToFile("Failure: $value");
                }
            )
            ->then(
                function ($value) {
                    $this->saveToFile("New Success: $value");
                },
                function ($value) {
                    $this->saveToFile("New Failure: $value");
                }
            );

        $this->saveToFile("B");
        $this->saveToFile($promise->getPromiseStatus());

        $this->saveToFile("C");
        $result = $promise->await();
        $this->saveToFile(print_r($result, true));

        $this->saveToFile("D");
        $this->saveToFile($promise->getPromiseStatus());


        $this->assertEquals(
            <<<'EOT'
A
pending
B
pending
C
New Success: Promise is fulfilled!
Success: Promise is fulfilled!
Array
(
    [0] => Promise is fulfilled!
)

D
fulfilled

EOT
            ,
            file_get_contents("/tmp/promise.txt")
        );
    }

    public function testPromiseReject()
    {
        if (extension_loaded('parallel')) {
            $this->markTestSkipped(
                'Promise test is not compatible with parallel extension'
            );
        }

        $promise = new \ByJG\PHPThread\Promise(function ($resolve, $reject) {
            sleep(1);
            $reject("Promise is rejected!");
        });

        $this->validatePromises($promise);

        $this->assertEquals(
            <<<'EOT'
A
pending
B
pending
C
Failure: Promise is rejected!
Array
(
    [0] => Promise is rejected!
)

D
rejected
E
New Failure: Promise is rejected!

EOT
            ,
            file_get_contents("/tmp/promise.txt")
        );
    }


}
