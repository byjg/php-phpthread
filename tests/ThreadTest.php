<?php


use PHPUnit\Framework\TestCase;

class ThreadTest extends TestCase
{
    public function threadMethod($arg)
    {
        sleep($arg*3);
        return $arg * 3;
    }

    public function testThread()
    {
        $thr1 = new \ByJG\PHPThread\Thread([$this, 'threadMethod']);
        $thr2 = new \ByJG\PHPThread\Thread([$this, 'threadMethod']);

        // Start Threads
        $thr1->execute(2);
        $thr2->execute(1);

        // Make sure they are running
        $this->assertTrue($thr1->isAlive());
        $this->assertTrue($thr2->isAlive());

        // Wait to Finish
        $thr1->waitFinish();
        $thr2->waitFinish();

        // Make sure they're finished
        $this->assertFalse($thr1->isAlive());
        $this->assertFalse($thr2->isAlive());

        // Get the thread result
        $this->assertEquals(6, $thr1->getResult());
        $this->assertEquals(3, $thr2->getResult());
    }
}
