<?php

require "vendor/autoload.php";

class Sample
{
    public function __construct()
    {
        $thr1 = \ByJG\PHPThread\Thread::create(function () {});
        echo "----------------------------------------------------\n";
        echo $thr1->getClassName() . "\n";
        echo "----------------------------------------------------\n";
    }
}

new Sample();