<?php


use PHPUnit\Framework\TestCase;

class PromisseTest extends TestCase
{
    public function saveToFile($content, $append = true)
    {
        if ($content instanceof \ByJG\PHPThread\PromisseStatus) {
            $content = $content->value;
        }
        file_put_contents("/tmp/promisse.txt", $content . "\n", $append ? FILE_APPEND : 0);
    }

    public function validatePromisses(\ByJG\PHPThread\PromisseInterface $promise)
    {
        $this->saveToFile("A", false);
        $this->saveToFile($promise->getPromisseStatus());

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
        $this->saveToFile($promise->getPromisseStatus());

        $this->saveToFile("C");
        $result = $promise->await();
        $this->saveToFile(print_r($result, true));

        $this->saveToFile("D");
        $this->saveToFile($promise->getPromisseStatus());

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
    public function testPromisseResolve()
    {
        if (extension_loaded('parallel')) {
            $this->markTestSkipped(
                'Promisse test is not compatible with parallel extension'
            );
        }
        $promise = new \ByJG\PHPThread\Promisse(function ($resolve, $reject) {
            sleep(1);
            $resolve("Promise is fulfilled!");
        });

        $this->validatePromisses($promise);

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
            file_get_contents("/tmp/promisse.txt")
        );
    }

    public function testPromisseResolveNested()
    {
        if (extension_loaded('parallel')) {
            $this->markTestSkipped(
                'Promisse test is not compatible with parallel extension'
            );
        }

        $promise = new \ByJG\PHPThread\Promisse(function ($resolve, $reject) {
            sleep(1);
            $resolve("Promise is fulfilled!");
        });

        $this->saveToFile("A", false);
        $this->saveToFile($promise->getPromisseStatus());

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
        $this->saveToFile($promise->getPromisseStatus());

        $this->saveToFile("C");
        $result = $promise->await();
        $this->saveToFile(print_r($result, true));

        $this->saveToFile("D");
        $this->saveToFile($promise->getPromisseStatus());


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
            file_get_contents("/tmp/promisse.txt")
        );
    }

    public function testPromisseReject()
    {
        if (extension_loaded('parallel')) {
            $this->markTestSkipped(
                'Promisse test is not compatible with parallel extension'
            );
        }

        $promise = new \ByJG\PHPThread\Promisse(function ($resolve, $reject) {
            sleep(1);
            $reject("Promise is rejected!");
        });

        $this->validatePromisses($promise);

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
            file_get_contents("/tmp/promisse.txt")
        );
    }


}
