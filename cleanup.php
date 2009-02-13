<?php

define('TIMENOW', time());

include_once (dirname(realpath(__FILE__)) . '/common.php');

db_init();
cleanup();
//$cache->gc(TIMENOW + 12000);