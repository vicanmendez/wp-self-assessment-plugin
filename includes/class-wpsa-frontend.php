<?php
/**
 * Clase para funcionalidades del frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSA_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor privado para singleton
    }
}
