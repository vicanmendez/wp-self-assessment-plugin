<?php
/**
 * Clase para administración del plugin WP Self Assessment
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSA_Admin {
    
    private static $instance = null;
    private $database;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->database = WPSA_Database::get_instance();
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_wpsa_analyze_pdf', array($this, 'analyze_pdf'));
        add_action('wp_ajax_wpsa_analyze_pdf_iframe', array($this, 'analyze_pdf_iframe'));
        add_action('wp_ajax_wpsa_upload_pdf', array($this, 'upload_pdf'));
        add_action('wp_ajax_wpsa_delete_materia', array($this, 'delete_materia'));
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Autoevaluaciones', 'wp-self-assessment'),
            __('Autoevaluaciones', 'wp-self-assessment'),
            'manage_options',
            'wpsa-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-clipboard',
            30
        );
        
        add_submenu_page(
            'wpsa-dashboard',
            __('Configuración', 'wp-self-assessment'),
            __('Configuración', 'wp-self-assessment'),
            'manage_options',
            'wpsa-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'wpsa-dashboard',
            __('Materias', 'wp-self-assessment'),
            __('Materias', 'wp-self-assessment'),
            'manage_options',
            'wpsa-materias',
            array($this, 'materias_page')
        );
        
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting('wpsa_settings', 'wpsa_gemini_api_key');
        register_setting('wpsa_settings', 'wpsa_recaptcha_site_key');
        register_setting('wpsa_settings', 'wpsa_recaptcha_secret_key');
        register_setting('wpsa_settings', 'wpsa_max_questions_per_session');
    }
    
    /**
     * Página de dashboard
     */
    public function dashboard_page() {
        // Aplicar filtros si existen
        $filters = array('limit' => 10); // Aumentar límite para mostrar más resultados

        if (isset($_GET['materia_id']) && !empty($_GET['materia_id'])) {
            $filters['materia_id'] = intval($_GET['materia_id']);
        }

        if (isset($_GET['fecha_desde']) && !empty($_GET['fecha_desde'])) {
            $filters['fecha_desde'] = sanitize_text_field($_GET['fecha_desde']);
        }

        if (isset($_GET['fecha_hasta']) && !empty($_GET['fecha_hasta'])) {
            $filters['fecha_hasta'] = sanitize_text_field($_GET['fecha_hasta']);
        }

        $estadisticas = $this->database->get_estadisticas($filters);

        // Obtener materias del usuario actual (si no es administrador, solo las suyas)
        $current_user = wp_get_current_user();
        $materia_filters = array();

        if (!current_user_can('manage_options') || isset($_GET['my_subjects'])) {
            $materia_filters['user_id'] = $current_user->ID;
        }

        $materias = $this->database->get_materias($materia_filters);
        $autoevaluaciones_recientes = $this->database->get_autoevaluaciones($filters);

        include WPSA_PLUGIN_PATH . 'includes/admin/dashboard.php';
    }
    
    /**
     * Página de configuración
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $api_key = get_option('wpsa_gemini_api_key', '');
        $recaptcha_site_key = get_option('wpsa_recaptcha_site_key', '');
        $recaptcha_secret_key = get_option('wpsa_recaptcha_secret_key', '');
        $max_questions = get_option('wpsa_max_questions_per_session', 10);
        
        include WPSA_PLUGIN_PATH . 'includes/admin/settings.php';
    }
    
    /**
     * Página de materias
     */
    public function materias_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $materia_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        switch ($action) {
            case 'edit':
            case 'add':
                $this->materia_form_page($materia_id);
                break;
            default:
                $this->materias_list_page();
                break;
        }
    }
    
    
    /**
     * Lista de materias
     */
    private function materias_list_page() {
        $filters = array();

        // Filtrar por usuario actual si no es administrador
        $current_user = wp_get_current_user();
        if (!current_user_can('manage_options')) {
            $filters['user_id'] = $current_user->ID;
        } elseif (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
            $filters['user_id'] = intval($_GET['user_id']);
        }

        if (isset($_GET['grado']) && !empty($_GET['grado'])) {
            $filters['grado'] = sanitize_text_field($_GET['grado']);
        }

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $filters['search'] = sanitize_text_field($_GET['search']);
        }

        $materias = $this->database->get_materias($filters);
        $grados = $this->database->get_grados();

        include WPSA_PLUGIN_PATH . 'includes/admin/materias-list.php';
    }
    
    /**
     * Formulario de materia
     */
    private function materia_form_page($materia_id = 0) {
        $materia = null;
        if ($materia_id > 0) {
            $materia = $this->database->get_materia($materia_id);

            // Verificar que el usuario tenga permisos para editar esta materia
            $current_user = wp_get_current_user();
            if (!current_user_can('manage_options') && $materia && $materia->user_id != $current_user->ID) {
                wp_die(__('No tienes permisos para editar esta materia.', 'wp-self-assessment'));
            }
        }

        if (isset($_POST['submit'])) {
            $this->save_materia();
        }

        include WPSA_PLUGIN_PATH . 'includes/admin/materia-form.php';
    }
    
    /**
     * Guardar configuraciones
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpsa_settings')) {
            wp_die(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        update_option('wpsa_gemini_api_key', sanitize_text_field($_POST['wpsa_gemini_api_key']));
        update_option('wpsa_recaptcha_site_key', sanitize_text_field($_POST['wpsa_recaptcha_site_key']));
        update_option('wpsa_recaptcha_secret_key', sanitize_text_field($_POST['wpsa_recaptcha_secret_key']));
        update_option('wpsa_max_questions_per_session', intval($_POST['wpsa_max_questions_per_session']));
        
        // Redirigir con mensaje de confirmación
        wp_redirect(admin_url('admin.php?page=wpsa-settings&settings-updated=true'));
        exit;
    }
    
    /**
     * Guardar materia
     */
    private function save_materia() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpsa_materia')) {
            wp_die(__('Error de seguridad', 'wp-self-assessment'));
        }

        $current_user = wp_get_current_user();
        $data = array(
            'id' => intval($_POST['materia_id']),
            'user_id' => $current_user->ID,
            'nombre' => sanitize_text_field($_POST['nombre']),
            'grado' => sanitize_text_field($_POST['grado']),
            'descripcion' => sanitize_textarea_field($_POST['descripcion']),
            'temario' => sanitize_textarea_field($_POST['temario']),
            'programa_pdf' => sanitize_url($_POST['programa_pdf']),
            'temario_analizado' => sanitize_textarea_field($_POST['temario_analizado'])
        );

        $result = $this->database->save_materia($data);

        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Materia guardada correctamente', 'wp-self-assessment') . '</p></div>';
            });

            wp_redirect(admin_url('admin.php?page=wpsa-materias'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Error al guardar la materia', 'wp-self-assessment') . '</p></div>';
            });
        }
    }
    
    /**
     * Analizar PDF con Gemini
     */
    public function analyze_pdf() {
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_analyze_pdf')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        $pdf_url = isset($_POST['pdf_url']) ? sanitize_url($_POST['pdf_url']) : '';
        $pdf_file_id = isset($_POST['pdf_file_id']) ? sanitize_text_field($_POST['pdf_file_id']) : '';
        $materia_id = intval($_POST['materia_id']);
        
        // Determinar si es URL o archivo subido
        if (!empty($pdf_file_id)) {
            // Archivo subido
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/wpsa-pdfs/' . $pdf_file_id;
            $pdf_url = $upload_dir['baseurl'] . '/wpsa-pdfs/' . $pdf_file_id;
            
            if (!file_exists($file_path)) {
                wp_send_json_error(__('Archivo PDF no encontrado', 'wp-self-assessment'));
            }
        } elseif (!empty($pdf_url)) {
            // URL externa
            if (!filter_var($pdf_url, FILTER_VALIDATE_URL)) {
                wp_send_json_error(__('URL del PDF no válida', 'wp-self-assessment'));
            }
        } else {
            wp_send_json_error(__('URL del PDF o archivo requerido', 'wp-self-assessment'));
        }
        
        // Verificar que la API key esté configurada
        $api_key = get_option('wpsa_gemini_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(__('API Key de Gemini no configurada', 'wp-self-assessment'));
        }
        
        try {
            $gemini_api = WPSA_Gemini_API::get_instance();
            $temario_analizado = $gemini_api->analyze_pdf($pdf_url);
            
            if ($temario_analizado && !empty(trim($temario_analizado))) {
                // Actualizar la materia con el temario analizado
                $materia = $this->database->get_materia($materia_id);
                if ($materia) {
                    $data = (array) $materia;
                    $data['temario_analizado'] = $temario_analizado;
                    $this->database->save_materia($data);
                }
                
                wp_send_json_success(array(
                    'temario' => $temario_analizado,
                    'message' => __('PDF analizado correctamente', 'wp-self-assessment')
                ));
            } else {
                wp_send_json_error(__('No se pudo extraer contenido del PDF. Verifica que el archivo sea válido y accesible.', 'wp-self-assessment'));
            }
        } catch (Exception $e) {
            error_log('WPSA: Error analizando PDF: ' . $e->getMessage());
            wp_send_json_error(__('Error interno al analizar el PDF: ', 'wp-self-assessment') . $e->getMessage());
        }
    }
    
    /**
     * Mostrar análisis de PDF en iframe
     */
    public function analyze_pdf_iframe() {
        // Verificar que el usuario tenga permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción', 'wp-self-assessment'));
        }
        
        // Verificar nonce del formulario principal
        $nonce = $_GET['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wpsa_materia')) {
            wp_die(__('Error de seguridad - Token inválido', 'wp-self-assessment'));
        }
        
        $pdf_url = isset($_GET['pdf_url']) ? sanitize_url($_GET['pdf_url']) : '';
        $pdf_file_id = isset($_GET['pdf_file_id']) ? sanitize_text_field($_GET['pdf_file_id']) : '';
        $materia_id = intval($_GET['materia_id']);
        
        // Determinar si es URL o archivo subido
        if (!empty($pdf_file_id)) {
            // Archivo subido
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/wpsa-pdfs/' . $pdf_file_id;
            $pdf_url = $upload_dir['baseurl'] . '/wpsa-pdfs/' . $pdf_file_id;
            
            if (!file_exists($file_path)) {
                wp_die(__('Archivo PDF no encontrado', 'wp-self-assessment'));
            }
        } elseif (!empty($pdf_url)) {
            // URL externa
            if (!filter_var($pdf_url, FILTER_VALIDATE_URL)) {
                wp_die(__('URL del PDF no válida', 'wp-self-assessment'));
            }
        } else {
            wp_die(__('URL del PDF o archivo requerido', 'wp-self-assessment'));
        }
        
        // Verificar que la API key esté configurada
        $api_key = get_option('wpsa_gemini_api_key', '');
        if (empty($api_key)) {
            wp_die(__('API Key de Gemini no configurada', 'wp-self-assessment'));
        }
        
        // Renderizar la página de análisis
        $this->render_pdf_analysis_page($pdf_url, $materia_id);
    }
    
    /**
     * Renderizar página de análisis de PDF
     */
    private function render_pdf_analysis_page($pdf_url, $materia_id) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php _e('Analizando PDF con IA', 'wp-self-assessment'); ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: #f1f1f1;
                }
                .wpsa-analysis-container {
                    max-width: 800px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .wpsa-analysis-header {
                    background: #0073aa;
                    color: white;
                    padding: 20px;
                    text-align: center;
                }
                .wpsa-analysis-content {
                    padding: 30px;
                }
                .wpsa-progress-container {
                    text-align: center;
                    margin: 30px 0;
                }
                .wpsa-progress-bar {
                    width: 100%;
                    height: 20px;
                    background: #f0f0f0;
                    border-radius: 10px;
                    overflow: hidden;
                    margin: 20px 0;
                }
                .wpsa-progress-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #0073aa, #00a0d2);
                    width: 0%;
                    transition: width 0.3s ease;
                    animation: pulse 2s infinite;
                }
                @keyframes pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.7; }
                    100% { opacity: 1; }
                }
                .wpsa-progress-text {
                    margin: 15px 0;
                    color: #666;
                    font-size: 16px;
                }
                .wpsa-result-container {
                    display: none;
                    margin-top: 30px;
                }
                .wpsa-temario-preview {
                    background: #f9f9f9;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 20px;
                    max-height: 400px;
                    overflow-y: auto;
                    white-space: pre-wrap;
                    font-family: 'Courier New', monospace;
                    font-size: 14px;
                    line-height: 1.6;
                }
                .wpsa-actions {
                    margin-top: 20px;
                    text-align: center;
                    display: flex;
                    gap: 10px;
                    justify-content: center;
                }
                .wpsa-error {
                    background: #f8d7da;
                    color: #721c24;
                    padding: 15px;
                    border-radius: 4px;
                    border: 1px solid #f5c6cb;
                    margin: 20px 0;
                }
                .wpsa-success {
                    background: #d4edda;
                    color: #155724;
                    padding: 15px;
                    border-radius: 4px;
                    border: 1px solid #c3e6cb;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="wpsa-analysis-container">
                <div class="wpsa-analysis-header">
                    <h2><?php _e('Analizando PDF con IA', 'wp-self-assessment'); ?></h2>
                    <p><?php _e('Extrayendo y analizando contenido del documento...', 'wp-self-assessment'); ?></p>
                </div>
                
                <div class="wpsa-analysis-content">
                    <div class="wpsa-progress-container">
                        <div class="wpsa-progress-bar">
                            <div class="wpsa-progress-fill" id="progress-fill"></div>
                        </div>
                        <p class="wpsa-progress-text" id="progress-text"><?php _e('Iniciando análisis...', 'wp-self-assessment'); ?></p>
                    </div>
                    
                    <div class="wpsa-result-container" id="result-container">
                        <h3><?php _e('Temario Generado:', 'wp-self-assessment'); ?></h3>
                        <div class="wpsa-temario-preview" id="temario-preview"></div>
                        <div class="wpsa-actions">
                            <button type="button" id="accept-temario" class="button button-primary">
                                <?php _e('Aceptar Temario', 'wp-self-assessment'); ?>
                            </button>
                            <button type="button" id="reject-temario" class="button">
                                <?php _e('Rechazar', 'wp-self-assessment'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var pdfUrl = '<?php echo esc_js($pdf_url); ?>';
                var pdfFileId = '<?php echo esc_js($pdf_file_id); ?>';
                var materiaId = <?php echo intval($materia_id); ?>;
                var progress = 0;
                var progressInterval;
                
                // Simular progreso
                function startProgress() {
                    progressInterval = setInterval(function() {
                        progress += Math.random() * 15;
                        if (progress > 90) progress = 90;
                        
                        $('#progress-fill').css('width', progress + '%');
                        
                        if (progress < 30) {
                            $('#progress-text').text('<?php _e('Descargando PDF...', 'wp-self-assessment'); ?>');
                        } else if (progress < 60) {
                            $('#progress-text').text('<?php _e('Enviando a Gemini AI...', 'wp-self-assessment'); ?>');
                        } else if (progress < 90) {
                            $('#progress-text').text('<?php _e('Procesando contenido...', 'wp-self-assessment'); ?>');
                        }
                    }, 500);
                }
                
                // Iniciar análisis
                function startAnalysis() {
                    startProgress();
                    
                    var ajaxData = {
                        action: 'wpsa_analyze_pdf',
                        materia_id: materiaId,
                        nonce: '<?php echo wp_create_nonce('wpsa_analyze_pdf'); ?>'
                    };
                    
                    if (pdfFileId) {
                        ajaxData.pdf_file_id = pdfFileId;
                    } else {
                        ajaxData.pdf_url = pdfUrl;
                    }
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: ajaxData,
                        success: function(response) {
                            clearInterval(progressInterval);
                            $('#progress-fill').css('width', '100%');
                            
                            if (response.success) {
                                $('#progress-text').text('<?php _e('Análisis completado', 'wp-self-assessment'); ?>');
                                $('#temario-preview').text(response.data.temario);
                                $('#result-container').show();
                                
                                // Notificar al padre
                                if (window.parent && window.parent.postMessage) {
                                    window.parent.postMessage({
                                        type: 'pdf_analysis_success',
                                        temario: response.data.temario
                                    }, '*');
                                }
                            } else {
                                showError(response.data || '<?php _e('Error desconocido', 'wp-self-assessment'); ?>');
                            }
                        },
                        error: function(xhr, status, error) {
                            clearInterval(progressInterval);
                            showError('<?php _e('Error de conexión: ', 'wp-self-assessment'); ?>' + error);
                        }
                    });
                }
                
                function showError(message) {
                    $('#progress-container').html('<div class="wpsa-error">' + message + '</div>');
                    
                    // Notificar al padre
                    if (window.parent && window.parent.postMessage) {
                        window.parent.postMessage({
                            type: 'pdf_analysis_error',
                            error: message
                        }, '*');
                    }
                }
                
                // Manejar botones
                $('#accept-temario').on('click', function() {
                    if (window.parent && window.parent.postMessage) {
                        window.parent.postMessage({
                            type: 'pdf_analysis_accept',
                            temario: $('#temario-preview').text()
                        }, '*');
                    }
                });
                
                $('#reject-temario').on('click', function() {
                    if (window.parent && window.parent.postMessage) {
                        window.parent.postMessage({
                            type: 'pdf_analysis_reject'
                        }, '*');
                    }
                });
                
                // Iniciar análisis
                startAnalysis();
            });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Subir PDF
     */
    public function upload_pdf() {
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_materia')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'wp-self-assessment'));
        }
        
        if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Error al subir el archivo', 'wp-self-assessment'));
        }
        
        $file = $_FILES['pdf_file'];
        
        // Verificar que sea PDF
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['type'] !== 'application/pdf') {
            wp_send_json_error(__('El archivo debe ser un PDF válido', 'wp-self-assessment'));
        }
        
        // Verificar tamaño (máximo 20MB)
        if ($file['size'] > 20 * 1024 * 1024) {
            wp_send_json_error(__('El archivo es demasiado grande. Máximo 20MB', 'wp-self-assessment'));
        }
        
        // Crear directorio de uploads si no existe
        $upload_dir = wp_upload_dir();
        $wpsa_dir = $upload_dir['basedir'] . '/wpsa-pdfs';
        if (!file_exists($wpsa_dir)) {
            wp_mkdir_p($wpsa_dir);
        }
        
        // Generar nombre único para el archivo
        $file_name = 'pdf_' . time() . '_' . wp_generate_password(8, false) . '.pdf';
        $file_path = $wpsa_dir . '/' . $file_name;
        
        // Mover archivo
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $file_url = $upload_dir['baseurl'] . '/wpsa-pdfs/' . $file_name;
            
            wp_send_json_success(array(
                'file_id' => $file_name,
                'file_url' => $file_url,
                'file_path' => $file_path,
                'message' => __('Archivo subido correctamente', 'wp-self-assessment')
            ));
        } else {
            wp_send_json_error(__('Error al guardar el archivo', 'wp-self-assessment'));
        }
    }
    
    /**
     * Eliminar materia
     */
    public function delete_materia() {
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_delete_materia')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }

        $materia_id = intval($_POST['materia_id']);

        // Verificar permisos para eliminar la materia
        $materia = $this->database->get_materia($materia_id);
        $current_user = wp_get_current_user();

        if (!$materia) {
            wp_send_json_error(__('Materia no encontrada', 'wp-self-assessment'));
        }

        if (!current_user_can('manage_options') && $materia->user_id != $current_user->ID) {
            wp_send_json_error(__('No tienes permisos para eliminar esta materia', 'wp-self-assessment'));
        }

        $result = $this->database->delete_materia($materia_id);

        if ($result) {
            wp_send_json_success(__('Materia eliminada correctamente', 'wp-self-assessment'));
        } else {
            wp_send_json_error(__('Error al eliminar la materia', 'wp-self-assessment'));
        }
    }
}
