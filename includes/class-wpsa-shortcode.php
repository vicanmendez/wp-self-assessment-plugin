<?php
/**
 * Clase para manejo de shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPSA_Shortcode {
    
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
        add_shortcode('wpsa_autoevaluacion', array($this, 'autoevaluacion_shortcode'));
    }
    
    /**
     * Shortcode principal para autoevaluación
     */
    public function autoevaluacion_shortcode($atts) {
        $atts = shortcode_atts(array(
            'materia_id' => '',
            'tema' => '',
            'modalidad' => '',
            'user_id' => ''
        ), $atts);

        // Verificar que la API esté configurada
        $api_key = get_option('wpsa_gemini_api_key', '');
        if (empty($api_key)) {
            return '<div class="wpsa-error">' . __('El sistema de autoevaluación no está configurado correctamente.', 'wp-self-assessment') . '</div>';
        }

        // Preparar filtros para obtener materias
        $filters = array();

        // Si se especifica un user_id, filtrar por ese usuario
        if (!empty($atts['user_id'])) {
            $filters['user_id'] = intval($atts['user_id']);
        } elseif (!empty($atts['current_user'])) {
            // Si se solicita del usuario actual
            $current_user_id = get_current_user_id();
            if ($current_user_id > 0) {
                $filters['user_id'] = $current_user_id;
            }
        }

        // Obtener materias disponibles con filtros
        $materias = $this->database->get_materias($filters);
        if (empty($materias)) {
            return '<div class="wpsa-error">' . __('No hay materias disponibles para autoevaluación.', 'wp-self-assessment') . '</div>';
        }

        // Si se especifica una materia específica, filtrar
        if (!empty($atts['materia_id'])) {
            $materias = array_filter($materias, function($materia) use ($atts) {
                return $materia->id == $atts['materia_id'];
            });
        }
        
        ob_start();
        ?>
        <div id="wpsa-autoevaluacion-container" class="wpsa-container">
            <div class="wpsa-header">
                <h2><?php _e('Sistema de Autoevaluación', 'wp-self-assessment'); ?></h2>
                <p><?php _e('Selecciona una materia y comienza tu autoevaluación personalizada con IA', 'wp-self-assessment'); ?></p>
            </div>
            
            <!-- Paso 1: Selección de materia -->
            <div id="wpsa-step-1" class="wpsa-step active">
                <h3><?php _e('Paso 1: Selecciona una Materia', 'wp-self-assessment'); ?></h3>
                <div class="wpsa-materias-grid">
                    <?php foreach ($materias as $materia): ?>
                        <div class="wpsa-materia-card" data-materia-id="<?php echo esc_attr($materia->id); ?>" onclick="selectMateria(<?php echo esc_attr($materia->id); ?>, '<?php echo esc_js($materia->nombre); ?>', '<?php echo esc_js($materia->grado); ?>')">
                            <h4><?php echo esc_html($materia->nombre); ?></h4>
                            <p class="wpsa-grado"><?php echo esc_html($materia->grado); ?></p>
                            <?php if (!empty($materia->descripcion)): ?>
                                <p class="wpsa-descripcion"><?php echo esc_html(wp_trim_words($materia->descripcion, 20)); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Botón para continuar -->
                <div class="wpsa-step-actions" style="margin-top: 30px; text-align: center;">
                    <button type="button" id="wpsa-continue-step-1" class="button button-primary" onclick="continueToStep2()" style="display: none;">
                    <?php _e('Continuar', 'wp-self-assessment'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Paso 2: Configuración de la evaluación -->
            <div id="wpsa-step-2" class="wpsa-step">
                <h3><?php _e('Paso 2: Configura tu Evaluación', 'wp-self-assessment'); ?></h3>
                
                <div class="wpsa-form-group">
                    <label for="wpsa-estudiante-nombre"><?php _e('Tu Nombre (Opcional):', 'wp-self-assessment'); ?></label>
                    <input type="text" id="wpsa-estudiante-nombre" placeholder="<?php _e('Ingresa tu nombre para personalizar la experiencia', 'wp-self-assessment'); ?>" />
                </div>
                
                <div class="wpsa-form-group">
                    <label for="wpsa-tema"><?php _e('Tema Específico (Opcional):', 'wp-self-assessment'); ?></label>
                    <input type="text" id="wpsa-tema" placeholder="<?php _e('Deja vacío para evaluar todo el programa', 'wp-self-assessment'); ?>" value="<?php echo esc_attr($atts['tema']); ?>" />
                </div>
                
                <div class="wpsa-form-group">
                    <label><?php _e('Nivel de Dificultad:', 'wp-self-assessment'); ?></label>
                    <div class="wpsa-nivel-options">
                        <label class="wpsa-nivel-option">
                            <input type="radio" name="nivel" value="inicial" checked />
                            <div class="wpsa-nivel-card wpsa-nivel-inicial">
                                <h4><?php _e('Inicial', 'wp-self-assessment'); ?></h4>
                                <p><?php _e('Preguntas extremadamente fáciles, casi obvias. Ideal para principiantes.', 'wp-self-assessment'); ?></p>
                                <div class="wpsa-nivel-badge">🌱</div>
                            </div>
                        </label>
                        
                        <label class="wpsa-nivel-option">
                            <input type="radio" name="nivel" value="intermedio" />
                            <div class="wpsa-nivel-card wpsa-nivel-intermedio">
                                <h4><?php _e('Intermedio', 'wp-self-assessment'); ?></h4>
                                <p><?php _e('Preguntas que requieren comprensión sólida del tema. Nivel estándar de evaluación.', 'wp-self-assessment'); ?></p>
                                <div class="wpsa-nivel-badge">⚡</div>
                            </div>
                        </label>
                        
                        <label class="wpsa-nivel-option">
                            <input type="radio" name="nivel" value="avanzado" />
                            <div class="wpsa-nivel-card wpsa-nivel-avanzado">
                                <h4><?php _e('Avanzado', 'wp-self-assessment'); ?></h4>
                                <p><?php _e('Preguntas técnicas complejas. Ideal para pruebas técnicas de programación.', 'wp-self-assessment'); ?></p>
                                <div class="wpsa-nivel-badge">🚀</div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="wpsa-form-group">
                    <label><?php _e('Modalidad de Evaluación:', 'wp-self-assessment'); ?></label>
                    <div class="wpsa-modalidad-options">
                        <label class="wpsa-modalidad-option">
                            <input type="radio" name="modalidad" value="preguntas_simples" <?php checked($atts['modalidad'], 'preguntas_simples'); ?> />
                            <div class="wpsa-modalidad-card">
                                <h4><?php _e('Preguntas Simples', 'wp-self-assessment'); ?></h4>
                                <p><?php _e('Preguntas directas para reflexionar sobre conceptos clave', 'wp-self-assessment'); ?></p>
                            </div>
                        </label>
                        
                        <label class="wpsa-modalidad-option">
                            <input type="radio" name="modalidad" value="ejercicios" <?php checked($atts['modalidad'], 'ejercicios'); ?> />
                            <div class="wpsa-modalidad-card">
                                <h4><?php _e('Ejercicios Prácticos', 'wp-self-assessment'); ?></h4>
                                <p><?php _e('Problemas y ejercicios para resolver paso a paso', 'wp-self-assessment'); ?></p>
                            </div>
                        </label>
                        
                        <label class="wpsa-modalidad-option">
                            <input type="radio" name="modalidad" value="codigo" <?php checked($atts['modalidad'], 'codigo'); ?> />
                            <div class="wpsa-modalidad-card">
                                <h4><?php _e('Análisis de Situaciones, Problemas o Código', 'wp-self-assessment'); ?></h4>
                                <p><?php _e('Revisión y análisis de situaciones, problemas o fragmentos de código', 'wp-self-assessment'); ?></p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="wpsa-step-actions">
                    <button type="button" id="wpsa-back-step-1" class="button button-secondary" onclick="backToStep1()">
                        <?php _e('Atrás', 'wp-self-assessment'); ?>
                    </button>
                    <button type="button" id="wpsa-start-evaluation" class="button button-primary" onclick="startEvaluation()">
                        <?php _e('Comenzar Evaluación', 'wp-self-assessment'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Paso 3: Evaluación en progreso -->
            <div id="wpsa-step-3" class="wpsa-step">
                <h3><?php _e('Paso 3: Tu Evaluación', 'wp-self-assessment'); ?></h3>
                
                <div class="wpsa-evaluation-info">
                    <div class="wpsa-info-item">
                        <strong><?php _e('Materia:', 'wp-self-assessment'); ?></strong>
                        <span id="wpsa-current-materia"></span>
                    </div>
                    <div class="wpsa-info-item">
                        <strong><?php _e('Tema:', 'wp-self-assessment'); ?></strong>
                        <span id="wpsa-current-tema"></span>
                    </div>
                    <div class="wpsa-info-item">
                        <strong><?php _e('Nivel:', 'wp-self-assessment'); ?></strong>
                        <span id="wpsa-current-nivel"></span>
                    </div>
                    <div class="wpsa-info-item">
                        <strong><?php _e('Modalidad:', 'wp-self-assessment'); ?></strong>
                        <span id="wpsa-current-modalidad"></span>
                    </div>
                    <div class="wpsa-info-item">
                        <strong><?php _e('Pregunta:', 'wp-self-assessment'); ?></strong>
                        <span id="wpsa-question-counter">1</span>
                    </div>
                </div>
                
                <div class="wpsa-question-container">
                    <div class="wpsa-question">
                        <h4 id="wpsa-question-text"><?php _e('Cargando pregunta...', 'wp-self-assessment'); ?></h4>
                        <div id="wpsa-question-content"></div>
                    </div>
                    
                    <div class="wpsa-answer-container">
                        <label for="wpsa-answer"><?php _e('Tu Respuesta:', 'wp-self-assessment'); ?></label>
                        <textarea id="wpsa-answer" rows="6" placeholder="<?php _e('Escribe tu respuesta aquí...', 'wp-self-assessment'); ?>"></textarea>
                    </div>
                    
                    <div class="wpsa-question-actions">
                        <button type="button" id="wpsa-submit-answer" class="button button-primary" onclick="submitAnswer()">
                            <?php _e('Enviar Respuesta', 'wp-self-assessment'); ?>
                        </button>
                        <button type="button" id="wpsa-skip-question" class="button button-secondary" onclick="skipQuestion()">
                            <?php _e('Omitir Pregunta', 'wp-self-assessment'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="wpsa-evaluation-actions">
                    <button type="button" id="wpsa-next-question" class="button button-primary" style="display: none;" onclick="nextQuestion()">
                        <?php _e('Siguiente Pregunta', 'wp-self-assessment'); ?>
                    </button>
                    <button type="button" id="wpsa-finish-evaluation" class="button button-secondary" onclick="finishEvaluation()">
                        <?php _e('Finalizar Evaluación', 'wp-self-assessment'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Paso 4: Resultados -->
            <div id="wpsa-step-4" class="wpsa-step">
                <h3><?php _e('Resultados de tu Evaluación', 'wp-self-assessment'); ?></h3>
                
                <div class="wpsa-results-summary">
                    <div class="wpsa-score-card">
                        <h4><?php _e('Puntuación Final', 'wp-self-assessment'); ?></h4>
                        <div class="wpsa-score-display">
                            <span id="wpsa-final-score">0</span>
                            <span class="wpsa-score-total">/ <span id="wpsa-total-score">0</span></span>
                        </div>
                        <div class="wpsa-percentage">
                            <span id="wpsa-percentage">0</span>%
                        </div>
                    </div>

                    
                    <div class="wpsa-recommendations">
                        <h4><?php _e('Recomendaciones', 'wp-self-assessment'); ?></h4>
                        <div id="wpsa-recommendations-content">
                            <?php _e('Cargando recomendaciones...', 'wp-self-assessment'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="wpsa-results-actions">
                    <button type="button" id="wpsa-new-evaluation" class="button button-primary" onclick="newEvaluation()">
                        <?php _e('Nueva Evaluación', 'wp-self-assessment'); ?>
                    </button>
                    <button type="button" id="wpsa-download-results" class="button button-secondary" onclick="downloadResults()">
                        <?php _e('Descargar Resultados', 'wp-self-assessment'); ?>
                    </button>
                </div>
            </div>
            
            <!-- reCAPTCHA v3 (invisible) -->
            <div id="wpsa-recaptcha-container" class="wpsa-recaptcha" style="display: none;">
                <!-- reCAPTCHA v3 se carga automáticamente sin elementos visuales -->
            </div>
        </div>
        
        <script>
        // Variables globales
        let selectedMateriaData = null;
        let currentStep = 1;
        
        // Función para seleccionar materia
        function selectMateria(id, nombre, grado) {
            // Remover selección anterior
            document.querySelectorAll('.wpsa-materia-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Seleccionar nueva materia
            event.target.closest('.wpsa-materia-card').classList.add('selected');
            
            // Guardar datos
            selectedMateriaData = {
                id: id,
                nombre: nombre,
                grado: grado
            };
            
            // Mostrar botón continuar
            document.getElementById('wpsa-continue-step-1').style.display = 'inline-block';
        }
        
        // Función para continuar al paso 2
        function continueToStep2() {
            if (!selectedMateriaData) {
                alert('Por favor selecciona una materia');
                return;
            }
            
            // Ocultar paso 1 y mostrar paso 2
            document.getElementById('wpsa-step-1').classList.remove('active');
            document.getElementById('wpsa-step-2').classList.add('active');
            currentStep = 2;
        }
        
        // Función para volver al paso 1
        function backToStep1() {
            document.getElementById('wpsa-step-2').classList.remove('active');
            document.getElementById('wpsa-step-1').classList.add('active');
            currentStep = 1;
        }
        
        // Función para comenzar evaluación
        function startEvaluation() {
            if (!selectedMateriaData) {
                alert('Por favor selecciona una materia');
                return;
            }
            
            // Recopilar datos del formulario
            const estudianteNombre = document.getElementById('wpsa-estudiante-nombre').value;
            const tema = document.getElementById('wpsa-tema').value;
            const nivel = document.querySelector('input[name="nivel"]:checked').value;
            const modalidad = document.querySelector('input[name="modalidad"]:checked').value;
            
            if (!modalidad) {
                alert('Por favor selecciona una modalidad de evaluación');
                return;
            }
            
            if (!nivel) {
                alert('Por favor selecciona un nivel de dificultad');
                return;
            }
            
            // Actualizar información mostrada
            document.getElementById('wpsa-current-materia').textContent = selectedMateriaData.nombre;
            document.getElementById('wpsa-current-tema').textContent = tema || 'Todo el programa';
            document.getElementById('wpsa-current-nivel').textContent = getNivelName(nivel);
            document.getElementById('wpsa-current-modalidad').textContent = getModalidadName(modalidad);
            
            // Validar datos antes de inicializar
            if (!selectedMateriaData || !selectedMateriaData.id) {
                console.error('❌ Error: selectedMateriaData no está disponible o no tiene ID');
                alert('Error: No se pudo inicializar la evaluación. Por favor, recarga la página.');
                return;
            }

            console.log('📝 Inicializando evaluationData:', {
                materia_id: selectedMateriaData.id,
                materia_nombre: selectedMateriaData.nombre,
                estudiante: estudianteNombre,
                tema: tema,
                nivel: nivel,
                modalidad: modalidad
            });

            // Guardar datos globalmente
            window.evaluationData = {
                materia_id: selectedMateriaData.id,
                materia_nombre: selectedMateriaData.nombre,
                estudiante_nombre: estudianteNombre || 'Anónimo',
                tema: tema || 'General',
                nivel: nivel,
                modalidad: modalidad,
                currentQuestion: 1,
                responses: [], // Array para almacenar respuestas y puntuaciones
                questionData: {}, // Array asociativo para manejo robusto de datos
                askedQuestions: [], // Array para almacenar preguntas ya realizadas
                totalQuestions: 0,
                completedQuestions: 0,
                evaluation_id: null // Para almacenar el ID de evaluación de BD
            };

            console.log('✅ evaluationData inicializado correctamente:', window.evaluationData);
            
            // Ir al paso 3
            document.getElementById('wpsa-step-2').classList.remove('active');
            document.getElementById('wpsa-step-3').classList.add('active');
            currentStep = 3;
            
            // Generar primera pregunta
            generateQuestion();
        }
        
        // Función para generar pregunta (ahora asíncrona)
        async function generateQuestion() {
            showLoading();
            
            try {
                // Generar pregunta dinámica con IA
                const pregunta = await generateTestQuestion();
                hideLoading();
                displayQuestion(pregunta);
            } catch (error) {
                console.error('❌ Error generando pregunta:', error);
                hideLoading();
                // Mostrar error al usuario
                document.getElementById('wpsa-question-content').innerHTML = 
                    '<div class="wpsa-error">Error generando pregunta. Por favor, intenta nuevamente.</div>';
            }
        }
        
        // Función para generar pregunta dinámica usando IA
        function generateTestQuestion() {
            const materiaId = window.evaluationData.materia_id;
            const materiaNombre = selectedMateriaData.nombre;
            const nivel = window.evaluationData.nivel;
            const modalidad = window.evaluationData.modalidad;
            const tema = window.evaluationData.tema || '';
            const numero = window.evaluationData.currentQuestion;
            
            console.log('🎯 WPSA Debug - Datos del formulario para generar pregunta:', {
                materiaId: materiaId,
                materiaNombre: materiaNombre,
                nivel: nivel,
                modalidad: modalidad,
                tema: tema,
                numero: numero,
                evaluationData: window.evaluationData
            });
            
            // Generar pregunta usando IA real
            return generateDynamicQuestion(materiaId, materiaNombre, tema, modalidad, nivel, numero);
        }
        
        // Función para obtener datos de materia desde la BD
        function getMateriaDataFromBD(materiaId) {
            return new Promise((resolve, reject) => {
                const data = {
                    action: 'wpsa_get_materia_data',
                    nonce: '<?php echo wp_create_nonce("wpsa_nonce"); ?>',
                    materia_id: materiaId
                };
                
                console.log('📤 WPSA Debug - Obteniendo datos de materia desde BD:', data);
                
                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        console.log('✅ WPSA Debug - Datos de materia obtenidos:', result.data);
                        resolve(result.data);
                    } else {
                        console.error('❌ Error obteniendo datos de materia:', result.data);
                        reject(new Error(result.data || 'Error obteniendo datos de materia'));
                    }
                })
                .catch(error => {
                    console.error('❌ Error de conexión obteniendo datos de materia:', error);
                    reject(error);
                });
            });
        }
        
        // Función para generar pregunta dinámica con IA
        function generateDynamicQuestion(materiaId, materiaNombre, tema, modalidad, nivel, numero) {
            console.log('🔄 WPSA Debug - Iniciando generateDynamicQuestion con parámetros:', {
                materiaId, materiaNombre, tema, modalidad, nivel, numero
            });
            
            // Primero obtener datos completos de la materia desde la BD
            return getMateriaDataFromBD(materiaId)
                .then(materiaData => {
                    console.log('📚 WPSA Debug - Datos de materia obtenidos de BD:', materiaData);
                    
                    // Usar los datos reales de la BD para generar pregunta con IA
                    // Pasar TODOS los parámetros del formulario
                    return generateQuestionWithAI(materiaId, materiaData.nombre, tema, modalidad, nivel, numero)
                        .then(pregunta => {
                            if (pregunta && pregunta.pregunta) {
                                console.log('✅ Pregunta generada con IA usando datos de BD:', pregunta);
                                return pregunta;
                            } else {
                                console.log('⚠️ Fallback a preguntas específicas de la materia');
                                return generateFallbackQuestionWithBD(materiaId, materiaData, tema, modalidad, nivel, numero);
                            }
                        })
                        .catch(error => {
                            console.error('❌ Error generando pregunta con IA:', error);
                            console.log('⚠️ Fallback a preguntas específicas de la materia');
                            return generateFallbackQuestionWithBD(materiaId, materiaData, tema, modalidad, nivel, numero);
                        });
                })
                .catch(error => {
                    console.error('❌ Error obteniendo datos de materia desde BD:', error);
                    console.log('⚠️ Fallback a preguntas hardcodeadas genéricas');
                    return generateFallbackQuestion(materiaId, materiaNombre, tema, modalidad, nivel, numero);
                });
        }
        
        // Función para generar pregunta usando IA real
        function generateQuestionWithAI(materiaId, materiaNombre, tema, modalidad, nivel, numero) {
            return new Promise((resolve, reject) => {
                const data = {
                    action: 'wpsa_generate_dynamic_question',
                    nonce: '<?php echo wp_create_nonce("wpsa_nonce"); ?>',
                    materia_id: materiaId,
                    materia_nombre: materiaNombre,
                    tema: tema,
                    modalidad: modalidad,
                    nivel: nivel,
                    numero_pregunta: numero,
                    previous_questions: JSON.stringify(window.evaluationData ? window.evaluationData.askedQuestions : [])
                };

                // Mostrar datos que se envían desde el frontend
                console.log('📤 WPSA Debug - Datos enviados desde frontend:', data);
                console.log('📋 WPSA Debug - Resumen de parámetros:', {
                    'Materia ID': materiaId,
                    'Nombre Materia': materiaNombre,
                    'Tema Específico': tema || 'General',
                    'Modalidad': modalidad,
                    'Nivel': nivel,
                    'Número Pregunta': numero,
                    'Preguntas Anteriores': window.evaluationData ? window.evaluationData.askedQuestions.length : 0
                });

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(data)
                })
                .then(response => response.json())
                .then(result => {
                    console.log('🔍 WPSA Debug - Respuesta completa del servidor:', result);

                    if (result.success) {
                        // Mostrar datos de debug en consola si están disponibles
                        if (result.data && result.data.debug_data) {
                            console.log('🔍 WPSA Debug - Datos recibidos en generate_question():', result.data.debug_data);
                        }

                        // Mostrar datos del temario si están disponibles
                        if (result.data && result.data.temario_debug) {
                            console.log('📚 WPSA Debug - Datos del temario:', result.data.temario_debug);
                        }

                        // Mostrar el prompt completo si está disponible
                        if (result.data && result.data.debug_data && result.data.debug_data.prompt_completo) {
                            console.log('🤖 WPSA Debug - PROMPT COMPLETO ENVIADO A LA IA:');
                            console.log('================================================');
                            console.log(result.data.debug_data.prompt_completo);
                            console.log('================================================');
                            console.log('📏 Longitud del prompt:', result.data.debug_data.prompt_completo.length, 'caracteres');
                        }

                        // Verificar que la pregunta se generó correctamente
                        if (result.data && result.data.pregunta) {
                            console.log('✅ WPSA Debug - Pregunta generada exitosamente:', result.data.pregunta);
                        } else {
                            console.error('❌ WPSA Debug - No se generó pregunta válida:', result.data);
                        }

                        resolve(result.data);
                    } else {
                        console.error('❌ WPSA Debug - Error en respuesta del servidor:', result);
                        reject(new Error(result.data || 'Error generando pregunta'));
                    }
                })
                .catch(error => {
                    reject(error);
                });
            });
        }
        
        // Función de fallback usando datos reales de la BD
        function generateFallbackQuestionWithBD(materiaId, materiaData, tema, modalidad, nivel, numero) {
            const materiaNombre = materiaData.nombre;
            const temario = materiaData.temario || materiaData.temario_analizado || materiaData.descripcion || '';
            
            console.log('📚 WPSA Debug - Generando pregunta de fallback con datos de BD:', {
                materia: materiaNombre,
                temario_length: temario.length,
                tema: tema,
                modalidad: modalidad,
                nivel: nivel
            });
            
            // Generar pregunta basada en el temario real de la materia
            let pregunta = '';
            let respuesta_correcta = '';
            
            if (temario && temario.length > 50) {
                // Si hay temario, generar pregunta basada en él
                const temas = temario.split('\n').filter(t => t.trim().length > 10);
                const temaSeleccionado = temas[Math.floor(Math.random() * temas.length)] || temas[0] || '';
                
                if (temaSeleccionado) {
                    pregunta = `Basándote en el tema "${temaSeleccionado.trim()}" de ${materiaNombre}, explica los conceptos principales y su aplicación práctica.`;
                    respuesta_correcta = `Esta pregunta evalúa la comprensión de ${temaSeleccionado.trim()} en el contexto de ${materiaNombre}.`;
                } else {
                    pregunta = `Explica los conceptos fundamentales de ${materiaNombre} y su importancia en el campo de estudio.`;
                    respuesta_correcta = `Esta pregunta evalúa los conocimientos básicos de ${materiaNombre}.`;
                }
            } else {
                // Si no hay temario, generar pregunta genérica de la materia
                pregunta = `Describe los conceptos principales de ${materiaNombre} y explica su relevancia en el ámbito profesional.`;
                respuesta_correcta = `Esta pregunta evalúa la comprensión general de ${materiaNombre}.`;
            }
            
            return {
                pregunta: pregunta,
                respuesta_correcta: respuesta_correcta,
                puntuacion: 10,
                dificultad: nivel,
                fuente: 'BD - ' + materiaNombre
            };
        }
        
        // Función de fallback con preguntas hardcodeadas (mejoradas) - SOLO como último recurso
        function generateFallbackQuestion(materiaId, materiaNombre, tema, modalidad, nivel, numero) {
            const materiaNombreLower = materiaNombre.toLowerCase();
            
            // Preguntas específicas por materia y nivel
            const preguntasPorMateria = {
                'matematicas': {
                    'inicial': {
                        'preguntas_simples': [
                            '¿Qué es una suma?',
                            '¿Cuánto es 2 + 3?',
                            '¿Qué es un número par?',
                            '¿Cuántos lados tiene un triángulo?',
                            '¿Qué es la mitad de 10?'
                        ],
                        'ejercicios': [
                            'Calcula: 5 + 3 = ?',
                            'Resuelve: 8 - 2 = ?',
                            'Encuentra: 4 × 2 = ?',
                            'Divide: 12 ÷ 3 = ?',
                            'Suma: 1 + 2 + 3 = ?'
                        ],
                        'codigo': [
                            'Analiza este código simple:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>var resultado = 5 + 3;\nconsole.log(resultado);</code></pre><br>¿Qué hace el código y cuál será la salida? Explica paso a paso.',
                            'Explica qué hace este fragmento:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>console.log("Hola mundo");\nconsole.log("Bienvenido a la programación");</code></pre><br>¿Cuántas líneas se imprimirán y en qué orden?',
                            '¿Para qué sirve esta estructura condicional?<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>var edad = 20;\nif (edad >= 18) {\n    console.log("Eres mayor de edad");\n} else {\n    console.log("Eres menor de edad");\n}</code></pre><br>¿Qué se imprimirá y por qué?',
                            '¿Qué imprime este código?<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>alert(2 + 2);\nconsole.log("El resultado es: " + (2 + 2));</code></pre><br>Explica qué hace cada línea y cuál es la diferencia entre alert y console.log.',
                            'Explica qué es una variable:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>var nombre = "Juan";\nvar edad = 25;\nconsole.log("Hola " + nombre + ", tienes " + edad + " años");</code></pre><br>¿Qué se imprimirá y cómo funcionan las variables?'
                        ]
                    },
                    'intermedio': {
                        'preguntas_simples': [
                            '¿Qué es una ecuación?',
                            'Explica qué es el teorema de Pitágoras',
                            '¿Qué es una función matemática?',
                            'Define qué es un logaritmo',
                            '¿Qué es la derivada de una función?'
                        ],
                        'ejercicios': [
                            'Resuelve la ecuación: 2x + 5 = 13',
                            'Calcula el área de un círculo con radio 5',
                            'Encuentra la pendiente de la recta y = 2x + 3',
                            'Resuelve: log₂(8) = ?',
                            'Calcula la derivada de x²'
                        ],
                        'codigo': [
                            'Analiza esta función para calcular el área de un círculo:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>function calcularArea(radio) {\n    const pi = 3.14159;\n    const area = pi * radio * radio;\n    return area;\n}\n\nconsole.log(calcularArea(5));</code></pre><br>¿Qué hace la función y cuál será la salida? ¿Cómo mejorarías el código?',
                            'Explica este algoritmo para encontrar números primos:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>function esPrimo(numero) {\n    if (numero <= 1) return false;\n    for (let i = 2; i < numero; i++) {\n        if (numero % i === 0) return false;\n    }\n    return true;\n}\n\nconsole.log(esPrimo(17));</code></pre><br>¿Cómo funciona el algoritmo y cuál es su complejidad?',
                            'Analiza esta función para resolver ecuaciones cuadráticas:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>function resolverCuadratica(a, b, c) {\n    const discriminante = b * b - 4 * a * c;\n    if (discriminante < 0) return "Sin solución real";\n    \n    const x1 = (-b + Math.sqrt(discriminante)) / (2 * a);\n    const x2 = (-b - Math.sqrt(discriminante)) / (2 * a);\n    return { x1, x2 };\n}\n\nconsole.log(resolverCuadratica(1, -5, 6));</code></pre><br>¿Qué hace la función y cuál será el resultado?',
                            'Explica este código para calcular factoriales:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>function factorial(n) {\n    if (n <= 1) return 1;\n    return n * factorial(n - 1);\n}\n\nconsole.log(factorial(5));</code></pre><br>¿Cómo funciona la recursión y cuál es el resultado?',
                            'Analiza este algoritmo de ordenamiento:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>function ordenarBurbuja(arreglo) {\n    for (let i = 0; i < arreglo.length; i++) {\n        for (let j = 0; j < arreglo.length - 1; j++) {\n            if (arreglo[j] > arreglo[j + 1]) {\n                [arreglo[j], arreglo[j + 1]] = [arreglo[j + 1], arreglo[j]];\n            }\n        }\n    }\n    return arreglo;\n}\n\nconsole.log(ordenarBurbuja([64, 34, 25, 12, 22]));</code></pre><br>¿Cómo funciona el algoritmo y cuál es su eficiencia?'
                        ]
                    },
                    'avanzado': {
                        'preguntas_simples': [
                            'Explica la teoría de conjuntos',
                            '¿Qué es el cálculo diferencial?',
                            'Define espacios vectoriales',
                            'Explica la teoría de probabilidades',
                            '¿Qué es la topología matemática?'
                        ],
                        'ejercicios': [
                            'Resuelve la integral ∫x²dx',
                            'Calcula el determinante de una matriz 3x3',
                            'Encuentra los valores propios de una matriz',
                            'Resuelve un sistema de ecuaciones diferenciales',
                            'Calcula la transformada de Fourier'
                        ],
                        'codigo': [
                            'Analiza esta implementación del algoritmo de ordenamiento rápido:\n\n```javascript\nfunction quickSort(arr) {\n    if (arr.length <= 1) return arr;\n    \n    const pivot = arr[Math.floor(arr.length / 2)];\n    const left = arr.filter(x => x < pivot);\n    const middle = arr.filter(x => x === pivot);\n    const right = arr.filter(x => x > pivot);\n    \n    return [...quickSort(left), ...middle, ...quickSort(right)];\n}\n\nconsole.log(quickSort([64, 34, 25, 12, 22, 11, 90]));\n```\n\n¿Cómo funciona el algoritmo y cuál es su complejidad temporal?',
                            'Explica esta función para resolver sistemas lineales:\n\n```javascript\nfunction resolverSistema(matriz, vector) {\n    const n = matriz.length;\n    for (let i = 0; i < n; i++) {\n        let maxRow = i;\n        for (let k = i + 1; k < n; k++) {\n            if (Math.abs(matriz[k][i]) > Math.abs(matriz[maxRow][i])) {\n                maxRow = k;\n            }\n        }\n        [matriz[i], matriz[maxRow]] = [matriz[maxRow], matriz[i]];\n        [vector[i], vector[maxRow]] = [vector[maxRow], vector[i]];\n        \n        for (let k = i + 1; k < n; k++) {\n            const factor = matriz[k][i] / matriz[i][i];\n            for (let j = i; j < n; j++) {\n                matriz[k][j] -= factor * matriz[i][j];\n            }\n            vector[k] -= factor * vector[i];\n        }\n    }\n    \n    const solucion = new Array(n);\n    for (let i = n - 1; i >= 0; i--) {\n        solucion[i] = vector[i];\n        for (let j = i + 1; j < n; j++) {\n            solucion[i] -= matriz[i][j] * solucion[j];\n        }\n        solucion[i] /= matriz[i][i];\n    }\n    return solucion;\n}\n```\n\n¿Qué método numérico implementa y cómo funciona?',
                            'Analiza este código para métodos numéricos:\n\n```javascript\nfunction newtonRaphson(f, df, x0, tolerancia = 1e-6, maxIter = 100) {\n    let x = x0;\n    for (let i = 0; i < maxIter; i++) {\n        const fx = f(x);\n        const dfx = df(x);\n        \n        if (Math.abs(dfx) < 1e-12) {\n            throw new Error("Derivada muy pequeña");\n        }\n        \n        const xNuevo = x - fx / dfx;\n        \n        if (Math.abs(xNuevo - x) < tolerancia) {\n            return xNuevo;\n        }\n        \n        x = xNuevo;\n    }\n    throw new Error("No convergió");\n}\n\nconst f = x => x * x - 2;\nconst df = x => 2 * x;\nconsole.log(newtonRaphson(f, df, 1));\n```\n\n¿Qué método implementa y cuál es su propósito?',
                            'Explica este código para análisis estadístico:\n\n```javascript\nfunction analisisEstadistico(datos) {\n    const n = datos.length;\n    const media = datos.reduce((sum, x) => sum + x, 0) / n;\n    \n    const varianza = datos.reduce((sum, x) => sum + Math.pow(x - media, 2), 0) / n;\n    const desviacionEstandar = Math.sqrt(varianza);\n    \n    const datosOrdenados = [...datos].sort((a, b) => a - b);\n    const mediana = n % 2 === 0 \n        ? (datosOrdenados[n/2 - 1] + datosOrdenados[n/2]) / 2\n        : datosOrdenados[Math.floor(n/2)];\n    \n    return {\n        media,\n        mediana,\n        varianza,\n        desviacionEstandar,\n        rango: Math.max(...datos) - Math.min(...datos)\n    };\n}\n\nconsole.log(analisisEstadistico([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]));\n```\n\n¿Qué medidas estadísticas calcula y cómo se interpretan?',
                            'Analiza este algoritmo de optimización (gradiente descendente):\n\n```javascript\nfunction gradienteDescendente(f, gradiente, x0, alpha = 0.01, maxIter = 1000) {\n    let x = [...x0];\n    const historial = [];\n    \n    for (let i = 0; i < maxIter; i++) {\n        const grad = gradiente(x);\n        const magnitudGrad = Math.sqrt(grad.reduce((sum, g) => sum + g * g, 0));\n        \n        if (magnitudGrad < 1e-6) break;\n        \n        for (let j = 0; j < x.length; j++) {\n            x[j] -= alpha * grad[j];\n        }\n        \n        historial.push({ iteracion: i, x: [...x], valor: f(x) });\n    }\n    \n    return { solucion: x, historial };\n}\n\nconst f = (x) => x[0] * x[0] + x[1] * x[1];\nconst gradiente = (x) => [2 * x[0], 2 * x[1]];\nconsole.log(gradienteDescendente(f, gradiente, [3, 4]));\n```\n\n¿Cómo funciona el algoritmo y cuál es su objetivo?'
                        ]
                    }
                },
                'programacion': {
                    'inicial': {
                        'preguntas_simples': [
                            '¿Qué es una variable?',
                            '¿Qué es un bucle?',
                            '¿Qué es una función?',
                            '¿Qué es un condicional?',
                            '¿Qué es un array?'
                        ],
                        'ejercicios': [
                            'Escribe un programa que sume dos números',
                            'Crea un bucle que imprima números del 1 al 5',
                            'Escribe una función que calcule el área de un rectángulo',
                            'Crea un programa que determine si un número es par',
                            'Escribe código para encontrar el mayor de dos números'
                        ],
                        'codigo': [
                            'Analiza este bucle for:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>for(var i = 1; i <= 3; i++) {\n    console.log("Número: " + i);\n}</code></pre><br>¿Qué imprime el código y cuántas veces se ejecuta el bucle? Explica paso a paso.',
                            'Explica esta estructura condicional:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>var edad = 20;\nif (edad >= 18) {\n    console.log("Eres mayor de edad");\n} else {\n    console.log("Eres menor de edad");\n}</code></pre><br>¿Qué se imprimirá y por qué? ¿Qué pasa si cambias la edad a 16?',
                            '¿Qué es este arreglo y cómo se usa?<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>var numeros = [1, 2, 3, 4, 5];\nconsole.log("Primer elemento:", numeros[0]);\nconsole.log("Último elemento:", numeros[4]);\nconsole.log("Cantidad de elementos:", numeros.length);</code></pre><br>Explica qué es un arreglo y cómo acceder a sus elementos.',
                            'Explica esta función:<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>function suma(a, b) {\n    return a + b;\n}\n\nvar resultado = suma(5, 3);\nconsole.log("La suma es:", resultado);</code></pre><br>¿Qué hace la función y cuál será la salida? ¿Cómo se llama una función?',
                            '¿Qué imprime este código?<br><br><pre style="background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;"><code>var nombre = "María";\nconsole.log("Hola " + nombre);\nconsole.log("Bienvenida, " + nombre + "!");</code></pre><br>Explica cómo funciona la concatenación de strings y cuál será la salida.'
                        ]
                    },
                    'intermedio': {
                        'preguntas_simples': [
                            '¿Qué es la programación orientada a objetos?',
                            'Explica qué es la recursión',
                            '¿Qué son las estructuras de datos?',
                            'Define qué es un algoritmo',
                            '¿Qué es la complejidad algorítmica?'
                        ],
                        'ejercicios': [
                            'Implementa una clase para manejar estudiantes',
                            'Crea una función recursiva para calcular factoriales',
                            'Implementa una pila (stack) usando arrays',
                            'Escribe un algoritmo de búsqueda binaria',
                            'Crea una función para ordenar una lista'
                        ],
                        'codigo': [
                            'Optimiza este código para mejor rendimiento',
                            'Refactoriza esta función para mejor legibilidad',
                            'Identifica y corrige errores en este código',
                            'Implementa manejo de errores en esta función',
                            'Convierte este código a programación orientada a objetos'
                        ]
                    },
                    'avanzado': {
                        'preguntas_simples': [
                            'Explica patrones de diseño en programación',
                            '¿Qué es la programación funcional?',
                            'Define arquitectura de software',
                            'Explica conceptos de concurrencia',
                            '¿Qué es el testing automatizado?'
                        ],
                        'ejercicios': [
                            'Implementa el patrón Singleton',
                            'Crea un sistema de microservicios',
                            'Implementa programación asíncrona',
                            'Diseña una base de datos relacional',
                            'Crea un sistema de autenticación seguro'
                        ],
                        'codigo': [
                            'Implementa un patrón Observer',
                            'Crea un sistema de caché distribuido',
                            'Implementa algoritmos de machine learning',
                            'Diseña un sistema de logging robusto',
                            'Crea una API RESTful completa'
                        ]
                    }
                },
                'fisica': {
                    'inicial': {
                        'preguntas_simples': [
                            '¿Qué es la gravedad?',
                            '¿Qué es la velocidad?',
                            '¿Qué es la masa?',
                            '¿Qué es la energía?',
                            '¿Qué es la fuerza?'
                        ],
                        'ejercicios': [
                            'Calcula la velocidad: distancia = 100m, tiempo = 10s',
                            'Encuentra la fuerza: masa = 5kg, aceleración = 2m/s²',
                            'Resuelve: ¿Cuál es la energía cinética de un objeto de 2kg moviéndose a 5m/s?',
                            'Calcula: Si un objeto cae desde 20m, ¿cuánto tiempo tarda?',
                            'Encuentra la presión: fuerza = 100N, área = 2m²'
                        ],
                        'codigo': [
                            '¿Qué hace este código? velocidad = distancia / tiempo',
                            'Explica: if (masa > 0) { aceleracion = fuerza / masa }',
                            '¿Para qué sirve: energia = 0.5 * masa * velocidad * velocidad?',
                            '¿Qué calcula: presion = fuerza / area?',
                            'Explica: tiempo = Math.sqrt(2 * altura / gravedad)'
                        ]
                    },
                    'intermedio': {
                        'preguntas_simples': [
                            '¿Qué es la segunda ley de Newton?',
                            'Explica qué es la conservación de la energía',
                            '¿Qué es el momento angular?',
                            'Define qué es la termodinámica',
                            '¿Qué es la mecánica cuántica?'
                        ],
                        'ejercicios': [
                            'Resuelve el problema de caída libre con resistencia del aire',
                            'Calcula el momento de inercia de una esfera',
                            'Encuentra la frecuencia de un péndulo simple',
                            'Resuelve el problema de colisiones elásticas',
                            'Calcula la eficiencia de una máquina térmica'
                        ],
                        'codigo': [
                            'Implementa una simulación de movimiento parabólico',
                            'Crea un algoritmo para resolver ecuaciones de movimiento',
                            'Escribe código para simular ondas',
                            'Implementa cálculos de mecánica de fluidos',
                            'Crea una simulación de campo electromagnético'
                        ]
                    },
                    'avanzado': {
                        'preguntas_simples': [
                            'Explica la teoría de la relatividad especial',
                            '¿Qué es la mecánica cuántica relativista?',
                            'Define la teoría de campos cuánticos',
                            'Explica la cosmología moderna',
                            '¿Qué es la física de partículas?'
                        ],
                        'ejercicios': [
                            'Resuelve la ecuación de Schrödinger para el átomo de hidrógeno',
                            'Calcula la métrica de Schwarzschild',
                            'Encuentra la función de onda para un oscilador armónico cuántico',
                            'Resuelve las ecuaciones de Maxwell en el vacío',
                            'Calcula la sección eficaz de dispersión'
                        ],
                        'codigo': [
                            'Implementa algoritmos de Monte Carlo para física estadística',
                            'Crea simulaciones de dinámica molecular',
                            'Implementa métodos numéricos para ecuaciones diferenciales parciales',
                            'Escribe código para análisis de datos de física de partículas',
                            'Crea simulaciones de sistemas cuánticos'
                        ]
                    }
                },
                'quimica': {
                    'inicial': {
                        'preguntas_simples': [
                            '¿Qué es un átomo?',
                            '¿Qué es una molécula?',
                            '¿Qué es un elemento químico?',
                            '¿Qué es una reacción química?',
                            '¿Qué es la tabla periódica?'
                        ],
                        'ejercicios': [
                            'Balancea la ecuación: H₂ + O₂ → H₂O',
                            'Calcula la masa molar del CO₂',
                            'Encuentra el número de moles en 44g de CO₂',
                            'Resuelve: ¿Cuál es la concentración de 2 moles en 1 litro?',
                            'Calcula el pH de una solución con [H⁺] = 1×10⁻³ M'
                        ],
                        'codigo': [
                            '¿Qué hace este código? masa_molar = sum(atomic_masses)',
                            'Explica: if (pH < 7) { tipo = "acido" }',
                            '¿Para qué sirve: moles = masa / masa_molar?',
                            '¿Qué calcula: concentracion = moles / volumen?',
                            'Explica: balance = count_atoms(reactivos) == count_atoms(productos)'
                        ]
                    },
                    'intermedio': {
                        'preguntas_simples': [
                            '¿Qué es la termodinámica química?',
                            'Explica qué es la cinética química',
                            '¿Qué es el equilibrio químico?',
                            'Define qué es la electroquímica',
                            '¿Qué es la química orgánica?'
                        ],
                        'ejercicios': [
                            'Calcula la constante de equilibrio para una reacción',
                            'Resuelve problemas de titulación ácido-base',
                            'Encuentra la velocidad de reacción usando la ley de Arrhenius',
                            'Calcula el potencial de celda electroquímica',
                            'Resuelve problemas de estereoquímica'
                        ],
                        'codigo': [
                            'Implementa algoritmos para balancear ecuaciones químicas',
                            'Crea simulaciones de reacciones químicas',
                            'Escribe código para análisis espectroscópico',
                            'Implementa cálculos de termodinámica química',
                            'Crea modelos de cinética química'
                        ]
                    },
                    'avanzado': {
                        'preguntas_simples': [
                            'Explica la mecánica cuántica en química',
                            '¿Qué es la química computacional?',
                            'Define la catálisis heterogénea',
                            'Explica la química supramolecular',
                            '¿Qué es la química verde?'
                        ],
                        'ejercicios': [
                            'Resuelve la ecuación de Schrödinger para moléculas',
                            'Calcula superficies de energía potencial',
                            'Implementa métodos de Hartree-Fock',
                            'Resuelve problemas de catálisis enzimática',
                            'Calcula propiedades termodinámicas de materiales'
                        ],
                        'codigo': [
                            'Implementa algoritmos de química cuántica',
                            'Crea simulaciones de dinámica molecular',
                            'Escribe código para diseño de fármacos',
                            'Implementa métodos de química computacional',
                            'Crea modelos de catálisis computacional'
                        ]
                    }
                }
            };
            
            // Obtener preguntas específicas basadas en el nombre de la materia
            let preguntasArray = [];
            
            // Debug: mostrar información de la materia
            console.log('Materia ID:', materiaId);
            console.log('Materia Nombre:', materiaNombre);
            console.log('Nivel:', nivel);
            console.log('Modalidad:', modalidad);
            console.log('Tema:', tema);
            
            // Buscar por nombre de materia (case insensitive)
            let materiaKey = null;
            for (let key in preguntasPorMateria) {
                if (materiaNombre.includes(key) || key.includes(materiaNombre)) {
                    materiaKey = key;
                    break;
                }
            }
            
            // Si no encuentra coincidencia exacta, buscar por palabras clave
            if (!materiaKey) {
                const nombreLower = materiaNombre.toLowerCase();
                if (nombreLower.includes('matem') || nombreLower.includes('algebra') || nombreLower.includes('calculo') || nombreLower.includes('aritmetica')) {
                    materiaKey = 'matematicas';
                } else if (nombreLower.includes('program') || nombreLower.includes('codigo') || nombreLower.includes('software') || nombreLower.includes('informatica') || nombreLower.includes('computacion') || nombreLower.includes('sistemas')) {
                    materiaKey = 'programacion';
                } else if (nombreLower.includes('fisica') || nombreLower.includes('mecanica') || nombreLower.includes('termodinamica')) {
                    materiaKey = 'fisica';
                } else if (nombreLower.includes('quimica') || nombreLower.includes('quimic') || nombreLower.includes('organica') || nombreLower.includes('inorganica')) {
                    materiaKey = 'quimica';
                } else {
                    // Si no encuentra ninguna coincidencia, usar matemáticas como fallback
                    materiaKey = 'matematicas';
                }
            }
            
            // Determinar el lenguaje de programación basado en el tema y materia
            let programmingLanguage = 'javascript';
            if (materiaKey === 'programacion') {
                const tema = window.evaluationData.tema || '';
                const materiaNombre = selectedMateriaData.nombre || '';
                
                // Buscar en el tema y nombre de la materia
                const searchText = (tema + ' ' + materiaNombre).toLowerCase();
                
                if (searchText.includes('java') || searchText.includes('programación 1') || searchText.includes('programacion 1')) {
                    programmingLanguage = 'java';
                } else if (searchText.includes('python')) {
                    programmingLanguage = 'python';
                } else if (searchText.includes('c++') || searchText.includes('cpp') || searchText.includes('c plus plus')) {
                    programmingLanguage = 'cpp';
                } else if (searchText.includes('javascript') || searchText.includes('js')) {
                    programmingLanguage = 'javascript';
                } else if (searchText.includes('php')) {
                    programmingLanguage = 'php';
                } else if (searchText.includes('c#') || searchText.includes('csharp')) {
                    programmingLanguage = 'csharp';
                }
                
                console.log('Lenguaje detectado:', programmingLanguage, 'para tema:', tema, 'materia:', materiaNombre);
            }
            
            console.log('Materia Key detectada:', materiaKey);
            
            if (materiaKey && preguntasPorMateria[materiaKey] && preguntasPorMateria[materiaKey][nivel] && preguntasPorMateria[materiaKey][nivel][modalidad]) {
                preguntasArray = preguntasPorMateria[materiaKey][nivel][modalidad];
            } else {
                // Preguntas genéricas como fallback
                preguntasArray = [
                    `Pregunta ${numero} sobre ${tema} en ${materiaNombre}`,
                    `Explica brevemente ${tema} en el contexto de ${materiaNombre}`,
                    `¿Qué sabes sobre ${tema} en ${materiaNombre}?`,
                    `Describe ${tema} aplicado a ${materiaNombre}`,
                    `¿Cómo funciona ${tema} en ${materiaNombre}?`
                ];
            }
            
            // Respuestas específicas por nivel
            const respuestasPorNivel = {
                'inicial': [
                    'Es un concepto básico que todos deberían conocer.',
                    'Es fundamental para entender temas más avanzados.',
                    'Es la base de todo el conocimiento en esta área.',
                    'Es importante para el desarrollo de habilidades.',
                    'Es esencial para el aprendizaje continuo.'
                ],
                'intermedio': [
                    'Requiere comprensión de conceptos previos y aplicación práctica.',
                    'Involucra análisis y síntesis de información.',
                    'Necesita práctica constante para dominarse.',
                    'Combina teoría y aplicación en situaciones reales.',
                    'Es un paso intermedio hacia conocimientos avanzados.'
                ],
                'avanzado': [
                    'Requiere dominio profundo y experiencia práctica.',
                    'Involucra conceptos complejos y análisis crítico.',
                    'Necesita años de estudio y práctica especializada.',
                    'Combina múltiples disciplinas y enfoques.',
                    'Es el nivel más alto de competencia en el área.'
                ]
            };
            
            const index = (numero - 1) % preguntasArray.length;
            const respuestasArray = respuestasPorNivel[nivel] || respuestasPorNivel['intermedio'];
            const respuestaIndex = (numero - 1) % respuestasArray.length;
            
            // Obtener pregunta y reemplazar lenguaje si es necesario
            let pregunta = preguntasArray[index];
            
            // Reemplazar el lenguaje de programación si es necesario
            if (materiaKey === 'programacion' && modalidad === 'codigo') {
                if (programmingLanguage === 'java') {
                    pregunta = pregunta.replace(/javascript/g, 'Java');
                    pregunta = pregunta.replace(/console\.log/g, 'System.out.println');
                    pregunta = pregunta.replace(/var /g, 'int ');
                    pregunta = pregunta.replace(/let /g, 'int ');
                    pregunta = pregunta.replace(/const /g, 'final int ');
                } else if (programmingLanguage === 'python') {
                    pregunta = pregunta.replace(/javascript/g, 'Python');
                    pregunta = pregunta.replace(/console\.log/g, 'print');
                    pregunta = pregunta.replace(/var /g, '');
                    pregunta = pregunta.replace(/let /g, '');
                    pregunta = pregunta.replace(/const /g, '');
                } else if (programmingLanguage === 'cpp') {
                    pregunta = pregunta.replace(/javascript/g, 'C++');
                    pregunta = pregunta.replace(/console\.log/g, 'cout <<');
                    pregunta = pregunta.replace(/var /g, 'int ');
                    pregunta = pregunta.replace(/let /g, 'int ');
                    pregunta = pregunta.replace(/const /g, 'const int ');
                } else if (programmingLanguage === 'php') {
                    pregunta = pregunta.replace(/javascript/g, 'PHP');
                    pregunta = pregunta.replace(/console\.log/g, 'echo');
                    pregunta = pregunta.replace(/var /g, '$');
                    pregunta = pregunta.replace(/let /g, '$');
                    pregunta = pregunta.replace(/const /g, 'const ');
                } else if (programmingLanguage === 'csharp') {
                    pregunta = pregunta.replace(/javascript/g, 'C#');
                    pregunta = pregunta.replace(/console\.log/g, 'Console.WriteLine');
                    pregunta = pregunta.replace(/var /g, 'int ');
                    pregunta = pregunta.replace(/let /g, 'int ');
                    pregunta = pregunta.replace(/const /g, 'const int ');
                }
            }
            
            return {
                pregunta: pregunta,
                respuesta_correcta: respuestasArray[respuestaIndex],
                puntuacion: 10,
                dificultad: nivel
            };
        }
        
        // Función para convertir Markdown a HTML
        function formatMarkdownToHtml(text) {
            if (!text) return '';
            
            let html = text;
            
            // Primero, procesar bloques de código para evitar conflictos
            const codeBlocks = [];
            html = html.replace(/```(\w+)?\s*\n?([\s\S]*?)```/g, function(match, language, code) {
                const lang = language || 'text';
                const cleanCode = code.trim();
                const placeholder = `__CODE_BLOCK_${codeBlocks.length}__`;
                codeBlocks.push(`<pre class="wpsa-code-block"><code class="language-${lang}">${escapeHtml(cleanCode)}</code></pre>`);
                return placeholder;
            });
            
            // Convertir código inline (`code`)
            html = html.replace(/`([^`\n]+)`/g, '<code class="wpsa-inline-code">$1</code>');
            
            // Convertir texto en negrita (**texto** o __texto__)
            html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/__([^_\n]+)__/g, '<strong>$1</strong>');
            
            // Convertir texto en cursiva (*texto* o _texto_) - versión simple
            html = html.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
            html = html.replace(/\b_([^_\n]+)_\b/g, '<em>$1</em>');
            
            // Convertir listas con viñetas (- item)
            html = html.replace(/^- (.+)$/gm, '<li>$1</li>');
            html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
            
            // Convertir listas numeradas (1. item)
            html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
            html = html.replace(/(<li>.*<\/li>)/s, '<ol>$1</ol>');
            
            // Convertir saltos de línea
            html = html.replace(/\n/g, '<br>');
            
            // Restaurar bloques de código
            codeBlocks.forEach((block, index) => {
                html = html.replace(`__CODE_BLOCK_${index}__`, block);
            });
            
            return html;
        }
        
        // Función para escapar caracteres HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Función para mostrar pregunta
        function displayQuestion(questionData) {
            // Almacenar la pregunta en la lista de preguntas realizadas
            if (window.evaluationData && questionData && questionData.pregunta) {
                // Verificar si la pregunta ya existe para evitar duplicados
                const existingIndex = window.evaluationData.askedQuestions.findIndex(q =>
                    q.pregunta === questionData.pregunta
                );

                if (existingIndex === -1) {
                    window.evaluationData.askedQuestions.push({
                        pregunta: questionData.pregunta,
                        numero: window.evaluationData.currentQuestion
                    });
                }

                console.log('Preguntas almacenadas en askedQuestions:', window.evaluationData.askedQuestions);
                console.log('Total de preguntas previas:', window.evaluationData.askedQuestions.length);
            }

            document.getElementById('wpsa-question-text').textContent = 'Pregunta ' + window.evaluationData.currentQuestion;
            document.getElementById('wpsa-question-content').innerHTML = formatMarkdownToHtml(questionData.pregunta);
            document.getElementById('wpsa-answer').value = '';

            // Mostrar botones de acción
            document.getElementById('wpsa-submit-answer').style.display = 'inline-block';
            document.getElementById('wpsa-skip-question').style.display = 'inline-block';
            document.getElementById('wpsa-next-question').style.display = 'none';

            // Guardar datos de la pregunta
            window.currentQuestionData = questionData;
        }
        
        // Función para enviar respuesta
        function submitAnswer() {
            const respuesta = document.getElementById('wpsa-answer').value.trim();

            if (!respuesta) {
                alert('Por favor escribe una respuesta antes de continuar');
                return;
            }

            showLoading();

            // Evaluar respuesta usando IA (solo backend, sin evaluación local)
            evaluateAnswerWithAI(respuesta);
        }

        // Función para evaluar respuesta usando IA
        function evaluateAnswerWithAI(respuesta) {
            const data = {
                action: 'wpsa_evaluate_answer',
                nonce: '<?php echo wp_create_nonce("wpsa_nonce"); ?>',
                pregunta: document.getElementById('wpsa-question-text').textContent,
                respuesta: respuesta,
                respuesta_correcta: window.currentQuestionData ? window.currentQuestionData.respuesta_correcta : ''
            };

            console.log('🤖 Enviando respuesta a IA para evaluación:', data);

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(result => {
                hideLoading();

                if (result.success) {
                    console.log('✅ Respuesta evaluada por IA:', result.data);

                    // Guardar datos en el array asociativo usando el número de pregunta actual
                    const questionNumber = window.evaluationData.currentQuestion;
                    const questionText = window.currentQuestionData ? window.currentQuestionData.pregunta : document.getElementById('wpsa-question-text').textContent;
                    const correctAnswer = window.currentQuestionData ? window.currentQuestionData.respuesta_correcta : '';

                    console.log('💾 Guardando datos de pregunta:', {
                        questionNumber,
                        question: questionText,
                        answer: respuesta,
                        correctAnswer: correctAnswer,
                        score: result.data.puntuacion,
                        maxScore: 10,
                        feedback: result.data.feedback
                    });

                    // Asegurar que questionData existe
                    if (!window.evaluationData.questionData) {
                        window.evaluationData.questionData = {};
                        console.log('📝 Inicializando questionData');
                    }

                    // Guardar en questionData
                    window.evaluationData.questionData[questionNumber] = {
                        question: questionText,
                        answer: respuesta,
                        correctAnswer: correctAnswer,
                        score: parseInt(result.data.puntuacion) || 0,
                        maxScore: 10,
                        feedback: result.data.feedback || '',
                        timestamp: new Date().toISOString()
                    };

                    // Guardar también en responses para compatibilidad
                    if (!window.evaluationData.responses) {
                        window.evaluationData.responses = [];
                    }
                    window.evaluationData.responses.push({
                        question: questionNumber,
                        question_text: questionText,
                        answer: respuesta,
                        correct_answer: correctAnswer,
                        score: parseInt(result.data.puntuacion) || 0,
                        feedback: result.data.feedback || ''
                    });

                    // Actualizar contador de preguntas completadas
                    window.evaluationData.completedQuestions = Object.keys(window.evaluationData.questionData).length;

                    console.log('✅ Datos guardados. questionData actual:', window.evaluationData.questionData);
                    console.log('📊 Preguntas completadas:', window.evaluationData.completedQuestions);

                    // Guardar en base de datos inmediatamente
                    saveQuestionToDatabase(questionNumber, questionText, respuesta, correctAnswer, result.data.puntuacion, result.data.feedback)
                        .then(saveResult => {
                            console.log('✅ Pregunta guardada exitosamente en BD:', saveResult);
                        })
                        .catch(saveError => {
                            console.error('❌ Error al guardar pregunta en BD:', saveError);
                        });

                    console.log('📝 Datos guardados en questionData:', window.evaluationData.questionData);

                    // Mostrar feedback de la IA
                    showFeedback(result.data);
                } else {
                    console.error('❌ Error en evaluación IA:', result.data);
                    // Fallback a evaluación local si falla la IA
                    const localEvaluation = evaluateTestAnswer(respuesta);
                    
                    // Guardar datos localmente también
                    const questionNumber = window.evaluationData.currentQuestion;
                    const questionText = window.currentQuestionData ? window.currentQuestionData.pregunta : document.getElementById('wpsa-question-text').textContent;
                    const correctAnswer = window.currentQuestionData ? window.currentQuestionData.respuesta_correcta : '';

                    // Guardar en questionData
                    if (!window.evaluationData.questionData) {
                        window.evaluationData.questionData = {};
                    }
                    window.evaluationData.questionData[questionNumber] = {
                        question: questionText,
                        answer: respuesta,
                        correctAnswer: correctAnswer,
                        score: parseInt(localEvaluation.puntuacion) || 0,
                        maxScore: 10,
                        feedback: localEvaluation.feedback || '',
                        timestamp: new Date().toISOString()
                    };

                    // Guardar en base de datos
                    saveQuestionToDatabase(questionNumber, questionText, respuesta, correctAnswer, localEvaluation.puntuacion, localEvaluation.feedback)
                        .then(saveResult => {
                            console.log('✅ Pregunta guardada exitosamente en BD (fallback):', saveResult);
                        })
                        .catch(saveError => {
                            console.error('❌ Error al guardar pregunta en BD (fallback):', saveError);
                        });

                    showFeedback(localEvaluation);
                }
            })
            .catch(error => {
                console.error('❌ Error de conexión en evaluación IA:', error);
                hideLoading();
                // Fallback a evaluación local si hay error de conexión
                const localEvaluation = evaluateTestAnswer(respuesta);
                
                // Guardar datos localmente también
                const questionNumber = window.evaluationData.currentQuestion;
                const questionText = window.currentQuestionData ? window.currentQuestionData.pregunta : document.getElementById('wpsa-question-text').textContent;
                const correctAnswer = window.currentQuestionData ? window.currentQuestionData.respuesta_correcta : '';

                // Guardar en questionData
                if (!window.evaluationData.questionData) {
                    window.evaluationData.questionData = {};
                }
                window.evaluationData.questionData[questionNumber] = {
                    question: questionText,
                    answer: respuesta,
                    correctAnswer: correctAnswer,
                    score: parseInt(localEvaluation.puntuacion) || 0,
                    maxScore: 10,
                    feedback: localEvaluation.feedback || '',
                    timestamp: new Date().toISOString()
                };

                // Guardar en base de datos
                saveQuestionToDatabase(questionNumber, questionText, respuesta, correctAnswer, localEvaluation.puntuacion, localEvaluation.feedback)
                    .then(saveResult => {
                        console.log('✅ Pregunta guardada exitosamente en BD (fallback conexión):', saveResult);
                    })
                    .catch(saveError => {
                        console.error('❌ Error al guardar pregunta en BD (fallback conexión):', saveError);
                    });

                showFeedback(localEvaluation);
            });
        }
        
        // Función para evaluar respuesta de prueba
        function evaluateTestAnswer(respuesta) {
            const nivel = window.evaluationData.nivel;
            const modalidad = window.evaluationData.modalidad;
            
            let puntuacion = 5; // Puntuación base más alta
            let feedback = '';
            let recomendaciones = '';
            
            // Evaluar longitud según nivel
            const longitudMinima = nivel === 'inicial' ? 10 : nivel === 'intermedio' ? 20 : 30;
            const longitudIdeal = nivel === 'inicial' ? 30 : nivel === 'intermedio' ? 60 : 100;
            
            if (respuesta.length < longitudMinima) {
                puntuacion = Math.max(puntuacion - 2, 1); // Mínimo 1 punto
                feedback += 'Tu respuesta es muy corta. ';
            } else if (respuesta.length >= longitudIdeal) {
                puntuacion = Math.min(puntuacion + 3, 10); // Máximo 10 puntos
                feedback += 'Excelente detalle en tu respuesta. ';
            } else {
                puntuacion = Math.min(puntuacion + 1, 10);
                feedback += 'Buena longitud en tu respuesta. ';
            }
            
            // Evaluar contenido según modalidad y nivel
            let palabrasClave = [];
            if (modalidad === 'preguntas_simples') {
                palabrasClave = ['es', 'son', 'define', 'significa', 'importante', 'básico', 'concepto'];
            } else if (modalidad === 'ejercicios') {
                palabrasClave = ['paso', 'calculo', 'resultado', 'solución', 'proceso', 'fórmula', 'operación'];
            } else if (modalidad === 'codigo') {
                palabrasClave = ['código', 'función', 'variable', 'programa', 'algoritmo', 'lógica', 'sintaxis'];
            }
            
            const palabrasEncontradas = palabrasClave.filter(palabra => 
                respuesta.toLowerCase().includes(palabra)
            );
            
            if (palabrasEncontradas.length > 0) {
                puntuacion += Math.min(palabrasEncontradas.length, 3);
                feedback += `Bien, usas términos apropiados como: ${palabrasEncontradas.join(', ')}. `;
            }
            
            // Agregar feedback específico y didáctico
            const specificFeedback = getSpecificFeedback(respuesta, modalidad, nivel);
            feedback += specificFeedback;
            
            // Evaluar estructura y organización
            if (respuesta.includes('1.') || respuesta.includes('2.') || respuesta.includes('•') || respuesta.includes('-')) {
                puntuacion += 1;
                feedback += 'Buena estructura en tu respuesta. ';
            }
            
            // Evaluar coherencia básica
            if (respuesta.includes('?') && respuesta.length > 20) {
                puntuacion += 1;
                feedback += 'Muy bien, haces preguntas reflexivas. ';
            }
            
            // Evaluar ejemplos
            if (respuesta.includes('ejemplo') || respuesta.includes('por ejemplo') || respuesta.includes('como')) {
                puntuacion += 1;
                feedback += 'Excelente, incluyes ejemplos. ';
            }
            
            // Ajustar puntuación según nivel
            if (nivel === 'inicial') {
                puntuacion = Math.min(puntuacion + 1, 10); // Más generoso con nivel inicial
            } else if (nivel === 'avanzado') {
                puntuacion = Math.max(puntuacion - 1, 1); // Más estricto con nivel avanzado
            }
            
            // Ajustar puntuación final
            puntuacion = Math.max(1, Math.min(10, puntuacion));
            
            // Generar feedback específico por nivel
            if (puntuacion >= 8) {
                feedback += '¡Excelente trabajo! Tu respuesta demuestra una muy buena comprensión del tema.';
                recomendaciones = nivel === 'inicial' ? 
                    '¡Muy bien! Continúa aprendiendo y practicando.' :
                    'Excelente nivel. Considera explorar temas más avanzados.';
            } else if (puntuacion >= 6) {
                feedback += 'Buena respuesta, pero puedes mejorar algunos aspectos.';
                recomendaciones = nivel === 'inicial' ?
                    'Intenta ser más específico y dar más detalles.' :
                    'Revisa los conceptos y trata de ser más preciso.';
            } else if (puntuacion >= 4) {
                feedback += 'Tu respuesta necesita más desarrollo y claridad.';
                recomendaciones = nivel === 'inicial' ?
                    'No te preocupes, sigue practicando y mejorando.' :
                    'Revisa el tema y trata de incluir más información.';
            } else {
                feedback += 'Tu respuesta necesita una revisión completa.';
                recomendaciones = nivel === 'inicial' ?
                    'Es normal al principio. Sigue estudiando y practicando.' :
                    'Te recomiendo estudiar más el tema antes de continuar.';
            }
            
            return {
                puntuacion: puntuacion,
                feedback: feedback,
                recomendaciones: recomendaciones
            };
        }
        
        // Función para mostrar feedback
        function showFeedback(evaluationData) {
            // Remover feedback anterior si existe
            const existingFeedback = document.querySelector('.wpsa-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }

            const feedbackHtml = `
                <div class="wpsa-feedback" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 15px; border-left: 4px solid #1e3a8a;">
                    <h4 style="margin: 0 0 10px 0; color: #333;">Evaluación de tu respuesta:</h4>
                    <div style="background: #e3f2fd; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                        <strong>Puntuación: ${evaluationData.puntuacion || 0}/10</strong>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <strong>Comentarios:</strong><br>
                        ${evaluationData.feedback || 'Sin comentarios disponibles.'}
                    </div>
                    ${evaluationData.recomendaciones ? `
                        <div style="background: #fff3cd; padding: 10px; border-radius: 4px;">
                            <strong>Recomendaciones:</strong><br>
                            ${evaluationData.recomendaciones}
                        </div>
                    ` : ''}
                </div>
            `;

            // Agregar feedback al contenido de la pregunta
            const questionContent = document.getElementById('wpsa-question-content');
            if (questionContent) {
                questionContent.insertAdjacentHTML('beforeend', feedbackHtml);
            }

            // Ocultar botones de respuesta y mostrar siguiente
            const submitButton = document.getElementById('wpsa-submit-answer');
            const skipButton = document.getElementById('wpsa-skip-question');
            const nextButton = document.getElementById('wpsa-next-question');

            if (submitButton) submitButton.style.display = 'none';
            if (skipButton) skipButton.style.display = 'none';
            if (nextButton) nextButton.style.display = 'inline-block';
        }
        
        // Función para omitir pregunta
        function skipQuestion() {
            window.evaluationData.currentQuestion++;
            generateQuestion();
        }
        
        // Función para siguiente pregunta
        function nextQuestion() {
            window.evaluationData.currentQuestion++;
            
            // Verificar si hemos alcanzado el límite máximo
            const maxQuestions = 5; // Límite reducido para pruebas
            if (window.evaluationData.currentQuestion > maxQuestions) {
                finishEvaluation();
                return;
            }
            
            // Limpiar feedback anterior
            document.getElementById('wpsa-question-content').innerHTML = '';
            
            // Generar siguiente pregunta
            generateQuestion();
        }
        
        // Función para calcular resultados finales usando array asociativo
        function calculateFinalResults() {
            console.log('📊 Iniciando cálculo de resultados finales');
            console.log('📊 evaluationData completo:', window.evaluationData);

            // Verificar que existe evaluationData
            if (!window.evaluationData) {
                console.error('❌ No existe window.evaluationData');
                return null;
            }

            const questionData = window.evaluationData.questionData;
            console.log('📊 questionData:', questionData);

            // Verificar que questionData existe
            if (!questionData) {
                console.error('❌ questionData no existe');
                return null;
            }

            const questionKeys = Object.keys(questionData);
            console.log('📊 Claves de preguntas:', questionKeys);

            // Si no hay preguntas, intentar usar responses como fallback
            if (questionKeys.length === 0) {
                console.warn('⚠️ No hay preguntas en questionData, intentando usar responses');
                if (window.evaluationData.responses && window.evaluationData.responses.length > 0) {
                    return calculateFromResponses(window.evaluationData.responses);
                }
                console.error('❌ No hay datos de preguntas para calcular');
                return null;
            }

            let totalScore = 0;
            let obtainedScore = 0;
            let totalQuestions = questionKeys.length;
            let validQuestions = 0;

            // Calcular puntuaciones (cada pregunta vale 10 puntos por defecto)
            questionKeys.forEach(key => {
                const question = questionData[key];
                if (!question) {
                    console.warn('⚠️ Pregunta vacía encontrada en clave:', key);
                    return;
                }

                const maxScore = parseInt(question.maxScore) || 10; // Valor por defecto
                const score = parseInt(question.score) || 0; // Valor por defecto

                console.log(`📊 Pregunta ${key}: maxScore=${maxScore}, score=${score}`);

                totalScore += maxScore;
                obtainedScore += score;
                validQuestions++;
            });

            // Si no hay preguntas válidas, usar valores por defecto
            if (validQuestions === 0) {
                console.warn('⚠️ No hay preguntas válidas, usando valores por defecto');
                totalScore = totalQuestions * 10;
                obtainedScore = 0;
            }

            const percentage = totalScore > 0 ? Math.round((obtainedScore / totalScore) * 100) : 0;

            console.log('📊 Cálculo de resultados:', {
                totalQuestions: totalQuestions,
                validQuestions: validQuestions,
                totalScore: totalScore,
                obtainedScore: obtainedScore,
                percentage: percentage + '%'
            });

            return {
                totalQuestions: totalQuestions,
                totalScore: totalScore,
                obtainedScore: obtainedScore,
                percentage: percentage,
                questionData: questionData
            };
        }

        // Función auxiliar para calcular desde responses (fallback)
        function calculateFromResponses(responses) {
            console.log('📊 Calculando desde responses:', responses);

            if (!responses || responses.length === 0) {
                return null;
            }

            let totalScore = responses.length * 10; // Cada pregunta vale 10 puntos
            let obtainedScore = 0;

            responses.forEach(response => {
                obtainedScore += parseInt(response.score) || 0;
            });

            const percentage = totalScore > 0 ? Math.round((obtainedScore / totalScore) * 100) : 0;

            console.log('📊 Resultados desde responses:', {
                totalQuestions: responses.length,
                totalScore: totalScore,
                obtainedScore: obtainedScore,
                percentage: percentage + '%'
            });

            return {
                totalQuestions: responses.length,
                totalScore: totalScore,
                obtainedScore: obtainedScore,
                percentage: percentage,
                questionData: responses
            };
        }
        
        // Variable para prevenir múltiples envíos
        let evaluationSaving = false;

        // Función para finalizar evaluación (versión simplificada)
        function finishEvaluation() {
            // Prevenir múltiples envíos
            if (evaluationSaving) {
                console.log('⚠️ Evaluación ya se está guardando, ignorando solicitud duplicada');
                return;
            }

            evaluationSaving = true;
            console.log('🎯 Finalizando evaluación...');
            console.log('📊 Estado actual de evaluationData:', window.evaluationData);

            // Verificar que tenemos datos de evaluación
            if (!window.evaluationData) {
                console.error('❌ No hay datos de evaluación disponibles');
                showEvaluationError();
                evaluationSaving = false;
                return;
            }

            // Guardar evaluación final en la base de datos (el backend calculará los resultados)
            saveFinalEvaluationToDatabase({})
                .then(result => {
                    console.log('✅ Evaluación guardada en BD:', result);

                    // Ir al paso 4
                    document.getElementById('wpsa-step-3').classList.remove('active');
                    document.getElementById('wpsa-step-4').classList.add('active');
                    currentStep = 4;

                    // Mostrar resultados usando la función unificada
                    if (result.puntuacion_obtenida !== undefined && result.puntuacion_total !== undefined && result.porcentaje !== undefined) {
                        displayFinalResults(result);
                        console.log('🎯 Resultados mostrados desde BD:', result);
                        // Don't reset flag on success to prevent duplicate submissions
                    } else {
                        console.error('❌ Datos de resultado incompletos en respuesta del servidor');
                        showEvaluationError();
                        evaluationSaving = false; // Reset flag only on incomplete data
                    }
                })
                .catch(error => {
                    console.error('❌ Error al guardar evaluación final:', error);
                    // Check if it's a duplicate evaluation error
                    if (error && typeof error === 'string' && error.includes('evaluación reciente')) {
                        console.log('⚠️ Evaluación duplicada detectada, intentando obtener resultados existentes');
                        // Try to get the existing evaluation results
                        getExistingEvaluationResults();
                    } else {
                        showEvaluationError();
                        evaluationSaving = false; // Reset flag on other errors
                    }
                });
        }
        
        // Función para generar recomendaciones didácticas detalladas
        function generateDetailedRecommendations(percentage, evaluationData) {
            const materia = evaluationData.materia_nombre || 'la materia';
            const nivel = evaluationData.nivel;
            const modalidad = evaluationData.modalidad;
            
            let recommendations = '';
            
            if (percentage >= 90) {
                recommendations = `
                    <div style="background: #d4edda; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <h4 style="color: #155724; margin: 0 0 10px 0;">🎉 ¡Excelente trabajo!</h4>
                        <p style="margin: 0;">Has demostrado un dominio excepcional de ${materia} en nivel ${nivel}.</p>
                    </div>
                    <h4>📚 Próximos pasos recomendados:</h4>
                    <ul>
                        <li><strong>Profundiza en conceptos avanzados:</strong> Explora temas más complejos de ${materia}</li>
                        <li><strong>Proyectos prácticos:</strong> Aplica tus conocimientos en proyectos reales</li>
                        <li><strong>Mentoría:</strong> Considera ayudar a otros estudiantes</li>
                        <li><strong>Certificaciones:</strong> Busca certificaciones profesionales en ${materia}</li>
                    </ul>
                `;
            } else if (percentage >= 80) {
                recommendations = `
                    <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <h4 style="color: #0c5460; margin: 0 0 10px 0;">👍 Muy bien hecho!</h4>
                        <p style="margin: 0;">Tienes una buena comprensión de ${materia}, pero hay áreas específicas para mejorar.</p>
                    </div>
                    <h4>🎯 Áreas específicas para mejorar:</h4>
                    <ul>
                        <li><strong>Conceptos fundamentales:</strong> Revisa los principios básicos de ${materia}</li>
                        <li><strong>Práctica dirigida:</strong> Enfócate en ejercicios de ${modalidad}</li>
                        <li><strong>Ejemplos reales:</strong> Busca casos de estudio en ${materia}</li>
                        <li><strong>Resolución de problemas:</strong> Practica con problemas más complejos</li>
                    </ul>
                `;
            } else if (percentage >= 70) {
                recommendations = `
                    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <h4 style="color: #856404; margin: 0 0 10px 0;">⚠️ Buen trabajo, pero necesitas mejorar</h4>
                        <p style="margin: 0;">Tu comprensión de ${materia} es básica. Hay conceptos clave que requieren atención.</p>
                    </div>
                    <h4>🔍 Errores comunes a evitar:</h4>
                    <ul>
                        <li><strong>Conceptos mal entendidos:</strong> Revisa la teoría fundamental de ${materia}</li>
                        <li><strong>Métodos incorrectos:</strong> Practica la metodología correcta para ${modalidad}</li>
                        <li><strong>Falta de práctica:</strong> Resuelve más ejercicios paso a paso</li>
                        <li><strong>Conceptos intermedios:</strong> Enfócate en temas de nivel intermedio</li>
                    </ul>
                `;
            } else if (percentage >= 60) {
                recommendations = `
                    <div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <h4 style="color: #721c24; margin: 0 0 10px 0;">❌ Necesitas mejorar significativamente</h4>
                        <p style="margin: 0;">Tu comprensión de ${materia} es insuficiente. Requieres estudio intensivo.</p>
                    </div>
                    <h4>📖 Plan de estudio recomendado:</h4>
                    <ul>
                        <li><strong>Revisión completa:</strong> Vuelve a estudiar desde lo básico en ${materia}</li>
                        <li><strong>Conceptos fundamentales:</strong> Domina los principios básicos antes de avanzar</li>
                        <li><strong>Práctica diaria:</strong> Dedica tiempo diario a ejercicios de ${modalidad}</li>
                        <li><strong>Buscar ayuda:</strong> Considera tutorías o clases adicionales</li>
                    </ul>
                `;
            } else {
                recommendations = `
                    <div style="background: #f5c6cb; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                        <h4 style="color: #721c24; margin: 0 0 10px 0;">🚨 Requiere atención especial</h4>
                        <p style="margin: 0;">Tu comprensión de ${materia} es muy limitada. Necesitas un enfoque completamente nuevo.</p>
                    </div>
                    <h4>🆘 Acciones inmediatas recomendadas:</h4>
                    <ul>
                        <li><strong>Reiniciar desde cero:</strong> Comienza con conceptos básicos de ${materia}</li>
                        <li><strong>Buscar ayuda profesional:</strong> Considera un tutor o profesor particular</li>
                        <li><strong>Estudio estructurado:</strong> Sigue un plan de estudio paso a paso</li>
                        <li><strong>Evaluación de conocimientos previos:</strong> Identifica qué conceptos básicos faltan</li>
                    </ul>
                `;
            }
            
            // Agregar recomendaciones específicas por materia
            const materiaRecommendations = getMateriaSpecificRecommendations(materia, nivel, modalidad);
            recommendations += materiaRecommendations;
            
            return recommendations;
        }
        
        // Función para obtener recomendaciones específicas por materia
        function getMateriaSpecificRecommendations(materia, nivel, modalidad) {
            const materiaLower = materia.toLowerCase();
            
            if (materiaLower.includes('matem') || materiaLower.includes('algebra') || materiaLower.includes('calculo')) {
                return `
                    <h4>🔢 Recursos específicos para Matemáticas:</h4>
                    <ul>
                        <li><strong>Conceptos básicos:</strong> Aritmética, álgebra elemental, geometría básica</li>
                        <li><strong>Práctica:</strong> Resuelve problemas paso a paso, no uses calculadora para operaciones básicas</li>
                        <li><strong>Visualización:</strong> Usa gráficos y diagramas para entender conceptos</li>
                        <li><strong>Ejercicios recomendados:</strong> Khan Academy, ejercicios de repaso de tu libro de texto</li>
                    </ul>
                `;
            } else if (materiaLower.includes('program') || materiaLower.includes('codigo') || materiaLower.includes('software')) {
                return `
                    <h4>💻 Recursos específicos para Programación:</h4>
                    <ul>
                        <li><strong>Conceptos básicos:</strong> Variables, bucles, condicionales, funciones</li>
                        <li><strong>Práctica:</strong> Escribe código todos los días, resuelve problemas en plataformas como LeetCode</li>
                        <li><strong>Debugging:</strong> Aprende a identificar y corregir errores en tu código</li>
                        <li><strong>Recursos recomendados:</h4> FreeCodeCamp, Codecademy, ejercicios de HackerRank</li>
                    </ul>
                `;
            } else if (materiaLower.includes('fisica') || materiaLower.includes('mecanica')) {
                return `
                    <h4>⚡ Recursos específicos para Física:</h4>
                    <ul>
                        <li><strong>Conceptos básicos:</strong> Mecánica, termodinámica, electromagnetismo</li>
                        <li><strong>Práctica:</strong> Resuelve problemas con diagramas y fórmulas</li>
                        <li><strong>Visualización:</strong> Usa simulaciones y experimentos virtuales</li>
                        <li><strong>Recursos recomendados:</strong> PhET Simulations, Khan Academy Physics</li>
                    </ul>
                `;
            } else if (materiaLower.includes('quimica') || materiaLower.includes('quimic')) {
                return `
                    <h4>🧪 Recursos específicos para Química:</h4>
                    <ul>
                        <li><strong>Conceptos básicos:</strong> Estructura atómica, enlaces químicos, reacciones</li>
                        <li><strong>Práctica:</strong> Balancea ecuaciones, calcula moles y concentraciones</li>
                        <li><strong>Visualización:</strong> Usa modelos moleculares y simulaciones</li>
                        <li><strong>Recursos recomendados:</strong> ChemCollective, Phet Chemistry Simulations</li>
                    </ul>
                `;
            }
            
            return '';
        }
        
        // Función para guardar pregunta individual en la base de datos
        function saveQuestionToDatabase(questionNumber, question, answer, correctAnswer, score, feedback) {
            return new Promise((resolve, reject) => {
                console.log('💾 Guardando pregunta en BD:', {
                    questionNumber,
                    question: question ? question.substring(0, 50) + '...' : 'N/A',
                    answer: answer ? answer.substring(0, 50) + '...' : 'N/A',
                    correctAnswer: correctAnswer ? correctAnswer.substring(0, 50) + '...' : 'N/A',
                    score,
                    feedback: feedback ? feedback.substring(0, 50) + '...' : 'N/A'
                });

                // Validar datos antes de enviar
                if (!question || !answer) {
                    console.error('❌ Datos insuficientes para guardar pregunta:', { question, answer });
                    reject(new Error('Datos insuficientes para guardar pregunta'));
                    return;
                }

                const data = {
                    action: 'wpsa_save_individual_question',
                    nonce: '<?php echo wp_create_nonce("wpsa_nonce"); ?>',
                    question_number: questionNumber,
                    question_text: question,
                    answer: answer,
                    correct_answer: correctAnswer || '',
                    score: parseInt(score) || 0,
                    feedback: feedback || '',
                    materia_id: window.evaluationData ? window.evaluationData.materia_id : 0,
                    estudiante_nombre: window.evaluationData ? (window.evaluationData.estudiante_nombre || 'Anónimo') : 'Anónimo',
                    evaluation_id: window.evaluationData ? window.evaluationData.evaluation_id : null
                };

                console.log('📤 Enviando datos a AJAX save_individual_question:', data);

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(data)
                })
                .then(response => {
                    console.log('📥 Respuesta HTTP save_individual_question:', response.status);
                    return response.json();
                })
                .then(result => {
                    console.log('📋 Resultado del guardado save_individual_question:', result);

                    if (result.success) {
                        console.log('✅ Pregunta guardada en BD exitosamente:', result.data);

                        // Actualizar evaluationData con el ID de evaluación
                        if (result.data && result.data.evaluation_id && window.evaluationData && !window.evaluationData.evaluation_id) {
                            window.evaluationData.evaluation_id = result.data.evaluation_id;
                            console.log('📝 ID de evaluación guardado:', result.data.evaluation_id);
                        }

                        resolve(result.data);
                    } else {
                        console.error('❌ Error al guardar pregunta en BD:', result.data || result);
                        reject(new Error(result.data || 'Error al guardar pregunta'));
                    }
                })
                .catch(error => {
                    console.error('❌ Error de conexión al guardar pregunta:', error);
                    console.error('❌ Detalles del error:', error.message);
                    reject(error);
                });
            });
        }

        // Función para guardar evaluación en la base de datos
        function saveEvaluationToDatabase(obtainedScore, totalScore, percentage, recommendations) {
            // Crear datos para enviar
            const formData = new FormData();
            formData.append('action', 'wpsa_save_evaluation');
            formData.append('materia_id', window.evaluationData.materia_id);
            formData.append('estudiante_nombre', window.evaluationData.estudiante_nombre);
            formData.append('tema', window.evaluationData.tema);
            formData.append('modalidad', window.evaluationData.modalidad);
            formData.append('preguntas_respuestas', JSON.stringify([])); // Array vacío por ahora
            formData.append('puntuacion_total', totalScore);
            formData.append('puntuacion_obtenida', obtainedScore);
            formData.append('porcentaje', percentage);
            formData.append('recomendaciones', recommendations);
            formData.append('estado', 'completada');
            formData.append('nonce', '<?php echo wp_create_nonce("wpsa_nonce"); ?>');

            // Debug: mostrar datos que se van a enviar
            console.log('Guardando evaluación con datos:', {
                materia_id: window.evaluationData.materia_id,
                puntuacion_total: totalScore,
                puntuacion_obtenida: obtainedScore,
                porcentaje: percentage
            });

            // Enviar petición AJAX
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Evaluación guardada correctamente:', data.data);
                } else {
                    console.error('Error al guardar evaluación:', data.data);
                }
            })
            .catch(error => {
                console.error('Error de conexión al guardar:', error);
            });
        }

        // Función para descargar resultados
        function downloadResults() {
            if (!window.evaluationData || !window.evaluationData.evaluation_id) {
                alert('No hay resultados disponibles para descargar.');
                return;
            }

            // Crear URL para exportar evaluación como PDF
            const exportUrl = '<?php echo admin_url("admin-ajax.php"); ?>?action=wpsa_export_pdf&evaluation_id=' + window.evaluationData.evaluation_id + '&nonce=<?php echo wp_create_nonce("wpsa_export_nonce"); ?>';

            // Abrir en nueva ventana para descarga
            window.open(exportUrl, '_blank');
        }
        
        // Función para generar feedback específico y didáctico
        function getSpecificFeedback(respuesta, modalidad, nivel) {
            const respuestaLower = respuesta.toLowerCase();
            let specificFeedback = '';
            
            // Feedback específico para análisis de código
            if (modalidad === 'codigo') {
                if (respuestaLower.includes('función') || respuestaLower.includes('function')) {
                    specificFeedback += ' ✅ Correcto: Identificaste que es una función. ';
                } else {
                    specificFeedback += ' 💡 Pista: ¿Qué tipo de estructura de código es? (función, bucle, condicional) ';
                }
                
                if (respuestaLower.includes('console') || respuestaLower.includes('imprimir') || respuestaLower.includes('mostrar')) {
                    specificFeedback += ' ✅ Correcto: Mencionaste la salida por consola. ';
                } else {
                    specificFeedback += ' 💡 Pista: ¿Dónde se muestra el resultado? ';
                }
                
                if (respuestaLower.includes('bucle') || respuestaLower.includes('loop') || respuestaLower.includes('for') || respuestaLower.includes('while')) {
                    specificFeedback += ' ✅ Correcto: Identificaste el bucle. ';
                } else if (respuesta.includes('for(') || respuesta.includes('while(')) {
                    specificFeedback += ' 💡 Pista: Observa la estructura "for()" o "while()" - ¿qué tipo de control de flujo es? ';
                }
                
                if (respuestaLower.includes('variable') || respuestaLower.includes('var') || respuestaLower.includes('let') || respuestaLower.includes('const')) {
                    specificFeedback += ' ✅ Correcto: Mencionaste las variables. ';
                } else {
                    specificFeedback += ' 💡 Pista: ¿Qué elementos del código almacenan datos? ';
                }
            }
            
            // Feedback específico para ejercicios matemáticos
            if (modalidad === 'ejercicios') {
                if (respuestaLower.includes('paso') || respuestaLower.includes('proceso') || respuestaLower.includes('primero') || respuestaLower.includes('después')) {
                    specificFeedback += ' ✅ Correcto: Explicaste el proceso paso a paso. ';
                } else {
                    specificFeedback += ' 💡 Pista: Para ejercicios matemáticos, explica cada paso del cálculo. ';
                }
                
                if (respuestaLower.includes('fórmula') || respuestaLower.includes('ecuación') || respuestaLower.includes('=')) {
                    specificFeedback += ' ✅ Correcto: Mencionaste la fórmula o ecuación. ';
                } else {
                    specificFeedback += ' 💡 Pista: ¿Qué fórmula o ecuación usas para resolver este problema? ';
                }
                
                if (respuestaLower.includes('resultado') || respuestaLower.includes('respuesta') || respuestaLower.includes('solución')) {
                    specificFeedback += ' ✅ Correcto: Incluiste el resultado final. ';
                } else {
                    specificFeedback += ' 💡 Pista: No olvides incluir el resultado final de tu cálculo. ';
                }
            }
            
            // Feedback específico para preguntas conceptuales
            if (modalidad === 'preguntas_simples') {
                if (respuestaLower.includes('porque') || respuestaLower.includes('por qué') || respuestaLower.includes('debido') || respuestaLower.includes('ya que')) {
                    specificFeedback += ' ✅ Correcto: Explicaste el razonamiento. ';
                } else {
                    specificFeedback += ' 💡 Pista: Intenta explicar por qué sucede esto o por qué es importante. ';
                }
                
                if (respuestaLower.includes('ejemplo') || respuestaLower.includes('como') || respuestaLower.includes('tales como')) {
                    specificFeedback += ' ✅ Correcto: Usaste ejemplos para ilustrar tu punto. ';
                } else {
                    specificFeedback += ' 💡 Pista: ¿Puedes dar un ejemplo práctico de este concepto? ';
                }
            }
            
            // Feedback específico por nivel
            if (nivel === 'inicial') {
                if (respuesta.length < 20) {
                    specificFeedback += ' 📝 Sugerencia: En nivel inicial, no te preocupes por dar una respuesta perfecta, pero intenta explicar lo que entiendes. ';
                }
                specificFeedback += ' 🌱 Recuerda: En nivel inicial es normal tener dudas, lo importante es intentar. ';
            } else if (nivel === 'intermedio') {
                if (!respuestaLower.includes('porque') && !respuestaLower.includes('por qué')) {
                    specificFeedback += ' 🤔 Pregunta: ¿Puedes explicar por qué sucede esto? En nivel intermedio se espera más análisis. ';
                }
                specificFeedback += ' 📊 Sugerencia: En nivel intermedio, intenta conectar conceptos y mostrar relaciones. ';
            } else if (nivel === 'avanzado') {
                if (!respuestaLower.includes('aplicación') && !respuestaLower.includes('práctica') && !respuestaLower.includes('real')) {
                    specificFeedback += ' 🎯 Pregunta: ¿Cómo se aplica esto en situaciones reales? En nivel avanzado se esperan aplicaciones prácticas. ';
                }
                specificFeedback += ' 🔬 Sugerencia: En nivel avanzado, considera implicaciones, limitaciones y alternativas. ';
            }
            
            // Feedback general de mejora
            if (respuesta.length < 15) {
                specificFeedback += ' 📝 Sugerencia: Intenta dar más detalles en tu respuesta. ';
            }
            
            if (!respuestaLower.includes('es') && !respuestaLower.includes('son') && !respuestaLower.includes('define')) {
                specificFeedback += ' 💭 Pregunta: ¿Puedes definir o explicar qué es este concepto? ';
            }
            
            return specificFeedback;
        }
        
        // Función para mostrar error de evaluación
        function showEvaluationError() {
            // Ir al paso 4
            document.getElementById('wpsa-step-3').classList.remove('active');
            document.getElementById('wpsa-step-4').classList.add('active');
            currentStep = 4;
            
            // Mostrar error
            document.getElementById('wpsa-final-score').textContent = 'Error';
            document.getElementById('wpsa-total-score').textContent = 'Error';
            document.getElementById('wpsa-percentage').textContent = 'Error';
            
            // Mostrar mensaje de error
            const errorMessage = `
                <div style="background: #f8d7da; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
                    <h4 style="color: #721c24; margin: 0 0 10px 0;">⚠️ Error en la Evaluación</h4>
                    <p style="margin: 0; color: #721c24;">No se pudieron procesar las respuestas correctamente. Por favor, intenta nuevamente.</p>
                </div>
                <div style="text-align: center; margin: 20px 0;">
                    <button onclick="startNewEvaluation()" style="background: #1e3a8a; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                        Intentar Nuevamente
                    </button>
                </div>
            `;
            
            document.getElementById('wpsa-recommendations-content').innerHTML = errorMessage;
        }
        
        // Funciones auxiliares
        function showLoading() {
            document.getElementById('wpsa-question-text').textContent = 'Cargando...';
            document.getElementById('wpsa-question-content').innerHTML = '<div style="text-align: center; padding: 20px;"><div style="display: inline-block; width: 20px; height: 20px; border: 2px solid #e9ecef; border-top: 2px solid #1e3a8a; border-radius: 50%; animation: spin 1s linear infinite;"></div></div>';
        }
        
        function hideLoading() {
            // Se oculta cuando se muestra la pregunta
        }
        
        function showError(message) {
            const errorHtml = `
                <div style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 6px; padding: 15px; margin: 20px 0;">
                    ${message}
                </div>
            `;
            
            document.getElementById('wpsa-question-content').innerHTML = errorHtml;
        }
        
        // Función para nueva evaluación
        function newEvaluation() {
            // Resetear datos
            selectedMateriaData = null;
            currentStep = 1;

            // Limpiar formularios
            document.getElementById('wpsa-estudiante-nombre').value = '';
            document.getElementById('wpsa-tema').value = '';
            document.querySelectorAll('input[name="nivel"]').forEach(input => input.checked = false);
            document.querySelector('input[name="nivel"][value="inicial"]').checked = true;
            document.querySelectorAll('input[name="modalidad"]').forEach(input => input.checked = false);
            document.querySelectorAll('.wpsa-materia-card').forEach(card => card.classList.remove('selected'));

            // Ocultar botón continuar
            document.getElementById('wpsa-continue-step-1').style.display = 'none';

            // Ir al paso 1
            document.getElementById('wpsa-step-4').classList.remove('active');
            document.getElementById('wpsa-step-1').classList.add('active');
        }

        // Función de debug para verificar estado de evaluación
        function debugEvaluationState() {
            console.log('🔍 DEBUG: Estado actual de la evaluación');
            console.log('selectedMateriaData:', selectedMateriaData);
            console.log('currentStep:', currentStep);
            console.log('evaluationData:', window.evaluationData);
            console.log('evaluationSaving:', evaluationSaving);

            if (window.evaluationData) {
                console.log('questionData keys:', Object.keys(window.evaluationData.questionData || {}));
                console.log('responses length:', (window.evaluationData.responses || []).length);
            }

            return window.evaluationData;
        }

        // Exponer función de debug globalmente
        window.debugEvaluationState = debugEvaluationState;

        // Función de prueba para completar evaluación con datos de ejemplo
        window.testCompleteEvaluation = function() {
            console.log('🧪 Iniciando prueba de evaluación completa');

            // Crear datos de prueba
            window.evaluationData = {
                materia_id: 1,
                materia_nombre: 'Matemáticas',
                estudiante_nombre: 'Estudiante de Prueba',
                tema: 'Álgebra',
                nivel: 'intermedio',
                modalidad: 'ejercicios',
                currentQuestion: 6, // Simular que ya respondió 5 preguntas
                responses: [],
                questionData: {
                    1: {
                        question: 'Pregunta de prueba 1',
                        answer: 'Respuesta de prueba 1',
                        score: 8,
                        maxScore: 10,
                        feedback: 'Buena respuesta'
                    },
                    2: {
                        question: 'Pregunta de prueba 2',
                        answer: 'Respuesta de prueba 2',
                        score: 7,
                        maxScore: 10,
                        feedback: 'Bien hecho'
                    },
                    3: {
                        question: 'Pregunta de prueba 3',
                        answer: 'Respuesta de prueba 3',
                        score: 9,
                        maxScore: 10,
                        feedback: 'Excelente'
                    }
                },
                totalQuestions: 3,
                completedQuestions: 3
            };

            console.log('✅ Datos de prueba creados:', window.evaluationData);

            // Intentar finalizar evaluación
            finishEvaluation();
        };
        
        // Funciones auxiliares
        function getNivelName(nivel) {
            const niveles = {
                'inicial': 'Inicial 🌱',
                'intermedio': 'Intermedio ⚡',
                'avanzado': 'Avanzado 🚀'
            };
            return niveles[nivel] || nivel;
        }
        
        function getModalidadName(modalidad) {
            const modalidades = {
                'preguntas_simples': 'Preguntas Simples',
                'ejercicios': 'Ejercicios Prácticos',
                'codigo': 'Análisis de Situaciones, Problemas o Código'
            };
            return modalidades[modalidad] || modalidad;
        }
        
        // Función para guardar puntuación en sesión PHP
        function saveQuestionScoreToSession(questionNumber, score, answer, feedback) {
            const data = {
                action: 'wpsa_save_question_score',
                nonce: '<?php echo wp_create_nonce("wpsa_nonce"); ?>',
                question_number: questionNumber,
                score: score,
                answer: answer,
                feedback: feedback
            };
            
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    console.log('✅ Puntuación guardada en sesión PHP:', result.data);
                } else {
                    console.error('❌ Error al guardar puntuación:', result.data);
                }
            })
            .catch(error => {
                console.error('❌ Error de conexión:', error);
            });
        }
        
        // Función para obtener puntuaciones desde sesión PHP
        function getEvaluationScoresFromSession() {
            return new Promise((resolve, reject) => {
                const data = {
                    action: 'wpsa_get_evaluation_scores',
                    nonce: '<?php echo wp_create_nonce("wpsa_nonce"); ?>'
                };
                
                fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        console.log('✅ Puntuaciones obtenidas desde sesión PHP:', result.data);
                        resolve(result.data);
                    } else {
                        console.error('❌ Error al obtener puntuaciones:', result.data);
                        reject(result.data);
                    }
                })
                .catch(error => {
                    console.error('❌ Error de conexión:', error);
                    reject(error);
                });
            });
        }
        
        // Función para guardar evaluación final en BD (simplificada)
        function saveFinalEvaluationToDatabase(results) {
            const data = {
                action: 'wpsa_save_final_evaluation',
                nonce: '<?php echo wp_create_nonce("wpsa_nonce"); ?>',
                materia_id: window.evaluationData.materia_id,
                estudiante_nombre: window.evaluationData.estudiante_nombre || 'Anónimo',
                tema: window.evaluationData.tema || 'General',
                modalidad: window.evaluationData.modalidad,
                nivel: window.evaluationData.nivel,
                evaluation_id: window.evaluationData.evaluation_id || null
            };

            console.log('💾 Enviando datos a BD:', {
                materia_id: data.materia_id,
                estudiante: data.estudiante_nombre,
                tema: data.tema,
                modalidad: data.modalidad,
                nivel: data.nivel,
                evaluation_id: data.evaluation_id
            });

            // Verificar que los datos sean válidos antes de enviar
            if (!data.materia_id || !data.estudiante_nombre) {
                console.error('❌ Datos de evaluación incompletos:', data);
                throw new Error('Datos de evaluación incompletos');
            }

            return fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    console.log('✅ Evaluación guardada en BD:', result.data);

                    // Mostrar resultados inmediatamente
                    displayFinalResults(result.data);

                    return result.data;
                } else {
                    console.error('❌ Error al guardar evaluación:', result.data);
                    throw new Error(result.data || 'Error desconocido al guardar evaluación');
                }
            })
            .catch(error => {
                console.error('❌ Error de conexión:', error);
                throw error;
            });
        }

        // Función para mostrar resultados finales
        function displayFinalResults(data) {
            console.log('📊 Mostrando resultados finales:', data);

            const finalScoreElement = document.getElementById('wpsa-final-score');
            const totalScoreElement = document.getElementById('wpsa-total-score');
            const percentageElement = document.getElementById('wpsa-percentage');
            const recommendationsElement = document.getElementById('wpsa-recommendations-content');

            if (data.puntuacion_obtenida !== undefined && data.puntuacion_total !== undefined && data.porcentaje !== undefined) {
                if (finalScoreElement) {
                    finalScoreElement.textContent = data.puntuacion_obtenida.toString();
                }
                if (totalScoreElement) {
                    totalScoreElement.textContent = data.puntuacion_total.toString();
                }
                if (percentageElement) {
                    // Formatear porcentaje correctamente
                    const percentage = parseFloat(data.porcentaje);
                    percentageElement.textContent = (isNaN(percentage) ? 0 : percentage.toFixed(2)) + '%';
                }

                // Mostrar recomendaciones
                if (recommendationsElement && data.recomendaciones) {
                    recommendationsElement.innerHTML = data.recomendaciones;
                }

                console.log('✅ Resultados mostrados correctamente');
            } else {
                console.error('❌ Datos de resultados incompletos:', data);
            }
        }

        // Función para obtener resultados de evaluación existente cuando se detecta duplicado
        function getExistingEvaluationResults() {
            // Buscar la evaluación completada más reciente para este estudiante y materia
            const data = {
                action: 'wpsa_get_evaluation_details',
                nonce: '<?php echo wp_create_nonce("wpsa_nonce"); ?>',
                evaluation_id: 'latest_completed',
                materia_id: window.evaluationData.materia_id,
                estudiante_nombre: window.evaluationData.estudiante_nombre || 'Anónimo'
            };

            console.log('🔍 Buscando evaluación existente:', data);

            return fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data) {
                    console.log('✅ Evaluación existente encontrada:', result.data);

                    // Mostrar resultados usando la función unificada
                    displayFinalResults(result.data);

                    // Ir al paso 4
                    document.getElementById('wpsa-step-3').classList.remove('active');
                    document.getElementById('wpsa-step-4').classList.add('active');
                    currentStep = 4;

                    console.log('🎯 Resultados mostrados desde evaluación existente');
                } else {
                    console.error('❌ No se pudo obtener evaluación existente');
                    showEvaluationError();
                    evaluationSaving = false; // Reset flag
                }
            })
            .catch(error => {
                console.error('❌ Error al obtener evaluación existente:', error);
                showEvaluationError();
                evaluationSaving = false; // Reset flag
            });
        }
        </script>
        
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Estilos para formateo de texto de la IA */
        .wpsa-code-block {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 16px;
            margin: 12px 0;
            overflow-x: auto;
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            line-height: 1.4;
            color: #333;
        }
        
        .wpsa-code-block code {
            background: none;
            padding: 0;
            border: none;
            font-family: inherit;
            font-size: inherit;
            color: inherit;
        }
        
        .wpsa-inline-code {
            background-color: #f1f3f4;
            border: 1px solid #dadce0;
            border-radius: 3px;
            padding: 2px 6px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.9em;
            color: #d63384;
        }
        
        .wpsa-question-content strong {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .wpsa-question-content em {
            font-style: italic;
            color: #6c757d;
        }
        
        .wpsa-question-content ul {
            margin: 12px 0;
            padding-left: 20px;
        }
        
        .wpsa-question-content ol {
            margin: 12px 0;
            padding-left: 20px;
        }
        
        .wpsa-question-content li {
            margin: 4px 0;
            line-height: 1.5;
        }
        
        .wpsa-question-content br {
            line-height: 1.6;
        }
        </style>
        <?php
        return ob_get_clean();
    }
}
