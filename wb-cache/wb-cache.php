<?php
namespace wbcache;

class WbCache {

    private $detect;

    private $start_time;

    public function __construct() {
        $this->force_cache = intval(filter_input(INPUT_GET, 'wbcache_cache', FILTER_SANITIZE_NUMBER_INT)) === 1;
        $this->force_device_type = filter_input(INPUT_GET, 'wbcache_device', FILTER_SANITIZE_STRING);

        if ($this->force_device_type == 'mobile') {
            $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1';
        }

        $_SERVER['REQUEST_URI'] = str_replace('/?wbcache_cache=1&wbcache_device=mobile', '/', $_SERVER['REQUEST_URI']);
        $_SERVER['REQUEST_URI'] = str_replace('/?wbcache_cache=1', '/', $_SERVER['REQUEST_URI']);




        $this->start_time = microtime(true);

        $this->detect = new Mobile_Detect();


        $this->device_type = 'computer';
        if ($this->detect->isMobile() || $this->detect->isTablet()) {
            $this->device_type = 'mobile';
        }

        $log_segment = date('Y-m-d-H');

        $this->config = (object)[
            'cache_dir' => 'uploads/cache/',
            // Katrai stundai savs log fails            
            'log_filename' => 'uploads/cache/requests-'.$log_segment.'.log',
        ];

        $ip = $_SERVER['REMOTE_ADDR'];
        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        $this->session_stamp = date('Y-m-d H:i:s ').$ip;

        if (!file_exists($this->config->log_filename)) {
            touch($this->config->log_filename);
            chmod($this->config->log_filename, 0777);
        }
    }

    /**
     * Return current url path. Only path without domain
     */
    private function get_current_path() {
        return $_SERVER['REQUEST_URI'];
    }

    public function log($data) {
        file_put_contents(
            $this->config->log_filename, 
            
            implode("\t", [
                $this->session_stamp, 
                $_SERVER['REQUEST_METHOD'],
                $data

            ])."\n",

            FILE_APPEND
        );
    }

    private function can_cache_request() {
        $s = true;

        if (strtoupper($_SERVER['REQUEST_METHOD']) != 'GET') {
            $s = false;
        }

        // Pārbaudām vai ir wordpress_logged_in* cookie
        foreach ($_COOKIE as $key => $value) {
            if (strpos($key, 'wordpress_logged_in') === 0) {
                $s = false;
            }
        }

        return $s;
    }

    private function cache_request($path, $content) {
        $cfn = md5($path).'-'.$this->device_type;

        $fn = $this->config->cache_dir.$cfn;

        if (is_writable($this->config->cache_dir)) {
            file_put_contents($fn, $content);
            chmod($fn, 0777);
        }
        else {
            echo 'wbcache: cant write cache '.$this->config->cache_dir;
            die();
        }
    }

    private function is_request_cached($path) {
        $cfn = md5($path).'-'.$this->device_type;

        $fn = $this->config->cache_dir.$cfn;

        if (file_exists($fn)) {

            // Pārbaudām cache timeout

            return file_get_contents($fn);
        }

        return false;
    }

    public function get_response($wp_index) {
        $path = $this->get_current_path();

        $this->log($path);

        if ($this->force_cache) {
            $cached = false;
            $can_cache = true;
        }
        else {
            $cached = $this->is_request_cached($path);
            //$can_cache = $this->can_cache_request();
            $can_cache = false;
        }

        if ($cached === false) {
            $response = $this->get_wp_content($wp_index);

            if ($can_cache) {
                $this->cache_request($path, $response);
            }
        }
        else {
            $response = $cached;
        }

        $this->log('END '.(microtime(true) - $this->start_time));

        return $response;
    }

    private function get_wp_content($wp_index) {
        ob_start();
        
        include($wp_index);

        $r = ob_get_contents();
        ob_end_clean();

        return $r;
    }
}