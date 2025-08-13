<?php

// Bootstrap file for PHPUnit tests
// This file contains mock WordPress functions for testing

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('get_plugin_data')) {
    function get_plugin_data($file)
    {
        return [];
    }
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';
