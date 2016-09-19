<?php
include_once(dirname(__FILE__).'/wb-cache/mobile-detect.php');
include_once(dirname(__FILE__).'/wb-cache/wb-cache.php');

$wbcache = new \wbcache\WbCache();

echo $wbcache->get_response('index-wp.php');