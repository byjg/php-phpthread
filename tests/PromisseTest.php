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
        $this->saveToFile(print_r($promise->await(), true));

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

    public function testPromisseReject()
    {
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
