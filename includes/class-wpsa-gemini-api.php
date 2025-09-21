<?php
/**
 * Clase para integración con la API de Gemini
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSA_Gemini_API {
    
    private static $instance = null;
    private $api_key;
    private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    private $debug_data = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_key = get_option('wpsa_gemini_api_key', '');
        error_log('🔍 WPSA Debug - API Key obtenida: ' . (!empty($this->api_key) ? 'Configurada (' . strlen($this->api_key) . ' caracteres)' : 'NO CONFIGURADA'));
    }

    
    
    /**
     * Generar pregunta para autoevaluación
     */
    public function generate_question($materia, $tema = '', $modalidad = 'preguntas_simples', $grado = '', $nivel = 'intermedio', $numero_pregunta = 1, $temario = '', $materia_id = null, $previous_questions = array()) {
        error_log('🔍 WPSA Debug - Iniciando generate_question con API Key: ' . (!empty($this->api_key) ? 'Configurada' : 'NO CONFIGURADA'));
        
        if (empty($this->api_key)) {
            error_log('❌ WPSA Debug - ERROR: API Key de Gemini no está configurada');
            return false;
        }
        
        // Si se proporciona materia_id, obtener el temario directamente de la BD
        if ($materia_id) {
            $database = WPSA_Database::get_instance();
            $materia_data = $database->get_materia($materia_id);
            
            if ($materia_data) {
                // Obtener temario (prioridad: temario_analizado > temario > descripción)
                if (!empty($materia_data->temario_analizado)) {
                    $temario = $materia_data->temario_analizado;
                    error_log('🔍 WPSA Debug - Usando temario_analizado desde BD, longitud: ' . strlen($temario));
                } elseif (!empty($materia_data->temario)) {
                    $temario = $materia_data->temario;
                    error_log('🔍 WPSA Debug - Usando temario desde BD, longitud: ' . strlen($temario));
                } elseif (!empty($materia_data->descripcion)) {
                    $temario = $materia_data->descripcion;
                    error_log('🔍 WPSA Debug - Usando descripcion desde BD, longitud: ' . strlen($temario));
                } else {
                    error_log('🔍 WPSA Debug - No se encontró temario en la BD para materia_id: ' . $materia_id);
                }
                
                // Actualizar datos de la materia con los de la BD
                $materia = $materia_data->nombre;
                $grado = $materia_data->grado;
            }
        }
        
        // Mostrar en consola todos los datos que recibe la función generate_question
        $datos_recibidos = array(
            'materia' => $materia,
            'tema' => $tema,
            'modalidad' => $modalidad,
            'grado' => $grado,
            'nivel' => $nivel,
            'numero_pregunta' => $numero_pregunta,
            'temario' => $temario,
            'temario_length' => strlen($temario),
            'materia_id' => $materia_id,
            'api_key_configured' => !empty($this->api_key),
            'timestamp' => current_time('mysql')
        );
        
        error_log('🔍 WPSA Debug - Datos recibidos en generate_question(): ' . print_r($datos_recibidos, true));
        
        // Agregar datos de debug a la respuesta para mostrarlos en consola del navegador
        $this->debug_data = $datos_recibidos;
        
        $prompt = $this->build_question_prompt($materia, $tema, $modalidad, $grado, $nivel, $numero_pregunta, $temario, $previous_questions);
        
        // Mostrar el prompt completo que se envía a la IA
        error_log('🤖 WPSA Debug - Prompt completo enviado a la IA:');
        error_log('=====================================');
        error_log($prompt);
        error_log('=====================================');
        error_log('📏 Longitud del prompt: ' . strlen($prompt) . ' caracteres');
        
        // Agregar el prompt a los datos de debug para mostrarlo en consola
        $this->debug_data['prompt_completo'] = $prompt;
        $this->debug_data['prompt_length'] = strlen($prompt);
        
        error_log('🔍 WPSA Debug - Haciendo petición a la API de Gemini...');
        $response = $this->make_api_request($prompt);
        
        error_log('🔍 WPSA Debug - Respuesta de la API de Gemini: ' . print_r($response, true));
        
        if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            error_log('✅ WPSA Debug - Respuesta válida recibida de la API');
            $question_data = $this->parse_question_response($response['candidates'][0]['content']['parts'][0]['text'], $modalidad);
            
            error_log('🔍 WPSA Debug - Pregunta parseada: ' . print_r($question_data, true));
            
            // Agregar datos de debug a la respuesta
            if ($question_data && $this->debug_data) {
                $question_data['debug_data'] = $this->debug_data;
            }
            
            return $question_data;
        } else {
            error_log('❌ WPSA Debug - Error: Respuesta inválida de la API de Gemini');
            if ($response) {
                error_log('❌ WPSA Debug - Estructura de respuesta: ' . print_r($response, true));
            } else {
                error_log('❌ WPSA Debug - La respuesta es null o false');
            }
        }
        
        return false;
    }
    
    /**
     * Evaluar respuesta del estudiante
     */
    public function evaluate_answer($pregunta, $respuesta_estudiante, $respuesta_correcta = '') {
        if (empty($this->api_key)) {
            error_log('❌ API key missing for evaluation');
            return ['error' => 'API no configurada'];
        }
        
        try {
            $prompt = $this->build_evaluation_prompt($pregunta, $respuesta_estudiante, $respuesta_correcta);
            $response = $this->make_api_request($prompt);
            
            if (!$response || !isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                throw new Exception('Respuesta vacía de la API');
            }
            
            $evaluation = $this->parse_evaluation_response($response['candidates'][0]['content']['parts'][0]['text']);
            
            // Validate minimum response structure
            if (!isset($evaluation['puntuacion']) || !isset($evaluation['feedback'])) {
                throw new Exception('Estructura de evaluación inválida');
            }
            
            return $evaluation;
            
        } catch (Exception $e) {
            error_log('❌ Error en evaluación: ' . $e->getMessage());
            return $this->generate_fallback_evaluation($respuesta_estudiante);
        }
    }
    
    private function generate_fallback_evaluation($respuesta, $pregunta) {
        // Análisis léxico mejorado
        $respuesta_limpia = strip_tags($respuesta);
        $word_count = str_word_count($respuesta_limpia);
        
        // Detectar elementos clave
        $has_example = preg_match('/ejemplo|por ejemplo|como/i', $respuesta_limpia);
        $has_explanation = preg_match('/porque|por qué|debido a|ya que/i', $respuesta_limpia);
        $has_tech_terms = preg_match('/\b(?:función|variable|algoritmo|método|clase|objeto)\b/i', $pregunta);
        
        // Calcular puntuación
        $score = min(10, max(0,
            round($word_count / 15) +
            ($has_example ? 3 : 0) +
            ($has_explanation ? 4 : 0) +
            ($has_tech_terms ? 3 : 0)
        ));

        // Generar feedback contextual
        $feedback = [];
        
        if ($word_count < 20) {
            $feedback[] = "Respuesta demasiado corta. Desarrolla más tus ideas.";
        } elseif ($word_count > 100) {
            $feedback[] = "Buena extensión, pero sé más conciso.";
        }
        
        if (!$has_example) {
            $feedback[] = "Incluir un ejemplo práctico mejoraría tu respuesta.";
        }
        
        if (!$has_explanation) {
            $feedback[] = "Explica el por qué detrás de los conceptos.";
        }
        
        if (empty($feedback)) {
            $feedback[] = "Buena estructura general. Revisa los detalles técnicos.";
        }

        return [
            'puntuacion' => $score,
            'feedback' => implode(' ', $feedback),
            'recomendaciones' => "Practica con ejercicios similares y revisa los conceptos clave."
        ];
    }
    
    /**
     * Analizar PDF y extraer temario usando la nueva API de documentos de Gemini
     */
    public function analyze_pdf($pdf_url) {
        if (empty($this->api_key)) {
            return false;
        }
        
        // Descargar el PDF
        $pdf_content = $this->download_pdf($pdf_url);
        if (!$pdf_content) {
            return false;
        }
        
        // Codificar el PDF en base64
        $pdf_base64 = base64_encode($pdf_content);
        
        // Crear el prompt para análisis
        $prompt = "Analiza este programa de curso en PDF y extrae un temario estructurado y detallado. Organiza los temas por unidades o módulos y proporciona una descripción breve pero completa de cada tema. Incluye conceptos específicos, tecnologías mencionadas, y objetivos de aprendizaje. El formato debe ser claro y útil para generar preguntas de evaluación.";
        
        // Usar la nueva API de documentos de Gemini
        $response = $this->make_document_api_request($pdf_base64, $prompt);
        
        if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return $response['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return false;
    }
    
    /**
     * Generar recomendaciones basadas en el rendimiento
     */
    public function generate_recommendations($autoevaluacion_data) {
        if (empty($this->api_key)) {
            return false;
        }
        
        $prompt = $this->build_recommendations_prompt($autoevaluacion_data);
        
        $response = $this->make_api_request($prompt);
        
        if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return $response['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return false;
    }
    
    /**
     * Construir prompt para generar pregunta
     */
    private function build_question_prompt($materia, $tema, $modalidad, $grado, $nivel, $numero_pregunta, $temario = '', $previous_questions = array()) {
        // Log de parámetros recibidos en build_question_prompt
        error_log('🔍 WPSA Debug - Parámetros en build_question_prompt:');
        error_log('  - Materia: ' . $materia);
        error_log('  - Tema: ' . $tema);
        error_log('  - Modalidad: ' . $modalidad);
        error_log('  - Grado: ' . $grado);
        error_log('  - Nivel: ' . $nivel);
        error_log('  - Número pregunta: ' . $numero_pregunta);
        error_log('  - Temario length: ' . strlen($temario));
        
        // Generar variación única para evitar repetición
        $variacion_id = $this->generate_variation_id($materia, $numero_pregunta, $nivel);
        
        $base_prompt = "IMPORTANTE: SIGUE EXACTAMENTE LAS SIGUIENTES INSTRUCCIONES. Eres un profesor experto en {$materia}";
        
        if (!empty($grado)) {
            $base_prompt .= " para estudiantes de {$grado}";
        }
        
        $base_prompt .= ". Genera una pregunta de autoevaluación ÚNICA y VARIADA";
        
        // Si hay temario disponible, usarlo para generar preguntas más específicas
        if (!empty($temario)) {
            $base_prompt .= " basándote EXCLUSIVAMENTE en el siguiente temario de la materia:\n\n";
            $base_prompt .= "=== TEMARIO OFICIAL DE LA MATERIA ===\n";
            $base_prompt .= $temario . "\n";
            $base_prompt .= "=== FIN DEL TEMARIO ===\n\n";
            
            // Extraer temas específicos del temario
            $specific_topics = $this->extract_specific_topics($temario, $nivel);
            if (!empty($specific_topics)) {
                $base_prompt .= "=== TEMAS ESPECÍFICOS IDENTIFICADOS PARA NIVEL {$nivel} ===\n";
                foreach ($specific_topics as $index => $topic) {
                    $base_prompt .= ($index + 1) . ". " . $topic . "\n";
                }
                $base_prompt .= "=== FIN DE TEMAS ESPECÍFICOS ===\n\n";
            }
            
            $base_prompt .= "INSTRUCCIONES CRÍTICAS:\n";
            $base_prompt .= "1. DEBES usar SOLO conceptos, temas y contenidos que aparezcan en el temario anterior\n";
            $base_prompt .= "2. NO uses conceptos genéricos que no estén en el temario\n";
            $base_prompt .= "3. La pregunta debe estar DIRECTAMENTE relacionada con los temas del temario\n";
            $base_prompt .= "4. Si el temario menciona tecnologías específicas (PHP, JavaScript, etc.), úsalas\n";
            $base_prompt .= "5. Si el temario menciona conceptos específicos, enfócate en ellos\n";
            $base_prompt .= "6. PRIORIZA los temas específicos identificados arriba\n\n";
            
            if (!empty($tema)) {
                $base_prompt .= "TEMA ESPECÍFICO SOLICITADO: {$tema}\n";
                $base_prompt .= "Si este tema aparece en el temario, úsalo. Si no, selecciona un tema similar del temario.\n\n";
            } else {
                $base_prompt .= "SELECCIÓN DE TEMA: Elige un tema ESPECÍFICO del temario que sea apropiado para el nivel {$nivel}\n";
                $base_prompt .= "PREFERIBLEMENTE de los temas específicos identificados arriba.\n\n";
            }
        } else {
            if (!empty($tema)) {
                $base_prompt .= " específicamente sobre el tema: {$tema}";
            } else {
                $base_prompt .= " sobre conceptos fundamentales de {$materia}";
            }
        }
        
        // Configurar nivel de dificultad
        $nivel_instrucciones = $this->get_nivel_instrucciones($nivel, $numero_pregunta);
        $base_prompt .= " con nivel de dificultad: {$nivel_instrucciones}";
        
        // Agregar instrucciones específicas sobre modalidad y nivel
        $base_prompt .= "\n\n🚨 INSTRUCCIONES CRÍTICAS OBLIGATORIAS - NO LAS IGNORES:";
        $base_prompt .= "\n🚨 MODALIDAD SELECCIONADA POR EL USUARIO: " . strtoupper($modalidad);
        $base_prompt .= "\n🚨 NIVEL SELECCIONADO POR EL USUARIO: " . strtoupper($nivel);
        $base_prompt .= "\n🚨 TEMA ESPECÍFICO SOLICITADO: " . (!empty($tema) ? $tema : 'CUALQUIER TEMA DEL TEMARIO');
        $base_prompt .= "\n🚨 DEBES RESPETAR EXACTAMENTE estas configuraciones del usuario. NO cambies la modalidad ni el nivel.";
        $base_prompt .= "\n🚨 EJEMPLO: Si modalidad es 'preguntas_simples', genera UNA pregunta simple, NO un ejercicio o código.";
        $base_prompt .= "\n🚨 EJEMPLO: Si nivel es 'inicial', usa preguntas básicas como '¿Qué es X?', NO complejas.";
        $base_prompt .= "\n🚨 Si tema es '{$tema}', la pregunta DEBE relacionarse directamente con él.";
        
        // Agregar preguntas anteriores para evitar repeticiones
        if (!empty($previous_questions)) {
            $base_prompt .= "\n\n🚫 PREGUNTAS ANTERIORES EN ESTA EVALUACIÓN (ESTRICTAMENTE PROHIBIDO REPETIR):";
            foreach ($previous_questions as $index => $prev_question) {
                $question_text = is_array($prev_question) ? ($prev_question['pregunta'] ?? $prev_question['question'] ?? '') : ($prev_question->pregunta ?? $prev_question->question ?? '');
                if (!empty($question_text)) {
                    $base_prompt .= "\n" . ($index + 1) . ". " . substr($question_text, 0, 150) . (strlen($question_text) > 150 ? "..." : "");
                }
            }
            $base_prompt .= "\n\n⚠️ INSTRUCCIONES CRÍTICAS PARA EVITAR REPETICIÓN:";
            $base_prompt .= "\n- NO copies ninguna de las preguntas anteriores";
            $base_prompt .= "\n- NO uses los mismos temas o conceptos principales";
            $base_prompt .= "\n- NO preguntes sobre aspectos similares de los mismos temas";
            $base_prompt .= "\n- Cambia completamente el enfoque y perspectiva";
            $base_prompt .= "\n- Si preguntaste sobre 'variables', ahora pregunta sobre 'funciones' o 'bucles'";
            $base_prompt .= "\n- Si preguntaste sobre 'importancia', ahora pregunta sobre 'aplicación práctica'";
            $base_prompt .= "\n- Si preguntaste sobre 'definición', ahora pregunta sobre 'ejemplos' o 'casos de uso'";
            $base_prompt .= "\n- Usa un tema completamente diferente del temario";
            $base_prompt .= "\n\nSi no puedes encontrar un tema diferente en el temario, es mejor generar una pregunta genérica pero completamente diferente que las anteriores.";
        }

        // Agregar variación única basada en el ID de variación
        $base_prompt .= "\n\nVARIACIÓN ÚNICA #{$variacion_id}: Para hacer la evaluación completamente diferente y evitar cualquier similitud con preguntas anteriores, usa el siguiente enfoque específico:";

        $enfoques = $this->get_variation_approaches($variacion_id);
        $base_prompt .= "\n" . $enfoques;

        // Agregar instrucciones adicionales para asegurar variedad
        $base_prompt .= "\n\nGARANTIZANDO VARIEDAD EXTREMA:";
        $base_prompt .= "\n- Si es la primera pregunta: usa un enfoque introductorio básico";
        $base_prompt .= "\n- Si es la segunda pregunta: cambia completamente a un enfoque aplicado";
        $base_prompt .= "\n- Si es la tercera pregunta: usa un enfoque analítico o de resolución de problemas";
        $base_prompt .= "\n- Si es la cuarta pregunta: enfócate en casos prácticos o escenarios reales";
        $base_prompt .= "\n- Si es la quinta pregunta: pregunta sobre tendencias, futuro o evolución del tema";
        $previous_count = $numero_pregunta - 1;
        $base_prompt .= "\n\nPregunta actual: #{$numero_pregunta} - Asegúrate de que sea completamente diferente a las {$previous_count} preguntas anteriores.";
        
        if (!empty($temario)) {
            $base_prompt .= "\n\nREQUISITOS OBLIGATORIOS PARA LA PREGUNTA:\n";
            $base_prompt .= "1. DEBE estar basada en un tema específico del temario\n";
            $base_prompt .= "2. DEBE usar terminología y conceptos del temario\n";
            $base_prompt .= "3. DEBE ser aplicable al contexto de la materia según el temario\n";
            $base_prompt .= "4. NO uses conceptos genéricos que no aparezcan en el temario\n";
            $base_prompt .= "5. Si el temario menciona herramientas específicas, úsalas en la pregunta\n";
            $base_prompt .= "6. Si el temario menciona metodologías específicas, enfócate en ellas\n\n";
            
            $base_prompt .= "ENFOQUES PERMITIDOS (basados en el temario):\n";
            $base_prompt .= "- Aplicación práctica de conceptos del temario en escenarios reales\n";
            $base_prompt .= "- Comparación de diferentes enfoques mencionados en el temario\n";
            $base_prompt .= "- Análisis de casos específicos relacionados con los temas del temario\n";
            $base_prompt .= "- Resolución de problemas usando metodologías del temario\n";
            $base_prompt .= "- Identificación de errores comunes en temas del temario\n";
            $base_prompt .= "- Ejemplos prácticos de aplicación de conceptos del temario\n";
            $base_prompt .= "- Ventajas y desventajas de diferentes métodos del temario\n";
            $base_prompt .= "- Análisis crítico de situaciones relacionadas con el temario\n";
            $base_prompt .= "- Conexiones entre diferentes temas del temario\n";
        } else {
            $base_prompt .= "\n\nIMPORTANTE: Esta pregunta debe ser COMPLETAMENTE DIFERENTE a cualquier pregunta anterior. Evita conceptos básicos obvios como '¿Qué es una variable?' o '¿Qué es un bucle?'. En su lugar, enfócate en:";
            $base_prompt .= "\n- Aplicaciones prácticas del concepto";
            $base_prompt .= "\n- Comparaciones entre diferentes enfoques";
            $base_prompt .= "\n- Análisis de casos específicos";
            $base_prompt .= "\n- Resolución de problemas paso a paso";
            $base_prompt .= "\n- Identificación de errores comunes";
            $base_prompt .= "\n- Ejemplos del mundo real";
            $base_prompt .= "\n- Ventajas y desventajas de diferentes métodos";
            $base_prompt .= "\n- Análisis crítico de situaciones";
            $base_prompt .= "\n- Conexiones entre conceptos relacionados";
        }
        
        // Agregar instrucciones para variar el tipo de pregunta
        $base_prompt .= "\n\nVARIACIÓN DE PREGUNTAS: Para evitar repetición, varía el tipo de pregunta:";
        $base_prompt .= "\n- Preguntas de comprensión (¿Qué es...?, ¿Cómo funciona...?)";
        $base_prompt .= "\n- Preguntas de aplicación (¿Cómo aplicarías...?, ¿Qué pasaría si...?)";
        $base_prompt .= "\n- Preguntas de análisis (¿Por qué...?, ¿Cuál es la diferencia entre...?)";
        $base_prompt .= "\n- Preguntas de síntesis (¿Cómo combinarías...?, ¿Qué estrategia usarías...?)";
        $base_prompt .= "\n- Preguntas de evaluación (¿Cuál es mejor...?, ¿Qué ventajas tiene...?)";
        
        // Agregar contexto único para cada pregunta
        $contexto_unico = $this->generate_unique_context($numero_pregunta, $nivel, $modalidad);
        $base_prompt .= "\n\nCONTEXTO ÚNICO PARA ESTA PREGUNTA: " . $contexto_unico;
        
        // Instrucciones específicas por modalidad
        $base_prompt .= "\n\nFORMATO OBLIGATORIO SEGÚN MODALIDAD SELECCIONADA:";
        
        switch ($modalidad) {
            case 'preguntas_simples':
                $base_prompt .= "\n\nMODALIDAD: PREGUNTAS SIMPLES";
                $base_prompt .= "\n- DEBE ser una pregunta directa y clara";
                $base_prompt .= "\n- NO debe ser un ejercicio o problema a resolver";
                $base_prompt .= "\n- DEBE permitir al estudiante reflexionar sobre conceptos clave";
                $base_prompt .= "\n- DEBE promover el pensamiento crítico";
                $base_prompt .= "\n- Formato: '¿Qué es...?', '¿Cómo funciona...?', '¿Por qué...?', '¿Cuál es la diferencia entre...?'";
                break;
                
            case 'ejercicios':
                $base_prompt .= "\n\nMODALIDAD: EJERCICIOS PRÁCTICOS";
                $base_prompt .= "\n- DEBE ser un ejercicio práctico o problema a resolver";
                $base_prompt .= "\n- NO debe ser una pregunta teórica simple";
                $base_prompt .= "\n- DEBE incluir todos los datos necesarios";
                $base_prompt .= "\n- DEBE especificar claramente qué se debe calcular o resolver";
                $base_prompt .= "\n- DEBE tener pasos claros de resolución";
                $base_prompt .= "\n- Formato: 'Calcula...', 'Resuelve...', 'Implementa...', 'Diseña...'";
                break;
                
            case 'codigo':
                $base_prompt .= "\n\nMODALIDAD: ANÁLISIS DE CÓDIGO/SITUACIONES";
                $base_prompt .= "\n- DEBE involucrar análisis de código, situaciones o problemas";
                $base_prompt .= "\n- NO debe ser una pregunta teórica simple";
                $base_prompt .= "\n- DEBE proporcionar un fragmento de código, situación o problema";
                $base_prompt .= "\n- DEBE pedir al estudiante que explique, identifique errores, o proponga mejoras";
                $base_prompt .= "\n- Formato: 'Analiza este código...', 'Explica qué hace...', 'Identifica el error...'";
                break;
        }
        
        // Instrucciones específicas por nivel
        $base_prompt .= "\n\nNIVEL DE DIFICULTAD OBLIGATORIO:";
        
        switch ($nivel) {
            case 'inicial':
                $base_prompt .= "\n\nNIVEL: INICIAL (EXTREMADAMENTE FÁCIL)";
                $base_prompt .= "\n- DEBE ser una pregunta OBVIA que cualquier principiante pueda responder";
                $base_prompt .= "\n- NO debe requerir conocimientos avanzados";
                $base_prompt .= "\n- DEBE usar conceptos básicos y fundamentales";
                $base_prompt .= "\n- DEBE ser directa y sin ambigüedades";
                $base_prompt .= "\n- Ejemplo: '¿Qué es una variable?', '¿Cuál es la función de un bucle for?'";
                break;
                
            case 'intermedio':
                $base_prompt .= "\n\nNIVEL: INTERMEDIO";
                $base_prompt .= "\n- DEBE requerir comprensión sólida del tema";
                $base_prompt .= "\n- NO debe ser extremadamente fácil ni extremadamente difícil";
                $base_prompt .= "\n- DEBE permitir al estudiante demostrar que entiende los conceptos";
                $base_prompt .= "\n- DEBE ser aplicable en situaciones reales";
                $base_prompt .= "\n- Ejemplo: 'Explica cómo implementarías...', '¿Cuál es la diferencia entre...?'";
                break;
                
            case 'avanzado':
                $base_prompt .= "\n\nNIVEL: AVANZADO (COMPLEJO)";
                $base_prompt .= "\n- DEBE ser una pregunta técnica compleja";
                $base_prompt .= "\n- DEBE requerir conocimientos profundos y experiencia";
                $base_prompt .= "\n- DEBE ser ideal para evaluar competencias técnicas avanzadas";
                $base_prompt .= "\n- DEBE requerir análisis crítico y síntesis";
                $base_prompt .= "\n- Ejemplo: 'Diseña una arquitectura...', 'Optimiza este algoritmo...'";
                break;
        }
        
        // Si hay temario, agregar instrucciones específicas para extraer temas
        if (!empty($temario)) {
            $base_prompt .= "\n\nINSTRUCCIONES FINALES:\n";
            $base_prompt .= "1. Lee cuidadosamente el temario completo\n";
            $base_prompt .= "2. Identifica un tema específico que sea apropiado para el nivel {$nivel}\n";
            $base_prompt .= "3. Crea una pregunta que evalúe la comprensión de ese tema específico\n";
            $base_prompt .= "4. Usa la terminología exacta del temario\n";
            $base_prompt .= "5. Asegúrate de que la pregunta sea práctica y aplicable\n";
            $base_prompt .= "6. La pregunta debe requerir conocimiento del tema específico del temario\n\n";
            
            $base_prompt .= "EJEMPLO DE PREGUNTA CORRECTA:\n";
            $base_prompt .= "Si el temario menciona 'MVC en PHP', una buena pregunta sería:\n";
            $base_prompt .= "'Explica cómo implementarías el patrón MVC en PHP para una aplicación web de gestión de usuarios, detallando la separación de responsabilidades entre Model, View y Controller.'\n\n";
            $base_prompt .= "EJEMPLO DE PREGUNTA INCORRECTA:\n";
            $base_prompt .= "'¿Qué es una variable en programación?' (demasiado genérica, no específica del temario)\n\n";
        }
        
        // Instrucción final crítica
        $base_prompt .= "\n\nINSTRUCCIÓN FINAL CRÍTICA:";
        $base_prompt .= "\n- MODALIDAD OBLIGATORIA: {$modalidad}";
        $base_prompt .= "\n- NIVEL OBLIGATORIO: {$nivel}";
        $base_prompt .= "\n- DEBES generar EXACTAMENTE el tipo de pregunta que corresponde a esta modalidad y nivel";
        $base_prompt .= "\n- NO generes preguntas de desarrollo complejas si el nivel es 'inicial'";
        $base_prompt .= "\n- NO generes preguntas simples si la modalidad es 'ejercicios' o 'codigo'";
        $base_prompt .= "\n- RESPETA las instrucciones específicas de modalidad y nivel dadas arriba";
        
        $base_prompt .= "\n\nFormato de respuesta esperado:\n";
        $base_prompt .= "PREGUNTA: [La pregunta aquí]\n";
        $base_prompt .= "RESPUESTA_CORRECTA: [La respuesta correcta o puntos clave]\n";
        $base_prompt .= "PUNTUACION: [Puntuación máxima, ej: 10]\n";
        $base_prompt .= "DIFICULTAD: [baja/media/alta]";
        
        return $base_prompt;
    }
    
    /**
     * Generar ID de variación único para evitar repetición
     */
    private function generate_variation_id($materia, $numero_pregunta, $nivel) {
        // Crear un ID único basado en materia, número de pregunta y nivel
        $seed = md5($materia . $numero_pregunta . $nivel . date('Y-m-d-H'));
        return substr($seed, 0, 8);
    }
    
    /**
     * Obtener enfoques de variación específicos
     */
    private function get_variation_approaches($variacion_id) {
        $enfoques = array(
            'Aplica el concepto en un escenario del mundo real específico',
            'Compara diferentes enfoques o metodologías para resolver el mismo problema',
            'Analiza un caso de estudio específico y extrae conclusiones',
            'Identifica errores comunes y explica cómo evitarlos',
            'Explica las ventajas y desventajas de diferentes implementaciones',
            'Conecta este concepto con otros temas relacionados',
            'Propón una solución creativa a un problema complejo',
            'Evalúa la eficiencia de diferentes estrategias',
            'Analiza las implicaciones de usar este concepto en diferentes contextos',
            'Explica cómo este concepto evoluciona o se adapta a nuevas tecnologías',
            'Identifica patrones comunes y excepciones importantes',
            'Propón mejoras o optimizaciones a un enfoque existente',
            'Analiza el impacto de este concepto en la experiencia del usuario',
            'Explica cómo este concepto se relaciona con principios fundamentales',
            'Evalúa la escalabilidad y mantenibilidad de diferentes enfoques'
        );
        
        // Usar el ID de variación para seleccionar un enfoque específico
        $indice = hexdec(substr($variacion_id, 0, 2)) % count($enfoques);
        return $enfoques[$indice];
    }
    
    /**
     * Generar contexto único para cada pregunta
     */
    private function generate_unique_context($numero_pregunta, $nivel, $modalidad) {
        $contextos = array(
            'preguntas_simples' => array(
                'inicial' => array(
                    'Piensa en un ejemplo simple de tu vida diaria',
                    'Considera una situación básica que todos conocemos',
                    'Imagina que estás explicando a un niño pequeño',
                    'Piensa en el primer paso para entender este concepto',
                    'Considera la aplicación más simple posible'
                ),
                'intermedio' => array(
                    'Analiza una situación real donde esto se aplica',
                    'Considera las implicaciones prácticas de este concepto',
                    'Piensa en cómo esto se relaciona con otros temas',
                    'Analiza un caso específico del mundo real',
                    'Considera las ventajas y desventajas de diferentes enfoques'
                ),
                'avanzado' => array(
                    'Analiza las implicaciones profundas de este concepto',
                    'Considera las limitaciones y excepciones',
                    'Piensa en las conexiones con múltiples disciplinas',
                    'Analiza un caso complejo que requiera síntesis',
                    'Considera las tendencias futuras y evolución del concepto'
                )
            ),
            'ejercicios' => array(
                'inicial' => array(
                    'Usa números simples y operaciones básicas',
                    'Aplica el método paso a paso más directo',
                    'Considera un problema que puedas resolver mentalmente',
                    'Usa ejemplos con objetos familiares',
                    'Aplica la fórmula más simple disponible'
                ),
                'intermedio' => array(
                    'Incluye múltiples pasos y verificaciones',
                    'Considera diferentes métodos de resolución',
                    'Incluye datos realistas pero manejables',
                    'Requiere análisis de resultados',
                    'Combina múltiples conceptos relacionados'
                ),
                'avanzado' => array(
                    'Incluye casos límite y excepciones',
                    'Requiere optimización y análisis crítico',
                    'Combina múltiples disciplinas o enfoques',
                    'Incluye consideraciones de eficiencia',
                    'Requiere síntesis de conocimientos avanzados'
                )
            ),
            'codigo' => array(
                'inicial' => array(
                    'Usa código simple y bien comentado',
                    'Incluye ejemplos de uso básico',
                    'Considera errores comunes de principiantes',
                    'Usa nombres de variables descriptivos',
                    'Incluye casos de uso simples'
                ),
                'intermedio' => array(
                    'Incluye patrones de diseño comunes',
                    'Considera optimizaciones básicas',
                    'Incluye manejo de errores',
                    'Requiere análisis de complejidad',
                    'Combina múltiples conceptos de programación'
                ),
                'avanzado' => array(
                    'Incluye patrones avanzados y arquitectura',
                    'Requiere análisis de rendimiento',
                    'Considera escalabilidad y mantenibilidad',
                    'Incluye casos de uso complejos',
                    'Requiere conocimiento de mejores prácticas'
                )
            )
        );
        
        $nivel_contextos = $contextos[$modalidad][$nivel] ?? $contextos[$modalidad]['intermedio'];
        $indice = ($numero_pregunta - 1) % count($nivel_contextos);
        
        return $nivel_contextos[$indice];
    }
    
    /**
     * Obtener instrucciones de nivel de dificultad
     */
    private function get_nivel_instrucciones($nivel, $numero_pregunta) {
        $progresion = min($numero_pregunta * 0.1, 0.5); // Progresión gradual hasta 50%
        
        switch ($nivel) {
            case 'inicial':
                $base = "EXTREMADAMENTE FÁCIL - Pregunta obvia que cualquier persona con conocimientos básicos puede responder correctamente. ";
                $base .= "Considera neurodivergencias: usa lenguaje simple, evita ambigüedades, pregunta directa y clara. ";
                $base .= "La respuesta debe ser evidente para alguien que apenas conoce el tema. ";
                $base .= "Progresión: Pregunta " . $numero_pregunta . " (aumenta dificultad gradualmente: " . round($progresion * 100) . "% más difícil que la primera)";
                return $base;
                
            case 'intermedio':
                $base = "NIVEL INTERMEDIO - Pregunta que requiere comprensión sólida del tema pero no conocimientos avanzados. ";
                $base .= "El estudiante debe demostrar que entiende los conceptos y puede aplicarlos. ";
                $base .= "Progresión: Pregunta " . $numero_pregunta . " (aumenta dificultad gradualmente: " . round($progresion * 100) . "% más difícil que la primera)";
                return $base;
                
            case 'avanzado':
                $base = "NIVEL AVANZADO - Pregunta técnica compleja que requiere conocimientos profundos y experiencia. ";
                $base .= "Ideal para evaluar competencias técnicas avanzadas, como en pruebas técnicas de programación. ";
                $base .= "El estudiante debe demostrar dominio experto del tema. ";
                $base .= "Progresión: Pregunta " . $numero_pregunta . " (aumenta dificultad gradualmente: " . round($progresion * 100) . "% más difícil que la primera)";
                return $base;
                
            default:
                return "NIVEL INTERMEDIO - Pregunta que requiere comprensión sólida del tema.";
        }
    }
    
    /**
     * Construir prompt para evaluar respuesta con feedback variado y específico
     */
    private function build_evaluation_prompt($pregunta, $respuesta_estudiante, $respuesta_correcta) {
        // Generar ID único para variar el feedback
        $feedback_variation_id = $this->generate_feedback_variation_id($pregunta, $respuesta_estudiante);

        $prompt = "Eres un profesor experto evaluando la respuesta de un estudiante. ";
        $prompt .= "Proporciona una evaluación ÚNICA, VARIADA y ESPECÍFICA que sea diferente a cualquier evaluación anterior.\n\n";

        $prompt .= "=== INFORMACIÓN DE LA EVALUACIÓN ===\n";
        $prompt .= "PREGUNTA ORIGINAL: {$pregunta}\n\n";
        $prompt .= "RESPUESTA DEL ESTUDIANTE: {$respuesta_estudiante}\n\n";

        if (!empty($respuesta_correcta)) {
            $prompt .= "RESPUESTA CORRECTA DE REFERENCIA: {$respuesta_correcta}\n\n";
        }

        // Agregar contexto específico basado en la variación
        $contexto_especifico = $this->get_feedback_context($feedback_variation_id);
        $prompt .= "=== CONTEXTO DE EVALUACIÓN ===\n";
        $prompt .= $contexto_especifico . "\n\n";

        $prompt .= "=== CRITERIOS DE EVALUACIÓN OBLIGATORIOS ===\n";
        $prompt .= "1. **GENEROSIDAD EN PUNTUACIÓN**: Si la respuesta demuestra comprensión básica del tema, otorga al menos 6-7 puntos. Solo usa puntuaciones bajas (1-3) para respuestas completamente incorrectas o vacías\n";
        $prompt .= "2. **ORIGINALIDAD**: Tu evaluación debe ser COMPLETAMENTE DIFERENTE a evaluaciones genéricas\n";
        $prompt .= "3. **ESPECIFICIDAD**: Enfócate en aspectos concretos de la respuesta del estudiante\n";
        $prompt .= "4. **CONSTRUCTIVIDAD**: Proporciona feedback que ayude al estudiante a mejorar\n";
        $prompt .= "5. **CONTEXTO**: Relaciona la evaluación con aplicaciones prácticas del tema\n";
        $prompt .= "6. **VARIEDAD**: Usa diferentes enfoques y perspectivas en tu evaluación\n\n";

        // Agregar enfoque específico basado en la variación
        $enfoque_especifico = $this->get_evaluation_approach($feedback_variation_id);
        $prompt .= "=== ENFOQUE DE EVALUACIÓN ESPECÍFICO ===\n";
        $prompt .= $enfoque_especifico . "\n\n";

        $prompt .= "=== ESTRUCTURA DE LA EVALUACIÓN ===\n";
        $prompt .= "PUNTUACION: [número del 0 al 10 basado en criterios específicos]\n";
        $prompt .= "FEEDBACK: [comentarios detallados, específicos y variados - MÍNIMO 100 palabras]\n";
        $prompt .= "RECOMENDACIONES: [sugerencias prácticas y accionables para mejorar]\n\n";

        $prompt .= "=== GUÍA DE PUNTUACIÓN ===\n";
        $prompt .= "• 8-10 puntos: Respuesta completa, correcta y bien explicada\n";
        $prompt .= "• 6-7 puntos: Respuesta básicamente correcta con algunos detalles faltantes\n";
        $prompt .= "• 4-5 puntos: Respuesta parcial o con errores conceptuales menores\n";
        $prompt .= "• 1-3 puntos: Respuesta muy básica, incorrecta o casi vacía\n";
        $prompt .= "• 0 puntos: Respuesta completamente incorrecta o no relacionada\n\n";

        $prompt .= "=== RECUERDA: SE GENEROSO ===\n";
        $prompt .= "Si el estudiante demuestra comprensión básica del tema, otorga al menos 6 puntos.\n";
        $prompt .= "Las respuestas de estudiantes que intentan responder merecen puntuaciones decentes.\n";
        $prompt .= "Solo usa puntuaciones muy bajas para respuestas completamente erróneas o vacías.\n\n";

        $prompt .= "=== INSTRUCCIONES FINALES ===\n";
        $prompt .= "- NO uses frases genéricas como 'Buen trabajo' o 'Necesitas mejorar'\n";
        $prompt .= "- Sé específico sobre qué aspectos de la respuesta son correctos/incorrectos\n";
        $prompt .= "- Relaciona tu evaluación con conceptos específicos de la pregunta\n";
        $prompt .= "- Proporciona ejemplos concretos cuando sea posible\n";
        $prompt .= "- Varía tu lenguaje y enfoque para mantener la evaluación fresca\n";
        $prompt .= "- Considera el nivel de detalle y profundidad de la respuesta\n";
        $prompt .= "- Evalúa no solo la corrección, sino también la comprensión conceptual\n\n";

        // Agregar ejemplos de evaluaciones variadas
        $prompt .= "=== EJEMPLOS DE EVALUACIONES VARIADAS (NO COPIES ESTOS FORMATOS) ===\n";
        $prompt .= "• En lugar de 'Buena explicación': 'Destacas correctamente cómo el algoritmo procesa los datos de entrada, pero podrías profundizar en la optimización del tiempo de ejecución'\n";
        $prompt .= "• En lugar de 'Falta detalle': 'Tu respuesta identifica los componentes principales, pero no aborda cómo estos interactúan en escenarios de carga elevada'\n";
        $prompt .= "• En lugar de 'Correcto': 'Excelente conexión entre la teoría de grafos y su aplicación práctica en redes sociales'\n\n";

        $prompt .= "VARIACIÓN ID: {$feedback_variation_id} - Usa este ID para generar una evaluación única\n\n";

        return $prompt;
    }

    /**
     * Generar ID de variación único para feedback
     */
    private function generate_feedback_variation_id($pregunta, $respuesta) {
        $seed = md5($pregunta . $respuesta . date('Y-m-d-H-i'));
        return substr($seed, 0, 8);
    }

    /**
     * Obtener contexto específico para la evaluación
     */
    private function get_feedback_context($variation_id) {
        $contextos = array(
            "Evalúa esta respuesta considerando el contexto práctico de aplicación del concepto",
            "Analiza la respuesta desde la perspectiva de la resolución de problemas reales",
            "Evalúa cómo la respuesta demuestra comprensión de principios fundamentales",
            "Considera la respuesta en términos de eficiencia y optimización",
            "Analiza la claridad y precisión en la comunicación de ideas complejas",
            "Evalúa la capacidad de conectar teoría con práctica",
            "Considera la profundidad del análisis y el nivel de detalle proporcionado",
            "Analiza cómo la respuesta aborda casos límite y excepciones",
            "Evalúa la estructura lógica y organización de la respuesta",
            "Considera la originalidad y creatividad en el enfoque",
            "Analiza la capacidad de síntesis y resumen de conceptos complejos",
            "Evalúa la precisión técnica y uso correcto de terminología",
            "Considera la aplicabilidad de la respuesta en diferentes escenarios",
            "Analiza la capacidad de identificar y resolver problemas potenciales",
            "Evalúa la integración de múltiples conceptos relacionados"
        );

        $indice = hexdec(substr($variation_id, 0, 2)) % count($contextos);
        return $contextos[$indice];
    }

    /**
     * Obtener enfoque específico de evaluación
     */
    private function get_evaluation_approach($variation_id) {
        $enfoques = array(
            "Enfócate en analizar la estructura lógica de la respuesta y cómo cada parte contribuye al todo",
            "Evalúa la precisión técnica y el uso correcto de conceptos específicos del dominio",
            "Analiza cómo la respuesta aborda la complejidad inherente al problema planteado",
            "Considera la capacidad del estudiante para identificar los aspectos más importantes",
            "Evalúa la claridad en la comunicación de ideas técnicas complejas",
            "Analiza la profundidad del entendimiento conceptual demostrado",
            "Considera cómo la respuesta podría aplicarse en escenarios del mundo real",
            "Evalúa la capacidad de síntesis y conexión entre diferentes conceptos",
            "Analiza la originalidad en el enfoque y solución propuesta",
            "Considera la eficiencia y optimización en la solución presentada",
            "Evalúa la capacidad de identificar limitaciones y casos especiales",
            "Analiza la coherencia interna y consistencia de la respuesta",
            "Considera el nivel de detalle apropiado para el contexto",
            "Evalúa la capacidad de abstracción y generalización de conceptos",
            "Analiza cómo la respuesta demuestra pensamiento crítico y analítico"
        );

        $indice = hexdec(substr($variation_id, 2, 2)) % count($enfoques);
        return $enfoques[$indice];
    }
    
    /**
     * Construir prompt para recomendaciones
     */
    private function build_recommendations_prompt($autoevaluacion_data) {
        $prompt = "Basándote en los siguientes datos de autoevaluación, genera recomendaciones personalizadas para el estudiante:\n\n";
        $prompt .= "Materia: {$autoevaluacion_data['materia']}\n";
        $prompt .= "Puntuación obtenida: {$autoevaluacion_data['puntuacion_obtenida']}/{$autoevaluacion_data['puntuacion_total']}\n";
        $prompt .= "Porcentaje: {$autoevaluacion_data['porcentaje']}%\n";
        $prompt .= "Modalidad: {$autoevaluacion_data['modalidad']}\n\n";
        
        if (!empty($autoevaluacion_data['preguntas_respuestas'])) {
            $prompt .= "Detalles de preguntas y respuestas:\n";
            foreach ($autoevaluacion_data['preguntas_respuestas'] as $pregunta) {
                // Normalizar acceso a datos (puede ser array u objeto)
                $pregunta_texto = is_array($pregunta) ? ($pregunta['pregunta'] ?? '') : ($pregunta->pregunta ?? '');
                $respuesta_estudiante = is_array($pregunta) ? ($pregunta['respuesta_estudiante'] ?? '') : ($pregunta->respuesta_estudiante ?? '');
                $puntuacion = is_array($pregunta) ? ($pregunta['puntuacion'] ?? 0) : ($pregunta->puntuacion ?? 0);

                $prompt .= "- Pregunta: {$pregunta_texto}\n";
                $prompt .= "  Respuesta: {$respuesta_estudiante}\n";
                $prompt .= "  Puntuación: {$puntuacion}\n\n";
            }
        }
        
        $prompt .= "Genera recomendaciones específicas y accionables para mejorar el rendimiento del estudiante.";
        
        return $prompt;
    }
    
    /**
     * Descargar PDF desde URL
     */
    private function download_pdf($pdf_url) {
        $response = wp_remote_get($pdf_url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('WPSA: Error descargando PDF: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        // Verificar que sea un PDF
        if (strpos($content_type, 'application/pdf') === false && strpos($body, '%PDF') !== 0) {
            error_log('WPSA: El archivo no parece ser un PDF válido');
            return false;
        }
        
        return $body;
    }
    
    /**
     * Realizar petición a la API de documentos de Gemini
     */
    private function make_document_api_request($pdf_base64, $prompt) {
        $url = $this->api_url . '?key=' . $this->api_key;
        
        $data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'inline_data' => array(
                                'mime_type' => 'application/pdf',
                                'data' => $pdf_base64
                            )
                        ),
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048,
            )
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($data),
            'timeout' => 60 // Aumentar timeout para documentos grandes
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('WPSA: Error en API de documentos: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        if (isset($decoded['error'])) {
            error_log('WPSA: Error de API de documentos: ' . $decoded['error']['message']);
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * Realizar petición a la API
     */
    private function make_api_request($prompt) {
        $url = $this->api_url . '?key=' . $this->api_key;
        
        error_log('🔍 WPSA Debug - URL de la API: ' . $url);
        error_log('🔍 WPSA Debug - API Key configurada: ' . (!empty($this->api_key) ? 'Sí' : 'No'));
        
        $data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7, // Reducir para mayor consistencia y adherencia al prompt
                'topK' => 50, // Aumentar para más diversidad
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            )
        );
        
        error_log('🔍 WPSA Debug - Datos enviados a la API: ' . print_r($data, true));
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($data),
            'timeout' => 30
        );
        
        error_log('🔍 WPSA Debug - Haciendo petición HTTP...');
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('❌ WPSA Debug - Error en petición HTTP: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        error_log('🔍 WPSA Debug - Código HTTP: ' . $http_code);
        error_log('🔍 WPSA Debug - Respuesta cruda: ' . $body);
        
        $decoded = json_decode($body, true);
        
        if (isset($decoded['error'])) {
            error_log('❌ WPSA Debug - Error de la API de Gemini: ' . $decoded['error']['message']);
            return false;
        }
        
        error_log('✅ WPSA Debug - Respuesta decodificada exitosamente');
        return $decoded;
    }
    
    /**
     * Parsear respuesta de pregunta
     */
    private function parse_question_response($text, $modalidad) {
        error_log('🔍 WPSA Debug - Iniciando parse_question_response con texto: ' . substr($text, 0, 200) . '...');
        
        $question_data = array(
            'pregunta' => '',
            'respuesta_correcta' => '',
            'puntuacion' => 10,
            'dificultad' => 'media'
        );
        
        // Extraer pregunta
        if (preg_match('/PREGUNTA:\s*(.+?)(?=RESPUESTA_CORRECTA:|$)/s', $text, $matches)) {
            $question_data['pregunta'] = trim($matches[1]);
            error_log('✅ WPSA Debug - Pregunta extraída: ' . substr($question_data['pregunta'], 0, 100) . '...');
        } else {
            error_log('❌ WPSA Debug - No se pudo extraer la pregunta del texto');
        }
        
        // Extraer respuesta correcta
        if (preg_match('/RESPUESTA_CORRECTA:\s*(.+?)(?=PUNTUACION:|$)/s', $text, $matches)) {
            $question_data['respuesta_correcta'] = trim($matches[1]);
            error_log('✅ WPSA Debug - Respuesta correcta extraída: ' . substr($question_data['respuesta_correcta'], 0, 100) . '...');
        } else {
            error_log('❌ WPSA Debug - No se pudo extraer la respuesta correcta del texto');
        }
        
        // Extraer puntuación
        if (preg_match('/PUNTUACION:\s*(\d+)/', $text, $matches)) {
            $question_data['puntuacion'] = intval($matches[1]);
            error_log('✅ WPSA Debug - Puntuación extraída: ' . $question_data['puntuacion']);
        } else {
            error_log('❌ WPSA Debug - No se pudo extraer la puntuación del texto');
        }
        
        // Extraer dificultad
        if (preg_match('/DIFICULTAD:\s*(baja|media|alta)/i', $text, $matches)) {
            $question_data['dificultad'] = strtolower($matches[1]);
            error_log('✅ WPSA Debug - Dificultad extraída: ' . $question_data['dificultad']);
        } else {
            error_log('❌ WPSA Debug - No se pudo extraer la dificultad del texto');
        }
        
        error_log('🔍 WPSA Debug - Resultado final del parsing: ' . print_r($question_data, true));
        
        // Verificar que al menos la pregunta se extrajo correctamente
        if (empty($question_data['pregunta'])) {
            error_log('❌ WPSA Debug - ERROR: No se pudo extraer una pregunta válida del texto de la IA');
            return false;
        }
        
        return $question_data;
    }
    
    /**
     * Parsear respuesta de evaluación
     */
    private function parse_evaluation_response($text) {
        $evaluation_data = array(
            'puntuacion' => 0,
            'feedback' => '',
            'recomendaciones' => ''
        );
        
        // Regex flexible para puntuación (variaciones comunes)
        $score_patterns = array(
            '/PUNTUACION[:\s]*(\d+)/i',
            '/SCORE[:\s]*(\d+)/i',
            '/PUNTUACI[ÓO]N[:\s]*(\d+)/i',
            '/CALIFICACI[ÓO]N[:\s]*(\d+)/i',
            '/PUNTAJE[:\s]*(\d+)/i',
            '/(\d+)\s*(?:puntos?|points?)/i'  // Captura números cerca de "puntos"
        );
        
        foreach ($score_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $evaluation_data['puntuacion'] = min(max(intval($matches[1]), 0), 10);
                break;
            }
        }
        
        // Si no se encuentra puntuación, usar fallback
        if ($evaluation_data['puntuacion'] == 0) {
            error_log('⚠️ WPSA Debug - No se pudo parsear puntuación, usando fallback');
            $evaluation_data = $this->generate_fallback_evaluation($text, $text); // Usar pregunta y respuesta como fallback
        }
        
        // Extraer feedback (flexible)
        if (preg_match('/(?:FEEDBACK|COMENTARIOS|COMENTARIO|AN[ÁA]LISIS)[:\s]*(.+?)(?=(?:RECOMENDACIONES|PUNTUACI[ÓO]N|SCORE)[:\s]|\Z)/is', $text, $matches)) {
            $evaluation_data['feedback'] = trim($matches[1]);
        } elseif (strpos($text, 'feedback') !== false || strpos($text, 'comentarios') !== false) {
            // Fallback: tomar texto después de la puntuación
            $parts = explode('PUNTUACION', $text);
            if (count($parts) > 1) {
                $evaluation_data['feedback'] = trim($parts[1]);
            }
        }
        
        // Extraer recomendaciones (flexible)
        if (preg_match('/(?:RECOMENDACIONES|SUGERENCIAS|MEJORAR|CONSEJOS)[:\s]*(.+)/is', $text, $matches)) {
            $evaluation_data['recomendaciones'] = trim($matches[1]);
        }
        
        // Validar puntuación final
        if ($evaluation_data['puntuacion'] < 0 || $evaluation_data['puntuacion'] > 10) {
            $evaluation_data['puntuacion'] = 5; // Default neutral si inválido
            error_log('⚠️ WPSA Debug - Puntuación fuera de rango, usando 5');
        }
        
        error_log('🔍 WPSA Debug - Evaluación parseada: ' . print_r($evaluation_data, true));
        
        return $evaluation_data;
    }
    
    /**
     * Extraer temas específicos del temario para generar preguntas más precisas
     */
    private function extract_specific_topics($temario, $nivel) {
        if (empty($temario)) {
            return array();
        }
        
        $topics = array();
        
        // Buscar patrones comunes en temarios
        $patterns = array(
            '/Unidad\s+\d+[:\-]\s*(.+?)(?:\n|$)/i',
            '/Módulo\s+\d+[:\-]\s*(.+?)(?:\n|$)/i',
            '/Tema\s+\d+[:\-]\s*(.+?)(?:\n|$)/i',
            '/\d+\.\s*(.+?)(?:\n|$)/',
            '/\*\s*(.+?)(?:\n|$)/',
            '/-\s*(.+?)(?:\n|$)/'
        );
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $temario, $matches);
            foreach ($matches[1] as $match) {
                $topic = trim($match);
                if (!empty($topic) && strlen($topic) > 5) {
                    $topics[] = $topic;
                }
            }
        }
        
        // Si no se encontraron temas con patrones, dividir por líneas
        if (empty($topics)) {
            $lines = explode("\n", $temario);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strlen($line) > 5 && !preg_match('/^(TEMARIO|CONTENIDO|PROGRAMA)/i', $line)) {
                    $topics[] = $line;
                }
            }
        }
        
        // Filtrar temas según el nivel
        $filtered_topics = array();
        foreach ($topics as $topic) {
            $topic_lower = strtolower($topic);
            
            // Para nivel inicial, buscar temas básicos
            if ($nivel === 'inicial') {
                if (preg_match('/(básico|introducción|fundamentos|conceptos|principios|inicial)/i', $topic_lower)) {
                    $filtered_topics[] = $topic;
                }
            }
            // Para nivel intermedio, buscar temas intermedios
            elseif ($nivel === 'intermedio') {
                if (preg_match('/(intermedio|aplicación|desarrollo|implementación|análisis)/i', $topic_lower)) {
                    $filtered_topics[] = $topic;
                }
            }
            // Para nivel avanzado, buscar temas avanzados
            elseif ($nivel === 'avanzado') {
                if (preg_match('/(avanzado|avanzado|complejo|optimización|arquitectura|patrones)/i', $topic_lower)) {
                    $filtered_topics[] = $topic;
                }
            }
        }
        
        // Si no se encontraron temas filtrados, usar todos los temas
        if (empty($filtered_topics)) {
            $filtered_topics = $topics;
        }
        
        return array_slice($filtered_topics, 0, 10); // Limitar a 10 temas
    }
}
