<?php

define('TIMENOW',   time());
	
require('common.php');

db_init();
cleanup();
//$cache->gc(TIMENOW + 12000);