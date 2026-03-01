<?php
// PHP 8.4 patch for deprecated features

// 1. dynamic_properties deprecated - bunu düzeltmek için
if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
    // Magic __get/__set kullanılan yerler için
    class stdClass84 extends stdClass {
        public function __get($name) {
            return null;
        }
        
        public function __set($name, $value) {
            $this->$name = $value;
        }
    }
}

// 2. mbstring erişimi için
if (!function_exists('mb_strlen') && function_exists('strlen')) {
    function mb_strlen($string, $encoding = null) {
        return strlen($string);
    }
}

// 3. JSON_ERROR deprecations
if (!defined('JSON_ERROR_NONE')) {
    define('JSON_ERROR_NONE', 0);
}
