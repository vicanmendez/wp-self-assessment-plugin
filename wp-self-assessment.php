<?php
/**
 * Plugin Name: WP Self Assessment
 * Description: Plugin para autoevaluaciones estudiantiles con integración de IA Gemini. Permite a profesores configurar materias y a estudiantes realizar autoevaluaciones interactivas.
 * Version: 1.0.0
 * Author: Prof. Víctor Méndez
 * License: GPL v2 or later
 * Text Domain: wp-self-assessment
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('WPSA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPSA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPSA_VERSION', '1.0.0');

// Incluir archivos necesarios
require_once WPSA_PLUGIN_PATH . 'includes/class-wpsa-database.php';
require_once WPSA_PLUGIN_PATH . 'includes/class-wpsa-admin.php';
require_once WPSA_PLUGIN_PATH . 'includes/class-wpsa-frontend.php';
require_once WPSA_PLUGIN_PATH . 'includes/class-wpsa-gemini-api.php';
require_once WPSA_PLUGIN_PATH . 'includes/class-wpsa-shortcode.php';
require_once WPSA_PLUGIN_PATH . 'includes/class-wpsa-ajax.php';

/**
 * Clase principal del plugin
 */
class WP_Self_Assessment {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Inicializar componentes
        WPSA_Database::get_instance();
        WPSA_Admin::get_instance();
        WPSA_Frontend::get_instance();
        WPSA_Gemini_API::get_instance();
        WPSA_Shortcode::get_instance();
        WPSA_Ajax::get_instance();
        
        // Cargar scripts y estilos
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('wp-self-assessment', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('wpsa-frontend', WPSA_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), WPSA_VERSION, true);
        wp_enqueue_style('wpsa-frontend', WPSA_PLUGIN_URL . 'assets/css/frontend.css', array(), WPSA_VERSION);
        
        // Localizar script para AJAX
        wp_localize_script('wpsa-frontend', 'wpsa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpsa_nonce'),
            'recaptcha_site_key' => get_option('wpsa_recaptcha_site_key', '')
        ));
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wpsa') !== false) {
            wp_enqueue_script('wpsa-admin', WPSA_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WPSA_VERSION, true);
            wp_enqueue_script('wpsa-notifications-fix', WPSA_PLUGIN_URL . 'assets/js/admin-notifications-fix.js', array('jquery'), WPSA_VERSION, true);
            wp_enqueue_style('wpsa-admin', WPSA_PLUGIN_URL . 'assets/css/admin.css', array(), WPSA_VERSION);
            wp_enqueue_style('wpsa-notifications-fix', WPSA_PLUGIN_URL . 'assets/css/admin-notifications-fix.css', array(), WPSA_VERSION);
        }
    }
    
    public function activate() {
        // Crear tablas de la base de datos
        WPSA_Database::create_tables();
        
        // Crear opciones por defecto
        add_option('wpsa_gemini_api_key', '');
        add_option('wpsa_recaptcha_site_key', '');
        add_option('wpsa_recaptcha_secret_key', '');
        add_option('wpsa_max_questions_per_session', 10);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Inicializar el plugin
WP_Self_Assessment::get_instance();
