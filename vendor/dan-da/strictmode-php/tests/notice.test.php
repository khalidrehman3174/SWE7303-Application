<?php

namespace tester;

require_once __DIR__  . '/../vendor/autoload.php';

// require_once( __DIR__ . '/../strictmode.php');
\strictmode\initializer::init();

class notice extends test_base {

    public function runtests() {
        $this->test1();
    }
    
    protected function test1() {
        
        $code = null;
        $msg = null;

        $arr = [];
        $v = @$arr["badkey"] ? "found" : "notfound";

        $this->eq( $v, 'notfound', 'error suppression operator' );
    }

}
