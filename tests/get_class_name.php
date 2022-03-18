<?php

require "vendor/autoload.php";

class Sample
{
    public function __construct()
    {
        $thr1 = new \ByJG\PHPThread\Thread(function () {});
        echo "----------------------------------------------------\n";
        echo $thr1->getClassName() . "\n";
        echo "----------------------------------------------------\n";
    }
}

new Sample();