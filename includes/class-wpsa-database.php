<?php
/**
 * Clase para manejo de base de datos del plugin WP Self Assessment
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSA_Database {
    
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
    
    /**
     * Crear tablas de la base de datos
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla de materias
        $table_materias = $wpdb->prefix . 'wpsa_materias';
        $sql_materias = "CREATE TABLE $table_materias (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT 0,
            nombre varchar(255) NOT NULL,
            grado varchar(100) NOT NULL,
            descripcion text,
            temario text,
            programa_pdf varchar(500),
            temario_analizado text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Tabla de autoevaluaciones
        $table_autoevaluaciones = $wpdb->prefix . 'wpsa_autoevaluaciones';
        $sql_autoevaluaciones = "CREATE TABLE $table_autoevaluaciones (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            materia_id mediumint(9) NOT NULL,
            estudiante_nombre varchar(255),
            tema varchar(255),
            modalidad enum('preguntas_simples', 'ejercicios', 'codigo') NOT NULL,
            preguntas_respuestas longtext,
            puntuacion_total int(11) DEFAULT 0,
            puntuacion_obtenida int(11) DEFAULT 0,
            porcentaje decimal(5,2) DEFAULT 0.00,
            recomendaciones text,
            estado enum('en_progreso', 'completada', 'cancelada') DEFAULT 'en_progreso',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            PRIMARY KEY (id),
            KEY materia_id (materia_id),
            KEY estudiante_nombre (estudiante_nombre),
            KEY estado (estado)
        ) $charset_collate;";
        
        // Tabla de preguntas individuales
        $table_preguntas = $wpdb->prefix . 'wpsa_preguntas';
        $sql_preguntas = "CREATE TABLE $table_preguntas (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            autoevaluacion_id mediumint(9) NOT NULL,
            pregunta text NOT NULL,
            respuesta_estudiante text,
            respuesta_correcta text,
            puntuacion int(11) DEFAULT 0,
            feedback text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY autoevaluacion_id (autoevaluacion_id)
        ) $charset_collate;";
        
        // Tabla de configuraciones
        $table_configuraciones = $wpdb->prefix . 'wpsa_configuraciones';
        $sql_configuraciones = "CREATE TABLE $table_configuraciones (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            config_key varchar(100) NOT NULL UNIQUE,
            config_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_materias);
        dbDelta($sql_autoevaluaciones);
        dbDelta($sql_preguntas);
        dbDelta($sql_configuraciones);
    }
    
    /**
     * Obtener todas las materias
     */
    public function get_materias($filters = array()) {
        global $wpdb;

        $table = $wpdb->prefix . 'wpsa_materias';
        $where = array();
        $values = array();

        // Filtrar por usuario si se especifica
        if (!empty($filters['user_id'])) {
            $where[] = "user_id = %d";
            $values[] = intval($filters['user_id']);
        } elseif (!empty($filters['current_user_only']) && $filters['current_user_only']) {
            // Si se solicita solo del usuario actual
            $current_user_id = get_current_user_id();
            if ($current_user_id > 0) {
                $where[] = "user_id = %d";
                $values[] = $current_user_id;
            }
        }

        if (!empty($filters['grado'])) {
            $where[] = "grado = %s";
            $values[] = $filters['grado'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(nombre LIKE %s OR descripcion LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }

        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }

        $sql = "SELECT * FROM $table $where_clause ORDER BY created_at DESC";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql);
    }
    
    /**
     * Obtener una materia por ID
     */
    public function get_materia($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wpsa_materias';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    /**
     * Guardar materia
     */
    public function save_materia($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'wpsa_materias';

        $materia_data = array(
            'user_id' => isset($data['user_id']) ? intval($data['user_id']) : get_current_user_id(),
            'nombre' => sanitize_text_field($data['nombre']),
            'grado' => sanitize_text_field($data['grado']),
            'descripcion' => sanitize_textarea_field($data['descripcion']),
            'temario' => sanitize_textarea_field($data['temario']),
            'programa_pdf' => sanitize_url($data['programa_pdf']),
            'temario_analizado' => sanitize_textarea_field($data['temario_analizado'])
        );

        if (isset($data['id']) && !empty($data['id'])) {
            // Actualizar
            $result = $wpdb->update(
                $table,
                $materia_data,
                array('id' => intval($data['id'])),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            return $data['id'];
        } else {
            // Insertar
            $result = $wpdb->insert($table, $materia_data);
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Eliminar materia
     */
    public function delete_materia($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wpsa_materias';
        return $wpdb->delete($table, array('id' => intval($id)), array('%d'));
    }
    
    /**
     * Obtener autoevaluaciones con filtros
     */
    public function get_autoevaluaciones($filters = array()) {
        global $wpdb;
        
        $table_autoeval = $wpdb->prefix . 'wpsa_autoevaluaciones';
        $table_materias = $wpdb->prefix . 'wpsa_materias';
        
        $where = array();
        $values = array();
        
        if (!empty($filters['materia_id'])) {
            $where[] = "a.materia_id = %d";
            $values[] = intval($filters['materia_id']);
        }
        
        if (!empty($filters['grado'])) {
            $where[] = "m.grado = %s";
            $values[] = $filters['grado'];
        }
        
        if (!empty($filters['estudiante'])) {
            $where[] = "a.estudiante_nombre LIKE %s";
            $values[] = '%' . $wpdb->esc_like($filters['estudiante']) . '%';
        }
        
        if (!empty($filters['estado'])) {
            $where[] = "a.estado = %s";
            $values[] = $filters['estado'];
        }
        
        if (!empty($filters['fecha_desde'])) {
            $where[] = "DATE(a.created_at) >= %s";
            $values[] = $filters['fecha_desde'];
        }
        
        if (!empty($filters['fecha_hasta'])) {
            $where[] = "DATE(a.created_at) <= %s";
            $values[] = $filters['fecha_hasta'];
        }
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }
        
        $sql = "SELECT a.*, m.nombre as materia_nombre, m.grado 
                FROM $table_autoeval a 
                LEFT JOIN $table_materias m ON a.materia_id = m.id 
                $where_clause 
                ORDER BY a.created_at DESC";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Obtener una autoevaluación por ID
     */
    public function get_autoevaluacion($id) {
        global $wpdb;
        
        $table_autoeval = $wpdb->prefix . 'wpsa_autoevaluaciones';
        $table_materias = $wpdb->prefix . 'wpsa_materias';
        $table_preguntas = $wpdb->prefix . 'wpsa_preguntas';
        
        // Obtener datos básicos de la autoevaluación
        $evaluation = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, m.nombre as materia_nombre, m.grado
            FROM $table_autoeval a
            LEFT JOIN $table_materias m ON a.materia_id = m.id
            WHERE a.id = %d",
            $id
        ));

        if ($evaluation) {
            // Obtener preguntas asociadas
            $evaluation->preguntas = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_preguntas
                WHERE autoevaluacion_id = %d
                ORDER BY created_at ASC",
                $id
            ));

            // Calcular totales basados en preguntas reales
            $evaluation->total_puntos = 0;
            $evaluation->puntos_obtenidos = 0;
            
            foreach ($evaluation->preguntas as $pregunta) {
                $evaluation->total_puntos += 10; // Cada pregunta vale 10 puntos
                $evaluation->puntos_obtenidos += $pregunta->puntuacion;
            }

            $evaluation->porcentaje = $evaluation->total_puntos > 0 ?
                round(($evaluation->puntos_obtenidos / $evaluation->total_puntos) * 100, 2) : 0;
        }

        return $evaluation;
    }

    /**
     * Obtener evaluación completa con cálculo en tiempo real
     */
    public function get_full_evaluation($evaluation_id) {
        return $this->get_autoevaluacion($evaluation_id);
    }
    
    /**
     * Guardar autoevaluación
     */
    public function save_autoevaluacion($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wpsa_autoevaluaciones';
        
        $autoeval_data = array(
            'materia_id' => intval($data['materia_id']),
            'estudiante_nombre' => sanitize_text_field($data['estudiante_nombre']),
            'tema' => sanitize_text_field($data['tema']),
            'modalidad' => sanitize_text_field($data['modalidad']),
            'preguntas_respuestas' => wp_json_encode($data['preguntas_respuestas']),
            'puntuacion_total' => intval($data['puntuacion_total']),
            'puntuacion_obtenida' => intval($data['puntuacion_obtenida']),
            'porcentaje' => floatval($data['porcentaje']),
            'recomendaciones' => sanitize_textarea_field($data['recomendaciones']),
            'estado' => sanitize_text_field($data['estado'])
        );
        
        if (isset($data['id']) && !empty($data['id'])) {
            // Actualizar
            $result = $wpdb->update(
                $table,
                $autoeval_data,
                array('id' => intval($data['id'])),
                array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%s', '%s'),
                array('%d')
            );
            return $data['id'];
        } else {
            // Insertar
            $result = $wpdb->insert($table, $autoeval_data);
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Obtener estadísticas de autoevaluaciones
     */
    public function get_estadisticas($filters = array()) {
        global $wpdb;
        
        $table_autoeval = $wpdb->prefix . 'wpsa_autoevaluaciones';
        $table_materias = $wpdb->prefix . 'wpsa_materias';
        
        $where = array();
        $values = array();
        
        if (!empty($filters['materia_id'])) {
            $where[] = "a.materia_id = %d";
            $values[] = intval($filters['materia_id']);
        }
        
        if (!empty($filters['fecha_desde'])) {
            $where[] = "DATE(a.created_at) >= %s";
            $values[] = $filters['fecha_desde'];
        }
        
        if (!empty($filters['fecha_hasta'])) {
            $where[] = "DATE(a.created_at) <= %s";
            $values[] = $filters['fecha_hasta'];
        }
        
        $where_clause = '';
        if (!empty($where)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where);
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_autoevaluaciones,
                    COUNT(CASE WHEN estado = 'completada' THEN 1 END) as completadas,
                    COUNT(CASE WHEN estado = 'en_progreso' THEN 1 END) as en_progreso,
                    AVG(porcentaje) as promedio_porcentaje,
                    COUNT(DISTINCT estudiante_nombre) as estudiantes_unicos
                FROM $table_autoeval a 
                LEFT JOIN $table_materias m ON a.materia_id = m.id 
                $where_clause";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Obtener grados únicos
     */
    public function get_grados() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wpsa_materias';
        $sql = "SELECT DISTINCT grado FROM $table ORDER BY grado";
        
        return $wpdb->get_col($sql);
    }
    
    /**
     * Eliminar una autoevaluación
     */
    public function delete_autoevaluacion($id) {
        global $wpdb;

        $table = $wpdb->prefix . 'wpsa_autoevaluaciones';

        $result = $wpdb->delete(
            $table,
            array('id' => intval($id)),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Guardar pregunta individual
     */
    public function save_pregunta($data) {
        global $wpdb;
        
        error_log("WPSA Database: ===== INICIO save_pregunta =====");
        error_log("WPSA Database: Datos recibidos: " . print_r($data, true));
        
        $table = $wpdb->prefix . 'wpsa_preguntas';
        error_log("WPSA Database: Tabla a usar: " . $table);
        
        // Validación completa
        $required = ['autoevaluacion_id', 'pregunta', 'respuesta_estudiante'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                error_log("WPSA Database: ERROR - Campo requerido faltante: $field");
                throw new Exception("Campo requerido faltante: $field");
            }
        }

        error_log("WPSA Database: Validación de campos requeridos pasada");

        $pregunta_data = array(
            'autoevaluacion_id' => absint($data['autoevaluacion_id']),
            'pregunta' => sanitize_textarea_field($data['pregunta']),
            'respuesta_estudiante' => sanitize_textarea_field($data['respuesta_estudiante']),
            'respuesta_correcta' => sanitize_textarea_field($data['respuesta_correcta'] ?? ''),
            'puntuacion' => min(max((int)($data['puntuacion'] ?? 0), 0), 10),
            'feedback' => sanitize_textarea_field($data['feedback'] ?? ''),
            'created_at' => current_time('mysql')
        );

        error_log("WPSA Database: Datos preparados para insertar: " . print_r($pregunta_data, true));

        $result = $wpdb->insert($table, $pregunta_data);
        
        if ($result === false) {
            error_log("WPSA Database: ERROR - Insert failed. Last error: " . $wpdb->last_error);
            error_log("WPSA Database: Last query: " . $wpdb->last_query);
            return false;
        }

        $insert_id = $wpdb->insert_id;
        error_log("WPSA Database: Insert successful. ID: " . $insert_id);
        error_log("WPSA Database: ===== ÉXITO save_pregunta =====");
        
        return $insert_id;
    }

    /**
     * Obtener preguntas de una autoevaluación
     */
    public function get_preguntas_autoevaluacion($autoevaluacion_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'wpsa_preguntas';
        $sql = "SELECT * FROM $table WHERE autoevaluacion_id = %d ORDER BY created_at ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $autoevaluacion_id));
    }

    /**
     * Eliminar preguntas de una autoevaluación
     */
    public function delete_preguntas_autoevaluacion($autoevaluacion_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'wpsa_preguntas';

        return $wpdb->delete(
            $table,
            array('autoevaluacion_id' => intval($autoevaluacion_id)),
            array('%d')
        );
    }

    /**
     * Guardar múltiples preguntas para una autoevaluación
     */
    public function save_preguntas_autoevaluacion($autoevaluacion_id, $preguntas_data) {
        global $wpdb;

        // Primero eliminar preguntas existentes
        $this->delete_preguntas_autoevaluacion($autoevaluacion_id);

        // Guardar nuevas preguntas
        $saved_questions = array();
        foreach ($preguntas_data as $pregunta) {
            $pregunta['autoevaluacion_id'] = $autoevaluacion_id;
            $question_id = $this->save_pregunta($pregunta);
            if ($question_id) {
                $saved_questions[] = $question_id;
            }
        }

        return $saved_questions;
    }
}
