<?php

require "vendor/autoload.php";

class Sample
{
    public function threadMethod($arg)
    {
        return $arg;
    }

    public function __construct()
    {
        $thr1 = new \ByJG\PHPThread\Thread([$this, 'threadMethod']);
        echo "----------------------------------------------------\n";
        echo $thr1->getClassName() . "\n";
        echo "----------------------------------------------------\n";
    }
}

new Sample();