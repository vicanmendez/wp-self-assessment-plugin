<?php
/**
 * Clase para manejo de peticiones AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSA_Ajax {
    
    private static $instance = null;
    private $database;
    private $gemini_api;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->database = WPSA_Database::get_instance();
        $this->gemini_api = WPSA_Gemini_API::get_instance();
        
        // AJAX para usuarios no autenticados
        add_action('wp_ajax_nopriv_wpsa_generate_question', array($this, 'generate_question'));
        add_action('wp_ajax_nopriv_wpsa_generate_dynamic_question', array($this, 'generate_dynamic_question'));
        add_action('wp_ajax_nopriv_wpsa_evaluate_answer', array($this, 'evaluate_answer'));
        add_action('wp_ajax_nopriv_wpsa_save_evaluation', array($this, 'save_evaluation'));
        add_action('wp_ajax_nopriv_wpsa_verify_recaptcha', array($this, 'verify_recaptcha'));
        add_action('wp_ajax_nopriv_wpsa_save_question_score', array($this, 'save_question_score'));
        add_action('wp_ajax_nopriv_wpsa_save_individual_question', array($this, 'save_individual_question'));
        add_action('wp_ajax_nopriv_wpsa_get_evaluation_scores', array($this, 'get_evaluation_scores'));
        add_action('wp_ajax_nopriv_wpsa_save_final_evaluation', array($this, 'save_final_evaluation'));
        add_action('wp_ajax_nopriv_wpsa_get_materia_data', array($this, 'get_materia_data'));
        
        // AJAX para usuarios autenticados
        add_action('wp_ajax_wpsa_generate_question', array($this, 'generate_question'));
        add_action('wp_ajax_wpsa_generate_dynamic_question', array($this, 'generate_dynamic_question'));
        add_action('wp_ajax_wpsa_evaluate_answer', array($this, 'evaluate_answer'));
        add_action('wp_ajax_wpsa_save_evaluation', array($this, 'save_evaluation'));
        add_action('wp_ajax_wpsa_verify_recaptcha', array($this, 'verify_recaptcha'));
        add_action('wp_ajax_wpsa_save_question_score', array($this, 'save_question_score'));
        add_action('wp_ajax_wpsa_save_individual_question', array($this, 'save_individual_question'));
        add_action('wp_ajax_wpsa_get_evaluation_scores', array($this, 'get_evaluation_scores'));
        add_action('wp_ajax_wpsa_save_final_evaluation', array($this, 'save_final_evaluation'));
        add_action('wp_ajax_wpsa_get_materia_data', array($this, 'get_materia_data'));
        
        // AJAX para reportes (solo usuarios autenticados)
        add_action('wp_ajax_wpsa_get_evaluation_details', array($this, 'get_evaluation_details'));
        add_action('wp_ajax_wpsa_delete_evaluation', array($this, 'delete_evaluation'));
        add_action('wp_ajax_wpsa_delete_multiple_evaluations', array($this, 'delete_multiple_evaluations'));
        add_action('wp_ajax_wpsa_export_csv', array($this, 'export_csv'));
        add_action('wp_ajax_wpsa_export_excel', array($this, 'export_excel'));
        add_action('wp_ajax_wpsa_export_pdf', array($this, 'export_pdf'));
    }

    /**
     * Generar nueva pregunta
     */
    public function generate_question() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }

        // Verificar reCAPTCHA si está configurado
        if (!$this->verify_recaptcha_internal()) {
            wp_send_json_error(__('Verificación reCAPTCHA fallida', 'wp-self-assessment'));
        }

        $materia_id = intval($_POST['materia_id']);
        $tema = sanitize_text_field($_POST['tema']);
        $modalidad = sanitize_text_field($_POST['modalidad']);
        $nivel = sanitize_text_field($_POST['nivel']);
        $numero_pregunta = intval($_POST['numero_pregunta']);

        // Obtener información de la materia
        $materia = $this->database->get_materia($materia_id);
        if (!$materia) {
            wp_send_json_error(__('Materia no encontrada', 'wp-self-assessment'));
        }

        // Generar pregunta con Gemini usando el temario
        $question_data = $this->gemini_api->generate_question(
            $materia->nombre,
            $tema,
            $modalidad,
            $materia->grado,
            $nivel,
            $numero_pregunta,
            $materia->temario
        );

        if (!$question_data) {
            wp_send_json_error(__('Error al generar la pregunta', 'wp-self-assessment'));
        }

        wp_send_json_success($question_data);
    }


    /**
     * Guardar pregunta individual en la base de datos
     */
    public function save_individual_question() {
        global $wpdb;

        error_log("WPSA: ===== INICIO save_individual_question =====");
        error_log("WPSA: save_individual_question called with POST data: " . print_r($_POST, true));

        // Verify nonce first
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            error_log("WPSA: ERROR - Nonce verification failed");
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'), 403);
        }

        error_log("WPSA: Nonce verification passed");

        // Validate and sanitize input
        $materia_id = filter_input(INPUT_POST, 'materia_id', FILTER_VALIDATE_INT, ['min_range' => 1]);
        $estudiante_nombre = sanitize_text_field($_POST['estudiante_nombre'] ?? '');
        $evaluation_id_provided = isset($_POST['evaluation_id']) ? intval($_POST['evaluation_id']) : null;
        $question_data = [
            'question_text' => sanitize_textarea_field($_POST['question_text'] ?? ''),
            'answer' => sanitize_textarea_field($_POST['answer'] ?? ''),
            'correct_answer' => sanitize_textarea_field($_POST['correct_answer'] ?? ''),
            'score' => min(max((int)($_POST['score'] ?? 0), 0), 10),
            'feedback' => sanitize_textarea_field($_POST['feedback'] ?? '')
        ];

        error_log("WPSA: Processed question data: " . print_r($question_data, true));
        error_log("WPSA: Evaluation ID provided: " . $evaluation_id_provided);

        // Validate required fields
        if (!$materia_id || empty($question_data['question_text']) || empty($question_data['answer'])) {
            wp_send_json_error(__('Datos incompletos para guardar pregunta', 'wp-self-assessment'), 400);
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Check materia exists
            $materia_data = $this->database->get_materia($materia_id);
            if (!$materia_data) {
                throw new Exception(__('Materia no encontrada', 'wp-self-assessment'));
            }

            // Get or create evaluation
            if ($evaluation_id_provided) {
                // Verify the provided evaluation exists and is in progress
                $table_autoeval = $wpdb->prefix . 'wpsa_autoevaluaciones';
                $existing_evaluation = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $table_autoeval
                     WHERE id = %d AND estado = 'en_progreso'",
                    $evaluation_id_provided
                ));

                if ($existing_evaluation) {
                    $evaluation_id = $evaluation_id_provided;
                    error_log("WPSA: Using provided evaluation ID: " . $evaluation_id);
                } else {
                    error_log("WPSA: Provided evaluation ID not found or not in progress, creating new one");
                    $evaluation_id = $this->get_or_create_evaluation($materia_id, $estudiante_nombre);
                }
            } else {
                $evaluation_id = $this->get_or_create_evaluation($materia_id, $estudiante_nombre);
            }

            // Save question with transaction
            // Guardar con validación completa
            $question_data_to_save = [
                'autoevaluacion_id' => $evaluation_id,
                'pregunta' => sanitize_textarea_field($question_data['question_text']),
                'respuesta_estudiante' => sanitize_textarea_field($question_data['answer']),
                'respuesta_correcta' => sanitize_textarea_field($question_data['correct_answer']),
                'puntuacion' => min(max((int)$question_data['score'], 0), 10),
                'feedback' => sanitize_textarea_field($question_data['feedback']),
                'created_at' => current_time('mysql')
            ];

            error_log("WPSA: Intentando guardar pregunta con datos: " . print_r($question_data_to_save, true));
            error_log("WPSA: Evaluation ID a usar: " . $evaluation_id);

            $question_id = $this->database->save_pregunta($question_data_to_save);
            error_log("WPSA: Resultado de save_pregunta: " . $question_id);

            if (!$question_id) {
                error_log("WPSA: ERROR - save_pregunta falló. Last error: " . $wpdb->last_error);
                throw new Exception($wpdb->last_error ?: __('Error desconocido al guardar pregunta', 'wp-self-assessment'));
            }

            // Commit transaction
            $wpdb->query('COMMIT');
            error_log("WPSA: Transaction committed successfully");

            error_log("WPSA: ===== ÉXITO save_individual_question =====");
            wp_send_json_success([
                'question_id' => $question_id,
                'evaluation_id' => $evaluation_id,
                'message' => __('Pregunta guardada correctamente', 'wp-self-assessment')
            ]);

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("WPSA ERROR en save_individual_question: " . $e->getMessage());
            error_log("WPSA ERROR Stack trace: " . $e->getTraceAsString());
            wp_send_json_error($e->getMessage(), 500);
        }
    }

    private function get_or_create_evaluation($materia_id, $estudiante_nombre) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpsa_autoevaluaciones';

        error_log("WPSA: ===== INICIO get_or_create_evaluation =====");
        error_log("WPSA: Buscando evaluación para materia_id: $materia_id, estudiante: $estudiante_nombre");

        // Check existing evaluation
        $evaluation = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table
            WHERE materia_id = %d AND estudiante_nombre = %s
            AND estado = 'en_progreso'
            ORDER BY created_at DESC LIMIT 1",
            $materia_id, $estudiante_nombre
        ));

        if ($evaluation) {
            error_log("WPSA: Evaluación existente encontrada con ID: " . $evaluation->id);
            return $evaluation->id;
        }

        error_log("WPSA: No se encontró evaluación existente, creando nueva");

        // Create new evaluation
        $insert_data = [
            'materia_id' => $materia_id,
            'estudiante_nombre' => $estudiante_nombre,
            'tema' => __('Evaluación en Progreso', 'wp-self-assessment'),
            'modalidad' => sanitize_text_field($_POST['modalidad'] ?? 'preguntas_simples'),
            'preguntas_respuestas' => wp_json_encode([]), // Initialize with empty array
            'puntuacion_total' => 0,
            'puntuacion_obtenida' => 0,
            'porcentaje' => 0.00,
            'estado' => 'en_progreso',
            'created_at' => current_time('mysql')
        ];

        error_log("WPSA: Datos para insertar nueva evaluación: " . print_r($insert_data, true));

        $result = $wpdb->insert($table, $insert_data);

        if (!$result) {
            error_log("WPSA: ERROR - Failed to create evaluation. Last error: " . $wpdb->last_error);
            error_log("WPSA: Last query: " . $wpdb->last_query);
            throw new Exception($wpdb->last_error ?: __('Error al crear evaluación', 'wp-self-assessment'));
        }

        $new_evaluation_id = $wpdb->insert_id;
        error_log("WPSA: Nueva evaluación creada con ID: " . $new_evaluation_id);
        error_log("WPSA: ===== ÉXITO get_or_create_evaluation =====");

        return $new_evaluation_id;
    }
    
    
    /**
     * Evaluar respuesta del estudiante
     */
    public function evaluate_answer() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        $pregunta = sanitize_textarea_field($_POST['pregunta']);
        $respuesta_estudiante = sanitize_textarea_field($_POST['respuesta'] ?? $_POST['respuesta_estudiante'] ?? '');
        $respuesta_correcta = sanitize_textarea_field($_POST['respuesta_correcta']);
        
        // Evaluar respuesta con Gemini
        $evaluation_data = $this->gemini_api->evaluate_answer(
            $pregunta,
            $respuesta_estudiante,
            $respuesta_correcta
        );
        
        if (!$evaluation_data) {
            wp_send_json_error(__('Error al evaluar la respuesta', 'wp-self-assessment'));
        }
        
        wp_send_json_success($evaluation_data);
    }
    
    /**
     * Guardar evaluación completa
     */
    public function save_evaluation() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        $materia_id = intval($_POST['materia_id']);
        $estudiante_nombre = sanitize_text_field($_POST['estudiante_nombre']);
        $tema = sanitize_text_field($_POST['tema']);
        $modalidad = sanitize_text_field($_POST['modalidad']);
        $preguntas_respuestas = json_decode(stripslashes($_POST['preguntas_respuestas']), true);
        
        // Calcular puntuación total
        $puntuacion_total = 0;
        $puntuacion_obtenida = 0;
        
        foreach ($preguntas_respuestas as $pregunta) {
            $puntuacion_total += intval($pregunta['puntuacion']);
            $puntuacion_obtenida += intval($pregunta['puntuacion_obtenida']);
        }
        
        $porcentaje = $puntuacion_total > 0 ? round(($puntuacion_obtenida / $puntuacion_total) * 100, 2) : 0;
        
        // Generar recomendaciones
        $autoevaluacion_data = array(
            'materia' => $this->database->get_materia($materia_id)->nombre,
            'puntuacion_obtenida' => $puntuacion_obtenida,
            'puntuacion_total' => $puntuacion_total,
            'porcentaje' => $porcentaje,
            'modalidad' => $modalidad,
            'preguntas_respuestas' => $preguntas_respuestas
        );
        
        $recomendaciones = $this->gemini_api->generate_recommendations($autoevaluacion_data);
        
        // Guardar en base de datos
        $evaluation_data = array(
            'materia_id' => $materia_id,
            'estudiante_nombre' => $estudiante_nombre,
            'tema' => $tema,
            'modalidad' => $modalidad,
            'preguntas_respuestas' => $preguntas_respuestas,
            'puntuacion_total' => $puntuacion_total,
            'puntuacion_obtenida' => $puntuacion_obtenida,
            'porcentaje' => $porcentaje,
            'recomendaciones' => $recomendaciones ?: __('No hay recomendaciones disponibles.', 'wp-self-assessment'),
            'estado' => 'completada'
        );
        
        $evaluation_id = $this->database->save_autoevaluacion($evaluation_data);
        
        if ($evaluation_id) {
            wp_send_json_success(array(
                'evaluation_id' => $evaluation_id,
                'puntuacion_obtenida' => $puntuacion_obtenida,
                'puntuacion_total' => $puntuacion_total,
                'porcentaje' => $porcentaje,
                'recomendaciones' => $recomendaciones ?: __('No hay recomendaciones disponibles.', 'wp-self-assessment')
            ));
        } else {
            wp_send_json_error(__('Error al guardar la evaluación', 'wp-self-assessment'));
        }
    }
    
    /**
     * Verificar reCAPTCHA
     */
    public function verify_recaptcha() {
        $recaptcha_response = sanitize_text_field($_POST['recaptcha_response']);
        
        if (empty($recaptcha_response)) {
            wp_send_json_error(__('Respuesta reCAPTCHA requerida', 'wp-self-assessment'));
        }
        
        $result = $this->verify_recaptcha_internal($recaptcha_response);
        
        if ($result) {
            wp_send_json_success(__('reCAPTCHA verificado correctamente', 'wp-self-assessment'));
        } else {
            wp_send_json_error(__('Verificación reCAPTCHA fallida', 'wp-self-assessment'));
        }
    }
    
    /**
     * Verificar reCAPTCHA internamente (soporta v2 y v3)
     */
    // En class-wpsa-ajax.php, REEMPLAZA esta función completa.

/**
 * Verifica reCAPTCHA internamente.
 * CORREGIDO: Ahora solo se activa si las claves están configuradas.
 */
    private function verify_recaptcha_internal($recaptcha_response = null) {
        $site_key = get_option('wpsa_recaptcha_site_key', '');
        $secret_key = get_option('wpsa_recaptcha_secret_key', '');
        
        // MODIFICACIÓN CLAVE: Si una de las claves está vacía,
        // consideramos que reCAPTCHA no está en uso y aprobamos la verificación.
        if (empty($site_key) || empty($secret_key)) {
            error_log('WPSA DEBUG: Claves reCAPTCHA no configuradas. Omitiendo verificación.');
            return true; // <-- Esto permite que el proceso continúe.
        }
        
        if (!$recaptcha_response) {
            $recaptcha_response = isset($_POST['recaptcha_token']) ? $_POST['recaptcha_token'] : 
                                (isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '');
        }
        
        if (empty($recaptcha_response)) {
            error_log('WPSA ERROR: Verificación reCAPTCHA fallida - no se recibió respuesta del cliente.');
            return false;
        }
        
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $secret_key,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log('WPSA ERROR: Error de conexión con la API de reCAPTCHA: ' . $response->get_error_message());
            return false;
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($result['success']) || $result['success'] !== true) {
            error_log('WPSA ERROR: Verificación reCAPTCHA fallida. Respuesta de Google: ' . print_r($result, true));
            return false;
        }
        
        // Para reCAPTCHA v3, se recomienda verificar el 'score'.
        if (isset($result['score'])) {
            error_log('WPSA DEBUG: reCAPTCHA v3 score: ' . $result['score']);
            return $result['score'] >= 0.5;
        }
        
        return true;
    }
    /**
     * Guardar puntuación de una pregunta individual en sesión PHP
     */
    public function save_question_score() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        // Iniciar sesión si no está iniciada
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $question_number = intval($_POST['question_number']);
        $score = intval($_POST['score']);
        $answer = sanitize_textarea_field($_POST['answer']);
        $feedback = sanitize_textarea_field($_POST['feedback']);
        
        // Inicializar array de respuestas si no existe
        if (!isset($_SESSION['wpsa_evaluation_responses'])) {
            $_SESSION['wpsa_evaluation_responses'] = array();
        }
        
        // Guardar respuesta en sesión
        $_SESSION['wpsa_evaluation_responses'][$question_number] = array(
            'question_number' => $question_number,
            'score' => $score,
            'answer' => $answer,
            'feedback' => $feedback,
            'timestamp' => current_time('mysql')
        );
        
        // Debug: log de la sesión
        error_log('WPSA Debug - Puntuación guardada en sesión: ' . print_r($_SESSION['wpsa_evaluation_responses'], true));
        
        wp_send_json_success(array(
            'message' => 'Puntuación guardada correctamente',
            'question_number' => $question_number,
            'score' => $score,
            'total_responses' => count($_SESSION['wpsa_evaluation_responses'])
        ));
    }
    
    /**
     * Obtener puntuaciones de la evaluación desde sesión PHP
     */
    public function get_evaluation_scores() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        // Iniciar sesión si no está iniciada
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $responses = isset($_SESSION['wpsa_evaluation_responses']) ? $_SESSION['wpsa_evaluation_responses'] : array();
        
        // Calcular totales
        $total_score = 0;
        $obtained_score = 0;
        $total_questions = count($responses);
        
        foreach ($responses as $response) {
            $total_score += 10; // Cada pregunta vale 10 puntos
            $obtained_score += intval($response['score']);
        }
        
        $percentage = $total_questions > 0 ? round(($obtained_score / $total_score) * 100, 2) : 0;
        
        wp_send_json_success(array(
            'responses' => $responses,
            'total_questions' => $total_questions,
            'total_score' => $total_score,
            'obtained_score' => $obtained_score,
            'percentage' => $percentage
        ));
    }
    

    
    /**
     * Guardar evaluación final en base de datos usando datos de sesión
     */
    public function save_final_evaluation() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }

        $materia_id = intval($_POST['materia_id']);
        $estudiante_nombre = sanitize_text_field($_POST['estudiante_nombre']);
        $tema = sanitize_text_field($_POST['tema']);
        $modalidad = sanitize_text_field($_POST['modalidad']);
        $nivel = sanitize_text_field($_POST['nivel']);
        $evaluation_id = isset($_POST['evaluation_id']) ? intval($_POST['evaluation_id']) : null;

        // Buscar evaluación existente
        global $wpdb;
        $table_autoeval = $wpdb->prefix . 'wpsa_autoevaluaciones';

        if ($evaluation_id) {
            // Si se proporciona evaluation_id, usarlo directamente
            $existing_evaluation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_autoeval WHERE id = %d",
                $evaluation_id
            ));

            if (!$existing_evaluation) {
                wp_send_json_error(__('No se encontró la evaluación especificada', 'wp-self-assessment'));
            }

            // Si la evaluación ya está completada, devolver los datos existentes
            if ($existing_evaluation->estado === 'completada') {
                error_log("WPSA: Evaluación ya completada, devolviendo datos existentes");
                wp_send_json_success(array(
                    'evaluation_id' => $existing_evaluation->id,
                    'puntuacion_obtenida' => intval($existing_evaluation->puntuacion_obtenida),
                    'puntuacion_total' => intval($existing_evaluation->puntuacion_total),
                    'porcentaje' => floatval($existing_evaluation->porcentaje),
                    'recomendaciones' => $existing_evaluation->recomendaciones ?: __('No hay recomendaciones disponibles.', 'wp-self-assessment'),
                    'message' => 'Evaluación ya completada anteriormente'
                ));
            }

            // Verificar que esté en progreso
            if ($existing_evaluation->estado !== 'en_progreso') {
                wp_send_json_error(__('La evaluación no está en estado válido para finalizar', 'wp-self-assessment'));
            }
        } else {
            // Fallback: buscar por materia y estudiante (para compatibilidad)
            $existing_evaluation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_autoeval
                 WHERE materia_id = %d
                 AND estudiante_nombre = %s
                 AND estado = 'en_progreso'
                 ORDER BY created_at DESC LIMIT 1",
                $materia_id,
                $estudiante_nombre
            ));

            if (!$existing_evaluation) {
                wp_send_json_error(__('No se encontró una evaluación en progreso para este estudiante', 'wp-self-assessment'));
            }
        }

        // Obtener preguntas desde la base de datos
        $evaluation_id_to_use = $existing_evaluation->id;
        error_log("WPSA: ===== INICIO save_final_evaluation - Finalizando evaluación =====");
        error_log("WPSA: Evaluation ID a finalizar: " . $evaluation_id_to_use);
        
        $preguntas_db = $this->database->get_preguntas_autoevaluacion($evaluation_id_to_use);
        error_log("WPSA: Preguntas encontradas en BD para evaluación ID {$evaluation_id_to_use}: " . count($preguntas_db));
        error_log("WPSA: Detalles de preguntas: " . print_r($preguntas_db, true));

        if (empty($preguntas_db)) {
            error_log("WPSA: WARNING - No hay preguntas guardadas para evaluación ID {$evaluation_id_to_use}. Estudiante: {$estudiante_nombre}, Materia ID: {$materia_id}");
            // Handle empty questions gracefully: 0% score
            $total_score = 0;
            $obtained_score = 0;
            $percentage = 0.00;
            $question_data = array();
            $recomendaciones = __('No se registraron respuestas completas para esta evaluación. Revisa que hayas respondido todas las preguntas antes de finalizar.', 'wp-self-assessment');
            
            // Update evaluation to completed with 0
            global $wpdb;
            $table_autoeval = $wpdb->prefix . 'wpsa_autoevaluaciones';
            $wpdb->update(
                $table_autoeval,
                array(
                    'puntuacion_total' => $total_score,
                    'puntuacion_obtenida' => $obtained_score,
                    'porcentaje' => $percentage,
                    'recomendaciones' => $recomendaciones,
                    'estado' => 'completada',
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $evaluation_id_to_use),
                array('%d', '%d', '%f', '%s', '%s', '%s'),
                array('%d')
            );
            
            error_log("WPSA: Evaluación actualizada con 0% por falta de preguntas");
            
            $response_data = array(
                'evaluation_id' => $evaluation_id_to_use,
                'puntuacion_obtenida' => $obtained_score,
                'puntuacion_total' => $total_score,
                'porcentaje' => $percentage,
                'recomendaciones' => $recomendaciones,
                'message' => 'Evaluación completada sin respuestas registradas'
            );
            wp_send_json_success($response_data);
        }

        // Preparar datos de preguntas desde la base de datos
        $question_data = array();
        $total_score = 0;
        $obtained_score = 0;

        foreach ($preguntas_db as $pregunta) {
            $question_data[] = array(
                'question' => $pregunta->pregunta,
                'answer' => $pregunta->respuesta_estudiante,
                'correct_answer' => $pregunta->respuesta_correcta,
                'score' => $pregunta->puntuacion,
                'maxScore' => 10,
                'feedback' => $pregunta->feedback
            );

            $total_score += 10; // Cada pregunta vale 10 puntos
            $obtained_score += intval($pregunta->puntuacion);
        }

        $percentage = $total_score > 0 ? round(($obtained_score / $total_score) * 100, 2) : 0;

        error_log("WPSA: Cálculo desde BD - Total: $total_score, Obtenido: $obtained_score, Porcentaje: $percentage");

        // Verificar si ya existen recomendaciones para esta evaluación
        $existing_evaluation_full = $this->database->get_autoevaluacion($existing_evaluation->id);
        $recomendaciones = '';

        if (!empty($existing_evaluation_full->recomendaciones)) {
            // Usar recomendaciones existentes
            $recomendaciones = $existing_evaluation_full->recomendaciones;
            error_log("WPSA: Usando recomendaciones existentes para evaluación ID: " . $existing_evaluation->id);
        } else {
            // Generar recomendaciones solo si no existen
            $autoevaluacion_data = array(
                'materia' => $this->database->get_materia($materia_id)->nombre,
                'puntuacion_obtenida' => $obtained_score,
                'puntuacion_total' => $total_score,
                'porcentaje' => $percentage,
                'modalidad' => $modalidad,
                'nivel' => $nivel,
                'preguntas_respuestas' => $question_data
            );

            $recomendaciones = $this->gemini_api->generate_recommendations($autoevaluacion_data);
            error_log("WPSA: Generando nuevas recomendaciones para evaluación ID: " . $existing_evaluation->id);
        }

        // Preparar datos para guardar en wpsa_autoevaluaciones
        $evaluation_data = array(
            'materia_id' => $materia_id,
            'estudiante_nombre' => $estudiante_nombre,
            'tema' => $tema,
            'modalidad' => $modalidad,
            'preguntas_respuestas' => wp_json_encode($question_data), // Usar datos calculados desde BD
            'puntuacion_total' => $total_score,
            'puntuacion_obtenida' => $obtained_score,
            'porcentaje' => $percentage,
            'recomendaciones' => $recomendaciones ?: __('No hay recomendaciones disponibles.', 'wp-self-assessment'),
            'estado' => 'completada',
            'completed_at' => current_time('mysql')
        );

        // Actualizar evaluación existente
        $update_data = array(
            'puntuacion_total' => $total_score,
            'puntuacion_obtenida' => $obtained_score,
            'porcentaje' => $percentage,
            'recomendaciones' => $recomendaciones ?: __('No hay recomendaciones disponibles.', 'wp-self-assessment'),
            'estado' => 'completada',
            'completed_at' => current_time('mysql')
        );

        error_log("WPSA: Actualizando evaluación con datos: " . print_r($update_data, true));
        
        $result = $wpdb->update(
            $table_autoeval,
            $update_data,
            array('id' => $existing_evaluation->id),
            array('%d', '%d', '%f', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            error_log("WPSA: ERROR - Failed to update evaluation. Last error: " . $wpdb->last_error);
            error_log("WPSA: Last query: " . $wpdb->last_query);
            throw new Exception(__('Error al actualizar la evaluación', 'wp-self-assessment'));
        }

        $evaluation_id = $existing_evaluation->id;
        error_log("WPSA: Evaluación actualizada exitosamente - ID: $evaluation_id, Puntaje: {$obtained_score}/{$total_score} ({$percentage}%)");
        error_log("WPSA: Rows affected: " . $result);

        if ($evaluation_id) {
            // Las preguntas ya están guardadas en la base de datos desde el proceso de evaluación
            // No necesitamos guardarlas nuevamente aquí para evitar duplicados
            error_log("WPSA: Evaluación completada exitosamente - ID: $evaluation_id");

            // Limpiar sesión si existe
            if (session_status() == PHP_SESSION_ACTIVE && isset($_SESSION['wpsa_evaluation_responses'])) {
                unset($_SESSION['wpsa_evaluation_responses']);
            }

            $response_data = array(
                'evaluation_id' => $evaluation_id,
                'puntuacion_obtenida' => $obtained_score,
                'puntuacion_total' => $total_score,
                'porcentaje' => floatval($percentage),
                'recomendaciones' => $recomendaciones ?: __('No hay recomendaciones disponibles.', 'wp-self-assessment'),
                'message' => 'Evaluación completada correctamente'
            );
            
            error_log("WPSA: ===== ÉXITO save_final_evaluation =====");
            error_log("WPSA: Enviando respuesta: " . print_r($response_data, true));
            
            wp_send_json_success($response_data);
        } else {
            error_log("WPSA: ERROR - evaluation_id is null or false");
            wp_send_json_error(__('Error al guardar la evaluación en la base de datos', 'wp-self-assessment'));
        }
    }

    /**
     * Obtener detalles de una autoevaluación
     */
    public function get_evaluation_details() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'wp-self-assessment'));
        }

        // Verificar nonce (puede venir por GET o POST)
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_GET['nonce']) ? $_GET['nonce'] : '');
        if (!wp_verify_nonce($nonce, 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }

        $evaluation_id = isset($_POST['evaluation_id']) ? $_POST['evaluation_id'] : (isset($_GET['evaluation_id']) ? $_GET['evaluation_id'] : 0);

        // Special case: get latest completed evaluation
        if ($evaluation_id === 'latest_completed') {
            $materia_id = intval($_POST['materia_id'] ?? 0);
            $estudiante_nombre = sanitize_text_field($_POST['estudiante_nombre'] ?? '');

            if (!$materia_id || empty($estudiante_nombre)) {
                wp_send_json_error(__('Datos insuficientes para buscar evaluación existente', 'wp-self-assessment'));
            }

            global $wpdb;
            $table = $wpdb->prefix . 'wpsa_autoevaluaciones';

            $evaluation = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table
                 WHERE materia_id = %d
                 AND estudiante_nombre = %s
                 AND estado = 'completada'
                 ORDER BY completed_at DESC LIMIT 1",
                $materia_id,
                $estudiante_nombre
            ));

            if (!$evaluation) {
                wp_send_json_error(__('No se encontró evaluación completada para este estudiante', 'wp-self-assessment'));
            }
        } else {
            $evaluation_id = intval($evaluation_id);

            if (!$evaluation_id) {
                wp_send_json_error(__('ID de evaluación no válido', 'wp-self-assessment'));
            }

            $evaluation = $this->database->get_autoevaluacion($evaluation_id);

            if (!$evaluation) {
                wp_send_json_error(__('Evaluación no encontrada', 'wp-self-assessment'));
            }
        }
        
        // Obtener preguntas individuales desde la tabla separada
        $preguntas_db = $this->database->get_preguntas_autoevaluacion($evaluation_id);

        // Normalizar preguntas a formato array para consistencia
        $preguntas_respuestas = array();
        if (!empty($preguntas_db)) {
            foreach ($preguntas_db as $pregunta) {
                $preguntas_respuestas[] = array(
                    'pregunta' => $pregunta->pregunta,
                    'respuesta_estudiante' => $pregunta->respuesta_estudiante,
                    'respuesta_correcta' => $pregunta->respuesta_correcta,
                    'puntuacion' => $pregunta->puntuacion,
                    'feedback' => $pregunta->feedback,
                    'created_at' => $pregunta->created_at
                );
            }
        } else {
            // Si no hay preguntas en la tabla separada, intentar decodificar desde JSON (compatibilidad)
            $preguntas_json = json_decode($evaluation->preguntas_respuestas, true);
            if (is_array($preguntas_json)) {
                $preguntas_respuestas = $preguntas_json;
            } else {
                $preguntas_respuestas = array();
            }
        }

        // Log para debugging
        error_log("WPSA: Preguntas obtenidas para evaluación $evaluation_id: " . count($preguntas_respuestas));

        // Preparar datos para la respuesta
        $details = array(
            'id' => $evaluation->id,
            'estudiante_nombre' => $evaluation->estudiante_nombre ?: __('Anónimo', 'wp-self-assessment'),
            'materia_nombre' => $evaluation->materia_nombre,
            'grado' => $evaluation->grado,
            'tema' => $evaluation->tema ?: __('General', 'wp-self-assessment'),
            'modalidad' => $evaluation->modalidad,
            'puntuacion_obtenida' => $evaluation->puntuacion_obtenida,
            'puntuacion_total' => $evaluation->puntuacion_total,
            'porcentaje' => $evaluation->porcentaje,
            'recomendaciones' => $evaluation->recomendaciones,
            'estado' => $evaluation->estado,
            'created_at' => $evaluation->created_at,
            'completed_at' => $evaluation->completed_at,
            'preguntas_respuestas' => $preguntas_respuestas
        );
        
        // Si se solicita formato HTML para iframe
        if (isset($_GET['format']) && $_GET['format'] === 'html') {
            $this->render_evaluation_details_html($details);
            exit;
        }
        
        wp_send_json_success($details);
    }
    
    /**
     * Renderizar detalles de evaluación en formato HTML para iframe
     */
    private function render_evaluation_details_html($details) {
        $modalidades = array(
            'preguntas_simples' => __('Preguntas Simples', 'wp-self-assessment'),
            'ejercicios' => __('Ejercicios Prácticos', 'wp-self-assessment'),
            'codigo' => __('Análisis de Situaciones, Problemas o Código', 'wp-self-assessment')
        );
        
        $estados = array(
            'completada' => __('Completada', 'wp-self-assessment'),
            'en_progreso' => __('En Progreso', 'wp-self-assessment'),
            'abandonada' => __('Abandonada', 'wp-self-assessment')
        );
        
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php _e('Detalles de Autoevaluación', 'wp-self-assessment'); ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: #f8f9fa;
                    color: #333;
                }
                .container {
                    max-width: 1000px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .header {
                    background: #0073aa;
                    color: white;
                    padding: 20px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .content {
                    padding: 30px;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                .info-card {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 6px;
                    border-left: 4px solid #0073aa;
                }
                .info-card h3 {
                    margin: 0 0 15px 0;
                    color: #0073aa;
                    font-size: 16px;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    padding: 5px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                .info-row:last-child {
                    border-bottom: none;
                }
                .info-label {
                    font-weight: 600;
                    color: #666;
                }
                .info-value {
                    color: #333;
                }
                .score-highlight {
                    background: #e3f2fd;
                    padding: 15px;
                    border-radius: 6px;
                    text-align: center;
                    margin: 20px 0;
                }
                .score-highlight .score {
                    font-size: 32px;
                    font-weight: bold;
                    color: #0073aa;
                }
                .score-highlight .percentage {
                    font-size: 18px;
                    color: #666;
                }
                .recommendations {
                    background: #fff3cd;
                    padding: 20px;
                    border-radius: 6px;
                    border-left: 4px solid #ffc107;
                    margin: 20px 0;
                }
                .recommendations h3 {
                    margin: 0 0 15px 0;
                    color: #856404;
                }
                .questions-section {
                    margin-top: 30px;
                }
                .question-item {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 6px;
                    margin-bottom: 15px;
                    border-left: 4px solid #28a745;
                }
                .question-item h4 {
                    margin: 0 0 10px 0;
                    color: #28a745;
                }
                .question-text {
                    margin-bottom: 15px;
                    font-style: italic;
                    color: #666;
                }
                .answer-text {
                    background: white;
                    padding: 15px;
                    border-radius: 4px;
                    border: 1px solid #e9ecef;
                    margin-bottom: 10px;
                }
                .score-badge {
                    display: inline-block;
                    background: #0073aa;
                    color: white;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: bold;
                }
                .feedback {
                    background: #e3f2fd;
                    padding: 10px;
                    border-radius: 4px;
                    font-size: 14px;
                    color: #1976d2;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Detalles de Autoevaluación', 'wp-self-assessment'); ?></h1>
                </div>
                
                <div class="content">
                    <!-- Información general -->
                    <div class="info-grid">
                        <div class="info-card">
                            <h3><?php _e('Información del Estudiante', 'wp-self-assessment'); ?></h3>
                            <div class="info-row">
                                <span class="info-label"><?php _e('Nombre:', 'wp-self-assessment'); ?></span>
                                <span class="info-value"><?php echo esc_html($details['estudiante_nombre']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e('Materia:', 'wp-self-assessment'); ?></span>
                                <span class="info-value"><?php echo esc_html($details['materia_nombre']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e('Grado:', 'wp-self-assessment'); ?></span>
                                <span class="info-value"><?php echo esc_html($details['grado']); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <h3><?php _e('Detalles de la Evaluación', 'wp-self-assessment'); ?></h3>
                            <div class="info-row">
                                <span class="info-label"><?php _e('Tema:', 'wp-self-assessment'); ?></span>
                                <span class="info-value"><?php echo esc_html($details['tema']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e('Modalidad:', 'wp-self-assessment'); ?></span>
                                <span class="info-value"><?php echo esc_html($modalidades[$details['modalidad']] ?? $details['modalidad']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e('Estado:', 'wp-self-assessment'); ?></span>
                                <span class="info-value"><?php echo esc_html($estados[$details['estado']] ?? $details['estado']); ?></span>
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <h3><?php _e('Fechas', 'wp-self-assessment'); ?></h3>
                            <div class="info-row">
                                <span class="info-label"><?php _e('Creada:', 'wp-self-assessment'); ?></span>
                                <span class="info-value"><?php echo esc_html(date('d/m/Y H:i', strtotime($details['created_at']))); ?></span>
                            </div>
                            <?php if ($details['completed_at']): ?>
                            <div class="info-row">
                                <span class="info-label"><?php _e('Completada:', 'wp-self-assessment'); ?></span>
                                <span class="info-value"><?php echo esc_html(date('d/m/Y H:i', strtotime($details['completed_at']))); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Puntuación destacada -->
                    <div class="score-highlight">
                        <div class="score"><?php echo esc_html($details['puntuacion_obtenida']); ?> / <?php echo esc_html($details['puntuacion_total']); ?></div>
                        <div class="percentage"><?php echo esc_html($details['porcentaje']); ?>%</div>
                    </div>
                    
                    <!-- Recomendaciones -->
                    <?php if (!empty($details['recomendaciones'])): ?>
                    <div class="recommendations">
                        <h3><?php _e('Recomendaciones', 'wp-self-assessment'); ?></h3>
                        <div><?php echo wp_kses_post($details['recomendaciones']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Preguntas y respuestas -->
                    <?php if (!empty($details['preguntas_respuestas'])): ?>
                    <div class="questions-section">
                        <h3><?php _e('Preguntas y Respuestas', 'wp-self-assessment'); ?> (<?php echo count($details['preguntas_respuestas']); ?> preguntas)</h3>
                        <?php foreach ($details['preguntas_respuestas'] as $index => $qa): ?>
                        <div class="question-item">
                            <h4><?php _e('Pregunta', 'wp-self-assessment'); ?> <?php echo $index + 1; ?></h4>

                            <!-- Mostrar pregunta desde la base de datos -->
                            <?php if (isset($qa['pregunta']) && !empty($qa['pregunta'])): ?>
                            <div class="question-text"><?php echo wp_kses_post($qa['pregunta']); ?></div>
                            <?php elseif (isset($qa['question']) && !empty($qa['question'])): ?>
                            <div class="question-text"><?php echo wp_kses_post($qa['question']); ?></div>
                            <?php else: ?>
                            <div class="question-text"><em><?php _e('Pregunta no disponible', 'wp-self-assessment'); ?></em></div>
                            <?php endif; ?>

                            <!-- Mostrar respuesta del estudiante -->
                            <?php if (isset($qa['respuesta_estudiante']) && !empty($qa['respuesta_estudiante'])): ?>
                            <div class="answer-text">
                                <strong><?php _e('Respuesta del estudiante:', 'wp-self-assessment'); ?></strong><br>
                                <?php echo wp_kses_post(nl2br($qa['respuesta_estudiante'])); ?>
                            </div>
                            <?php elseif (isset($qa['answer']) && !empty($qa['answer'])): ?>
                            <div class="answer-text">
                                <strong><?php _e('Respuesta del estudiante:', 'wp-self-assessment'); ?></strong><br>
                                <?php echo wp_kses_post(nl2br($qa['answer'])); ?>
                            </div>
                            <?php else: ?>
                            <div class="answer-text">
                                <strong><?php _e('Respuesta del estudiante:', 'wp-self-assessment'); ?></strong><br>
                                <em><?php _e('Sin respuesta', 'wp-self-assessment'); ?></em>
                            </div>
                            <?php endif; ?>

                            <!-- Mostrar puntuación -->
                            <?php
                            $puntuacion = 0;
                            if (isset($qa['puntuacion'])) {
                                $puntuacion = intval($qa['puntuacion']);
                            } elseif (isset($qa['score'])) {
                                $puntuacion = intval($qa['score']);
                            }
                            ?>
                            <div class="score-badge"><?php echo esc_html($puntuacion); ?>/10</div>

                            <!-- Mostrar feedback -->
                            <?php if (isset($qa['feedback']) && !empty($qa['feedback'])): ?>
                            <div class="feedback">
                                <strong><?php _e('Feedback:', 'wp-self-assessment'); ?></strong><br>
                                <?php echo wp_kses_post($qa['feedback']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="questions-section">
                        <h3><?php _e('Preguntas y Respuestas', 'wp-self-assessment'); ?></h3>
                        <p><em><?php _e('No hay preguntas disponibles para esta evaluación.', 'wp-self-assessment'); ?></em></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * Eliminar una autoevaluación
     */
    public function delete_evaluation() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'wp-self-assessment'));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        $evaluation_id = intval($_POST['evaluation_id']);
        
        if (!$evaluation_id) {
            wp_send_json_error(__('ID de evaluación no válido', 'wp-self-assessment'));
        }
        
        // Verificar que la evaluación existe
        $evaluation = $this->database->get_autoevaluacion($evaluation_id);
        
        if (!$evaluation) {
            wp_send_json_error(__('Evaluación no encontrada', 'wp-self-assessment'));
        }
        
        // Eliminar la evaluación de la base de datos
        $result = $this->database->delete_autoevaluacion($evaluation_id);
        
        if ($result) {
            // Log de la eliminación para auditoría
            error_log("WPSA: Autoevaluación eliminada - ID: {$evaluation_id}, Estudiante: {$evaluation->estudiante_nombre}, Materia: {$evaluation->materia_nombre}");
            
            wp_send_json_success(array(
                'message' => __('Autoevaluación eliminada correctamente', 'wp-self-assessment'),
                'evaluation_id' => $evaluation_id
            ));
        } else {
            wp_send_json_error(__('Error al eliminar la autoevaluación', 'wp-self-assessment'));
        }
    }
    
    /**
     * Exportar datos a CSV
     */
    public function export_csv() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción', 'wp-self-assessment'));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_GET['nonce'], 'wpsa_export_nonce')) {
            wp_die(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        $filters = array();
        
        if (isset($_GET['materia_id']) && !empty($_GET['materia_id'])) {
            $filters['materia_id'] = intval($_GET['materia_id']);
        }
        
        if (isset($_GET['fecha_desde']) && !empty($_GET['fecha_desde'])) {
            $filters['fecha_desde'] = sanitize_text_field($_GET['fecha_desde']);
        }
        
        if (isset($_GET['fecha_hasta']) && !empty($_GET['fecha_hasta'])) {
            $filters['fecha_hasta'] = sanitize_text_field($_GET['fecha_hasta']);
        }
        
        $autoevaluaciones = $this->database->get_autoevaluaciones($filters);
        
        // Configurar headers para descarga
        $filename = 'autoevaluaciones_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Crear archivo CSV
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, array(
            'ID',
            'Estudiante',
            'Materia',
            'Grado',
            'Tema',
            'Modalidad',
            'Puntuación Obtenida',
            'Puntuación Total',
            'Porcentaje',
            'Estado',
            'Fecha Creación',
            'Fecha Completada'
        ));
        
        // Datos
        foreach ($autoevaluaciones as $autoeval) {
            fputcsv($output, array(
                $autoeval->id,
                $autoeval->estudiante_nombre ?: 'Anónimo',
                $autoeval->materia_nombre,
                $autoeval->grado,
                $autoeval->tema ?: 'General',
                $autoeval->modalidad,
                $autoeval->puntuacion_obtenida,
                $autoeval->puntuacion_total,
                $autoeval->porcentaje,
                $autoeval->estado,
                $autoeval->created_at,
                $autoeval->completed_at
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Exportar datos a Excel
     */
    public function export_excel() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción', 'wp-self-assessment'));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_GET['nonce'], 'wpsa_export_nonce')) {
            wp_die(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        $filters = array();
        
        if (isset($_GET['materia_id']) && !empty($_GET['materia_id'])) {
            $filters['materia_id'] = intval($_GET['materia_id']);
        }
        
        if (isset($_GET['fecha_desde']) && !empty($_GET['fecha_desde'])) {
            $filters['fecha_desde'] = sanitize_text_field($_GET['fecha_desde']);
        }
        
        if (isset($_GET['fecha_hasta']) && !empty($_GET['fecha_hasta'])) {
            $filters['fecha_hasta'] = sanitize_text_field($_GET['fecha_hasta']);
        }
        
        $autoevaluaciones = $this->database->get_autoevaluaciones($filters);
        
        // Crear archivo Excel simple (CSV con extensión .xls)
        $filename = 'autoevaluaciones_' . date('Y-m-d_H-i-s') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Crear archivo
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, array(
            'ID',
            'Estudiante',
            'Materia',
            'Grado',
            'Tema',
            'Modalidad',
            'Puntuación Obtenida',
            'Puntuación Total',
            'Porcentaje',
            'Estado',
            'Fecha Creación',
            'Fecha Completada'
        ), "\t");
        
        // Datos
        foreach ($autoevaluaciones as $autoeval) {
            fputcsv($output, array(
                $autoeval->id,
                $autoeval->estudiante_nombre ?: 'Anónimo',
                $autoeval->materia_nombre,
                $autoeval->grado,
                $autoeval->tema ?: 'General',
                $autoeval->modalidad,
                $autoeval->puntuacion_obtenida,
                $autoeval->puntuacion_total,
                $autoeval->porcentaje,
                $autoeval->estado,
                $autoeval->created_at,
                $autoeval->completed_at
            ), "\t");
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Exportar evaluación individual a PDF
     */
    public function export_pdf() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos para realizar esta acción', 'wp-self-assessment'));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_GET['nonce'], 'wpsa_export_nonce')) {
            wp_die(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        $evaluation_id = intval($_GET['evaluation_id']);
        
        if (!$evaluation_id) {
            wp_die(__('ID de evaluación no válido', 'wp-self-assessment'));
        }
        
        $evaluation = $this->database->get_autoevaluacion($evaluation_id);
        
        if (!$evaluation) {
            wp_die(__('Evaluación no encontrada', 'wp-self-assessment'));
        }
        
        // Decodificar preguntas y respuestas
        $preguntas_respuestas = json_decode($evaluation->preguntas_respuestas, true);
        
        // Generar PDF simple usando HTML
        $filename = 'autoevaluacion_' . $evaluation_id . '_' . date('Y-m-d_H-i-s') . '.html';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Generar HTML para impresión
        $this->generate_pdf_html($evaluation, $preguntas_respuestas);
        exit;
    }
    
    /**
     * Generar HTML para PDF
     */
    private function generate_pdf_html($evaluation, $preguntas_respuestas) {
        $modalidades = array(
            'preguntas_simples' => __('Preguntas', 'wp-self-assessment'),
            'ejercicios' => __('Ejercicios', 'wp-self-assessment'),
            'codigo' => __('Código', 'wp-self-assessment')
        );
        
        $estados = array(
            'en_progreso' => __('En Progreso', 'wp-self-assessment'),
            'completada' => __('Completada', 'wp-self-assessment'),
            'cancelada' => __('Cancelada', 'wp-self-assessment')
        );
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php _e('Reporte de Autoevaluación', 'wp-self-assessment'); ?> #<?php echo $evaluation->id; ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px; }
                .header h1 { color: #0073aa; margin: 0; }
                .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .info-table th, .info-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .info-table th { background-color: #f5f5f5; font-weight: bold; }
                .questions-section { margin-top: 20px; }
                .question { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
                .question h4 { margin-top: 0; color: #0073aa; }
                .answer { background-color: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa; }
                .score { font-weight: bold; color: #0073aa; }
                .recommendations { background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin-top: 20px; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php _e('Reporte de Autoevaluación', 'wp-self-assessment'); ?></h1>
                <p><?php _e('Generado el', 'wp-self-assessment'); ?>: <?php echo date_i18n('d/m/Y H:i:s'); ?></p>
            </div>
            
            <table class="info-table">
                <tr>
                    <th><?php _e('ID de Evaluación', 'wp-self-assessment'); ?></th>
                    <td><?php echo esc_html($evaluation->id); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Estudiante', 'wp-self-assessment'); ?></th>
                    <td><?php echo esc_html($evaluation->estudiante_nombre ?: __('Anónimo', 'wp-self-assessment')); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Materia', 'wp-self-assessment'); ?></th>
                    <td><?php echo esc_html($evaluation->materia_nombre); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Grado', 'wp-self-assessment'); ?></th>
                    <td><?php echo esc_html($evaluation->grado); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Tema', 'wp-self-assessment'); ?></th>
                    <td><?php echo esc_html($evaluation->tema ?: __('General', 'wp-self-assessment')); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Modalidad', 'wp-self-assessment'); ?></th>
                    <td><?php echo esc_html($modalidades[$evaluation->modalidad] ?? $evaluation->modalidad); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Puntuación', 'wp-self-assessment'); ?></th>
                    <td class="score"><?php echo esc_html($evaluation->puntuacion_obtenida . '/' . $evaluation->puntuacion_total . ' (' . $evaluation->porcentaje . '%)'); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Estado', 'wp-self-assessment'); ?></th>
                    <td><?php echo esc_html($estados[$evaluation->estado] ?? $evaluation->estado); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Fecha de Creación', 'wp-self-assessment'); ?></th>
                    <td><?php echo esc_html(date_i18n('d/m/Y H:i:s', strtotime($evaluation->created_at))); ?></td>
                </tr>
                <?php if ($evaluation->completed_at): ?>
                <tr>
                    <th><?php _e('Fecha de Finalización', 'wp-self-assessment'); ?></th>
                    <td><?php echo esc_html(date_i18n('d/m/Y H:i:s', strtotime($evaluation->completed_at))); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            
            <?php if (!empty($preguntas_respuestas)): ?>
            <div class="questions-section">
                <h2><?php _e('Preguntas y Respuestas', 'wp-self-assessment'); ?></h2>
                
                <?php foreach ($preguntas_respuestas as $index => $pregunta): ?>
                <div class="question">
                    <h4><?php _e('Pregunta', 'wp-self-assessment'); ?> <?php echo $index + 1; ?></h4>
                    <p><?php echo nl2br(esc_html($pregunta['pregunta'])); ?></p>
                    
                    <div class="answer">
                        <strong><?php _e('Respuesta del Estudiante', 'wp-self-assessment'); ?>:</strong><br>
                        <?php echo nl2br(esc_html($pregunta['respuesta_estudiante'] ?: __('Sin respuesta', 'wp-self-assessment'))); ?>
                    </div>
                    
                    <?php if (!empty($pregunta['respuesta_correcta'])): ?>
                    <div class="answer">
                        <strong><?php _e('Respuesta Correcta', 'wp-self-assessment'); ?>:</strong><br>
                        <?php echo nl2br(esc_html($pregunta['respuesta_correcta'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <p><strong><?php _e('Puntuación', 'wp-self-assessment'); ?>:</strong> 
                       <span class="score"><?php echo esc_html($pregunta['puntuacion_obtenida'] . '/' . $pregunta['puntuacion']); ?></span>
                    </p>
                    
                    <?php if (!empty($pregunta['feedback'])): ?>
                    <div class="answer">
                        <strong><?php _e('Comentarios', 'wp-self-assessment'); ?>:</strong><br>
                        <?php echo nl2br(esc_html($pregunta['feedback'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($evaluation->recomendaciones)): ?>
            <div class="recommendations">
                <h3><?php _e('Recomendaciones', 'wp-self-assessment'); ?></h3>
                <p><?php echo nl2br(esc_html($evaluation->recomendaciones)); ?></p>
            </div>
            <?php endif; ?>
            
            <script>
                // Auto-imprimir al cargar
                window.onload = function() {
                    window.print();
                };
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Eliminar múltiples evaluaciones
     */
    public function delete_multiple_evaluations() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'wp-self-assessment'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        $evaluation_ids = $_POST['evaluation_ids'];
        
        if (!is_array($evaluation_ids) || empty($evaluation_ids)) {
            wp_send_json_error(__('No se seleccionaron evaluaciones para eliminar', 'wp-self-assessment'));
        }
        
        $deleted_count = 0;
        $errors = array();
        
        foreach ($evaluation_ids as $evaluation_id) {
            $evaluation_id = intval($evaluation_id);
            
            if (!$evaluation_id) {
                continue;
            }
            
            // Verificar que la evaluación existe
            $evaluation = $this->database->get_autoevaluacion($evaluation_id);
            if (!$evaluation) {
                $errors[] = sprintf(__('Evaluación ID %d no encontrada', 'wp-self-assessment'), $evaluation_id);
                continue;
            }
            
            // Eliminar la evaluación
            $result = $this->database->delete_autoevaluacion($evaluation_id);
            if ($result) {
                $deleted_count++;
                error_log("WPSA: Autoevaluación eliminada - ID: {$evaluation_id}, Estudiante: {$evaluation->estudiante_nombre}, Materia: {$evaluation->materia_nombre}");
            } else {
                $errors[] = sprintf(__('Error al eliminar evaluación ID %d', 'wp-self-assessment'), $evaluation_id);
            }
        }
        
        if ($deleted_count > 0) {
            $message = sprintf(
                _n(
                    'Se eliminó %d autoevaluación correctamente',
                    'Se eliminaron %d autoevaluaciones correctamente',
                    $deleted_count,
                    'wp-self-assessment'
                ),
                $deleted_count
            );
            
            if (!empty($errors)) {
                $message .= ' ' . sprintf(
                    _n(
                        '(%d error)',
                        '(%d errores)',
                        count($errors),
                        'wp-self-assessment'
                    ),
                    count($errors)
                );
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'deleted_count' => $deleted_count,
                'errors' => $errors
            ));
        } else {
            wp_send_json_error(__('No se pudo eliminar ninguna evaluación', 'wp-self-assessment'));
        }
    }
    
    /**
     * Generar pregunta dinámica usando IA
     */
    public function generate_dynamic_question() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }

        $materia_id = intval($_POST['materia_id']);
        $materia_nombre = sanitize_text_field($_POST['materia_nombre']);
        $tema = sanitize_text_field($_POST['tema']);
        $modalidad = sanitize_text_field($_POST['modalidad']);
        $nivel = sanitize_text_field($_POST['nivel']);
        $numero_pregunta = intval($_POST['numero_pregunta']);
        $previous_questions = isset($_POST['previous_questions']) ? json_decode(stripslashes($_POST['previous_questions']), true) : array();

        // Obtener datos de la materia
        $materia = $this->database->get_materia($materia_id);
        if (!$materia) {
            wp_send_json_error(__('Materia no encontrada', 'wp-self-assessment'));
        }

        // Debug: mostrar datos de la materia obtenida de la BD
        error_log('🔍 WPSA Debug - Datos de materia desde BD: ' . print_r($materia, true));

        // Obtener temario (prioridad: temario_analizado > temario > descripción)
        $temario = '';
        if (!empty($materia->temario_analizado)) {
            $temario = $materia->temario_analizado;
            error_log('🔍 WPSA Debug - Usando temario_analizado, longitud: ' . strlen($temario));
        } elseif (!empty($materia->temario)) {
            $temario = $materia->temario;
            error_log('🔍 WPSA Debug - Usando temario, longitud: ' . strlen($temario));
        } elseif (!empty($materia->descripcion)) {
            $temario = $materia->descripcion;
            error_log('🔍 WPSA Debug - Usando descripcion, longitud: ' . strlen($temario));
        } else {
            error_log('🔍 WPSA Debug - No se encontró temario, temario_analizado ni descripcion en la materia');
        }

        error_log('🔍 WPSA Debug - Temario final a enviar a IA, longitud: ' . strlen($temario));

        // Generar pregunta usando la API de Gemini (pasar materia_id para consulta directa)
        error_log('🔍 WPSA Debug - Llamando a generate_question con parámetros:');
        error_log('Materia: ' . $materia_nombre);
        error_log('Tema: ' . $tema);
        error_log('Modalidad: ' . $modalidad);
        error_log('Grado: ' . $materia->grado);
        error_log('Nivel: ' . $nivel);
        error_log('Número: ' . $numero_pregunta);
        error_log('Temario length: ' . strlen($temario));
        error_log('Materia ID: ' . $materia_id);
        error_log('Preguntas anteriores: ' . count($previous_questions));

        $pregunta = $this->gemini_api->generate_question(
            $materia_nombre,
            $tema,
            $modalidad,
            $materia->grado,
            $nivel,
            $numero_pregunta,
            $temario,
            $materia_id,
            $previous_questions
        );

        error_log('🔍 WPSA Debug - Resultado de generate_question: ' . print_r($pregunta, true));

        if ($pregunta) {
            // Agregar datos de debug del temario a la respuesta
            $pregunta['temario_debug'] = array(
                'temario_length' => strlen($temario),
                'temario_preview' => substr($temario, 0, 200) . (strlen($temario) > 200 ? '...' : ''),
                'materia_id' => $materia_id,
                'materia_nombre' => $materia_nombre
            );
            error_log('✅ WPSA Debug - Pregunta generada exitosamente, enviando respuesta');
            wp_send_json_success($pregunta);
        } else {
            error_log('❌ WPSA Debug - Error: generate_question devolvió false');
            wp_send_json_error(__('Error generando pregunta con IA', 'wp-self-assessment'));
        }
    }
    
    /**
     * Obtener datos de una materia desde la BD
     */
    public function get_materia_data() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpsa_nonce')) {
            wp_send_json_error(__('Error de seguridad', 'wp-self-assessment'));
        }
        
        $materia_id = intval($_POST['materia_id']);
        
        if (!$materia_id) {
            wp_send_json_error(__('ID de materia no válido', 'wp-self-assessment'));
        }
        
        // Obtener datos de la materia
        $materia = $this->database->get_materia($materia_id);
        
        if (!$materia) {
            wp_send_json_error(__('Materia no encontrada', 'wp-self-assessment'));
        }
        
        // Preparar datos para enviar
        $materia_data = array(
            'id' => $materia->id,
            'nombre' => $materia->nombre,
            'grado' => $materia->grado,
            'descripcion' => $materia->descripcion,
            'temario' => $materia->temario,
            'temario_analizado' => $materia->temario_analizado,
            'created_at' => $materia->created_at,
            'updated_at' => $materia->updated_at
        );
        
        // Log para debugging
        error_log('🔍 WPSA Debug - Datos de materia enviados al frontend: ' . print_r($materia_data, true));
        
        wp_send_json_success($materia_data);
    }
}
