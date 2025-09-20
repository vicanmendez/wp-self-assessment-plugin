/**
 * JavaScript para el frontend del plugin WP Self Assessment
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Variables globales
    let currentStep = 1;
    let selectedMateria = null;
    let currentEvaluation = {
        materia_id: null,
        estudiante_nombre: '',
        tema: '',
        nivel: 'inicial',
        modalidad: '',
        preguntas: [],
        currentQuestionIndex: 0
    };
    
    // Inicializar
    init();
    
    function init() {
        bindEvents();
        loadRecaptcha();
    }
    
    function bindEvents() {
        // Selección de materia
        $(document).on('click', '.wpsa-materia-card', function() {
            selectMateria($(this));
        });
        
        // Navegación entre pasos
        $(document).on('click', '#wpsa-start-evaluation', function() {
            startEvaluation();
        });
        
        $(document).on('click', '#wpsa-back-step-1', function() {
            goToStep(1);
        });
        
        // Envío de respuesta
        $(document).on('click', '#wpsa-submit-answer', function() {
            submitAnswer();
        });
        
        $(document).on('click', '#wpsa-skip-question', function() {
            skipQuestion();
        });
        
        // Navegación de preguntas
        $(document).on('click', '#wpsa-next-question', function() {
            nextQuestion();
        });
        
        $(document).on('click', '#wpsa-finish-evaluation', function() {
            finishEvaluation();
        });
        
        // Nueva evaluación
        $(document).on('click', '#wpsa-new-evaluation', function() {
            resetEvaluation();
        });
        
        // Descargar resultados
        $(document).on('click', '#wpsa-download-results', function() {
            downloadResults();
        });
    }
    
    function selectMateria($card) {
        $('.wpsa-materia-card').removeClass('selected');
        $card.addClass('selected');
        
        selectedMateria = {
            id: $card.data('materia-id'),
            nombre: $card.find('h4').text(),
            grado: $card.find('.wpsa-grado').text()
        };
        
        // Habilitar botón de continuar
        $('#wpsa-start-evaluation').prop('disabled', false);
    }
    
    function startEvaluation() {
        if (!selectedMateria) {
            alert('Por favor selecciona una materia');
            return;
        }
        
        // Recopilar datos del formulario
        currentEvaluation.materia_id = selectedMateria.id;
        currentEvaluation.estudiante_nombre = $('#wpsa-estudiante-nombre').val();
        currentEvaluation.tema = $('#wpsa-tema').val();
        currentEvaluation.nivel = $('input[name="nivel"]:checked').val();
        currentEvaluation.modalidad = $('input[name="modalidad"]:checked').val();
        
        if (!currentEvaluation.modalidad) {
            alert('Por favor selecciona una modalidad de evaluación');
            return;
        }
        
        if (!currentEvaluation.nivel) {
            alert('Por favor selecciona un nivel de dificultad');
            return;
        }
        
        // Actualizar información mostrada
        $('#wpsa-current-materia').text(selectedMateria.nombre);
        $('#wpsa-current-tema').text(currentEvaluation.tema || 'Todo el programa');
        $('#wpsa-current-nivel').text(getNivelName(currentEvaluation.nivel));
        $('#wpsa-current-modalidad').text(getModalidadName(currentEvaluation.modalidad));
        
        // Ir al paso 3 y generar primera pregunta
        goToStep(3);
        generateQuestion();
    }
    
    function generateQuestion() {
        showLoading();
        
        // Verificar reCAPTCHA v3 antes de enviar
        verifyRecaptcha().then(function(recaptchaToken) {
            $.ajax({
                url: wpsa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsa_generate_question',
                    materia_id: currentEvaluation.materia_id,
                    tema: currentEvaluation.tema,
                    modalidad: currentEvaluation.modalidad,
                    nivel: currentEvaluation.nivel,
                    numero_pregunta: currentEvaluation.currentQuestionIndex + 1,
                    nonce: wpsa_ajax.nonce,
                    recaptcha_token: recaptchaToken
                },
                success: function(response) {
                    hideLoading();
                    
                    if (response.success) {
                        displayQuestion(response.data);
                    } else {
                        showError('Error al generar la pregunta: ' + response.data);
                    }
                },
                error: function() {
                    hideLoading();
                    showError('Error de conexión. Por favor intenta de nuevo.');
                }
            });
        }).catch(function(error) {
            hideLoading();
            showError('Error de reCAPTCHA: ' + error);
        });
    }
    
    function displayQuestion(questionData) {
        $('#wpsa-question-text').text('Pregunta ' + (currentEvaluation.currentQuestionIndex + 1));
        $('#wpsa-question-content').html(questionData.pregunta);
        $('#wpsa-answer').val('');
        
        // Guardar datos de la pregunta
        currentEvaluation.preguntas[currentEvaluation.currentQuestionIndex] = {
            pregunta: questionData.pregunta,
            respuesta_correcta: questionData.respuesta_correcta,
            puntuacion: questionData.puntuacion,
            dificultad: questionData.dificultad,
            respuesta_estudiante: '',
            puntuacion_obtenida: 0,
            feedback: ''
        };
        
        // Actualizar contador
        $('#wpsa-question-counter').text(currentEvaluation.currentQuestionIndex + 1);
        
        // Mostrar botones de acción
        $('#wpsa-submit-answer, #wpsa-skip-question').show();
        $('#wpsa-next-question').hide();
    }
    
    function submitAnswer() {
        const respuesta = $('#wpsa-answer').val().trim();
        
        if (!respuesta) {
            alert('Por favor escribe una respuesta antes de continuar');
            return;
        }
        
        showLoading();
        
        const currentQuestion = currentEvaluation.preguntas[currentEvaluation.currentQuestionIndex];
        
        // Verificar reCAPTCHA v3 antes de enviar
        verifyRecaptcha().then(function(recaptchaToken) {
            $.ajax({
                url: wpsa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsa_evaluate_answer',
                    pregunta: currentQuestion.pregunta,
                    respuesta_estudiante: respuesta,
                    respuesta_correcta: currentQuestion.respuesta_correcta,
                    nonce: wpsa_ajax.nonce,
                    recaptcha_token: recaptchaToken
                },
                success: function(response) {
                    hideLoading();
                    
                    if (response.success) {
                        // Guardar respuesta y evaluación
                        currentQuestion.respuesta_estudiante = respuesta;
                        currentQuestion.puntuacion_obtenida = response.data.puntuacion;
                        currentQuestion.feedback = response.data.feedback;
                        
                        // Mostrar feedback
                        showFeedback(response.data);
                    } else {
                        showError('Error al evaluar la respuesta: ' + response.data);
                    }
                },
                error: function() {
                    hideLoading();
                    showError('Error de conexión. Por favor intenta de nuevo.');
                }
            });
        }).catch(function(error) {
            hideLoading();
            showError('Error de reCAPTCHA: ' + error);
        });
    }
    
    function showFeedback(evaluationData) {
        const feedbackHtml = `
            <div class="wpsa-feedback">
                <h4>Evaluación de tu respuesta:</h4>
                <div class="wpsa-score-display">
                    Puntuación: ${evaluationData.puntuacion}/10
                </div>
                <div class="wpsa-feedback-content">
                    <strong>Comentarios:</strong><br>
                    ${evaluationData.feedback}
                </div>
                ${evaluationData.recomendaciones ? `
                    <div class="wpsa-recommendations">
                        <strong>Recomendaciones:</strong><br>
                        ${evaluationData.recomendaciones}
                    </div>
                ` : ''}
            </div>
        `;
        
        $('#wpsa-question-content').append(feedbackHtml);
        
        // Ocultar botones de respuesta y mostrar siguiente
        $('#wpsa-submit-answer, #wpsa-skip-question').hide();
        $('#wpsa-next-question').show();
    }
    
    function skipQuestion() {
        const currentQuestion = currentEvaluation.preguntas[currentEvaluation.currentQuestionIndex];
        currentQuestion.respuesta_estudiante = '';
        currentQuestion.puntuacion_obtenida = 0;
        currentQuestion.feedback = 'Pregunta omitida';
        
        nextQuestion();
    }
    
    function nextQuestion() {
        currentEvaluation.currentQuestionIndex++;
        
        // Verificar si hemos alcanzado el límite máximo
        const maxQuestions = parseInt(wpsa_ajax.max_questions || 10);
        if (currentEvaluation.currentQuestionIndex >= maxQuestions) {
            finishEvaluation();
            return;
        }
        
        // Generar siguiente pregunta
        generateQuestion();
    }
    
    function finishEvaluation() {
        showLoading();
        
        // Obtener puntuaciones desde sesión PHP
        getEvaluationScoresFromSession()
            .then(scores => {
                console.log('📊 Puntuaciones obtenidas desde sesión PHP:', scores);
                
                const obtainedScore = scores.obtained_score;
                const totalScore = scores.total_score;
                const percentage = scores.percentage;
                
                // Validar que el porcentaje sea válido
                if (isNaN(percentage) || percentage < 0 || percentage > 100) {
                    showEvaluationError();
                    return;
                }

                // Generar recomendaciones didácticas basadas en el porcentaje y materia
                const recommendations = generateDetailedRecommendations(percentage, currentEvaluation);

                // Guardar evaluación final en la base de datos
                saveFinalEvaluationToDatabase()
                    .then(result => {
                        console.log('✅ Evaluación guardada en BD:', result);
                        
                        // Ir al paso 4
                        goToStep(4);

                        // Mostrar resultados - USAR VARIABLES LOCALES Y GUARDAR EN WINDOW
                        window.finalScores = {
                            obtained: obtainedScore,
                            total: totalScore,
                            percentage: percentage
                        };

                        document.getElementById('wpsa-final-score').textContent = obtainedScore;
                        document.getElementById('wpsa-total-score').textContent = totalScore;
                        document.getElementById('wpsa-percentage').textContent = percentage;

                        // Guardar recomendaciones en variable global
                        window.finalRecommendations = recommendations;

                        // Mostrar recomendaciones detalladas
                        document.getElementById('wpsa-recommendations-content').innerHTML = recommendations;

                        // Debug: verificar que los valores se mantienen
                        console.log('🎯 Puntuación final desde sesión PHP:', obtainedScore, 'de', totalScore, '=', percentage + '%');

                        // Verificar y restaurar valores cada 500ms
                        const restoreInterval = setInterval(() => {
                            const finalScore = document.getElementById('wpsa-final-score').textContent;
                            const totalScoreDisplay = document.getElementById('wpsa-total-score').textContent;
                            const percentageDisplay = document.getElementById('wpsa-percentage').textContent;
                            const recommendationsContent = document.getElementById('wpsa-recommendations-content').innerHTML;

                            if (finalScore === '0' || percentageDisplay === '0' || percentageDisplay === 'NaN' || finalScore === 'Error') {
                                console.log('⚠️ Valores reseteados! Restaurando desde sesión PHP...');
                                document.getElementById('wpsa-final-score').textContent = window.finalScores.obtained;
                                document.getElementById('wpsa-total-score').textContent = window.finalScores.total;
                                document.getElementById('wpsa-percentage').textContent = window.finalScores.percentage;
                            }

                            // Restaurar recomendaciones si se borraron
                            if (!recommendationsContent || recommendationsContent.includes('No hay recomendaciones disponibles')) {
                                console.log('⚠️ Recomendaciones borradas! Restaurando...');
                                document.getElementById('wpsa-recommendations-content').innerHTML = window.finalRecommendations;
                            }
                        }, 500);

                        // Limpiar el intervalo después de 10 segundos
                        setTimeout(() => {
                            clearInterval(restoreInterval);
                        }, 10000);
                    })
                    .catch(error => {
                        console.error('❌ Error al guardar evaluación final:', error);
                        showEvaluationError();
                    });
            })
            .catch(error => {
                console.error('❌ Error al obtener puntuaciones desde sesión PHP:', error);
                showEvaluationError();
            });
    }
    
    function showResults(resultsData) {
        // Actualizar puntuación
        $('#wpsa-final-score').text(resultsData.puntuacion_obtenida);
        $('#wpsa-total-score').text(resultsData.puntuacion_total);
        $('#wpsa-percentage').text(resultsData.porcentaje);
        
        // Mostrar recomendaciones
        $('#wpsa-recommendations-content').html(resultsData.recomendaciones);
        
        // Ir al paso 4
        goToStep(4);
    }
    
    function resetEvaluation() {
        currentEvaluation = {
            materia_id: null,
            estudiante_nombre: '',
            tema: '',
            nivel: 'inicial',
            modalidad: '',
            preguntas: [],
            currentQuestionIndex: 0
        };
        
        selectedMateria = null;
        currentStep = 1;
        
        // Limpiar formularios
        $('#wpsa-estudiante-nombre').val('');
        $('#wpsa-tema').val('');
        $('input[name="nivel"]').prop('checked', false);
        $('input[name="nivel"][value="inicial"]').prop('checked', true);
        $('input[name="modalidad"]').prop('checked', false);
        $('.wpsa-materia-card').removeClass('selected');
        
        // Ir al paso 1
        goToStep(1);
    }
    
    function downloadResults() {
        // Implementar descarga de resultados
        alert('Funcionalidad de descarga en desarrollo');
    }
    
    function goToStep(step) {
        $('.wpsa-step').removeClass('active');
        $('#wpsa-step-' + step).addClass('active');
        currentStep = step;
    }
    
    function showLoading() {
        $('.wpsa-question-container').addClass('wpsa-loading');
        $('#wpsa-question-text').text('Cargando...');
    }
    
    function hideLoading() {
        $('.wpsa-question-container').removeClass('wpsa-loading');
    }
    
    function showError(message) {
        const errorHtml = `
            <div class="wpsa-error">
                ${message}
            </div>
        `;
        
        $('.wpsa-container').prepend(errorHtml);
        
        // Remover error después de 5 segundos
        setTimeout(function() {
            $('.wpsa-error').fadeOut();
        }, 5000);
    }
    
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
            'codigo': 'Análisis de Código'
        };
        
        return modalidades[modalidad] || modalidad;
    }
    
    function loadRecaptcha() {
        const siteKey = wpsa_ajax.recaptcha_site_key;
        
        if (siteKey) {
            // Cargar script de reCAPTCHA v3
            if (!window.grecaptcha) {
                const script = document.createElement('script');
                script.src = 'https://www.google.com/recaptcha/api.js?render=' + siteKey;
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
            }
        }
    }
    
    // Verificar reCAPTCHA v3 antes de enviar
    function verifyRecaptcha() {
        const siteKey = wpsa_ajax.recaptcha_site_key;
        
        if (!siteKey) {
            return Promise.resolve(true); // Si no está configurado, permitir continuar
        }
        
        if (!window.grecaptcha) {
            return Promise.reject('reCAPTCHA no está cargado');
        }
        
        return new Promise((resolve, reject) => {
            grecaptcha.ready(function() {
                grecaptcha.execute(siteKey, { action: 'submit' }).then(function(token) {
                    if (token) {
                        resolve(token);
                    } else {
                        reject('No se pudo obtener token de reCAPTCHA');
                    }
                }).catch(function(error) {
                    reject('Error al ejecutar reCAPTCHA: ' + error);
                });
            });
        });
    }
    
    // Función para obtener puntuaciones desde sesión PHP
    function getEvaluationScoresFromSession() {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: wpsa_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpsa_get_evaluation_scores',
                    nonce: wpsa_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Puntuaciones obtenidas desde sesión PHP:', response.data);
                        resolve(response.data);
                    } else {
                        console.error('❌ Error al obtener puntuaciones:', response.data);
                        reject(response.data);
                    }
                },
                error: function(error) {
                    console.error('❌ Error de conexión:', error);
                    reject(error);
                }
            });
        });
    }
    
    // Función para guardar evaluación final en BD usando sesión PHP
    function saveFinalEvaluationToDatabase() {
        return new Promise((resolve, reject) => {
            // Verificar reCAPTCHA v3 antes de enviar
            verifyRecaptcha().then(function(recaptchaToken) {
                $.ajax({
                    url: wpsa_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wpsa_save_final_evaluation',
                        nonce: wpsa_ajax.nonce,
                        materia_id: currentEvaluation.materia_id,
                        estudiante_nombre: currentEvaluation.estudiante_nombre || 'Anónimo',
                        tema: currentEvaluation.tema || 'General',
                        modalidad: currentEvaluation.modalidad,
                        nivel: currentEvaluation.nivel,
                        recaptcha_token: recaptchaToken
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('✅ Evaluación guardada en BD:', response.data);
                            resolve(response.data);
                        } else {
                            console.error('❌ Error al guardar evaluación:', response.data);
                            reject(response.data);
                        }
                    },
                    error: function(error) {
                        console.error('❌ Error de conexión:', error);
                        reject(error);
                    }
                });
            }).catch(function(error) {
                console.error('❌ Error de reCAPTCHA:', error);
                reject(error);
            });
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
        
        return recommendations;
    }
    
    // Función para mostrar error de evaluación
    function showEvaluationError() {
        // Ir al paso 4
        goToStep(4);
        
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
                <button onclick="resetEvaluation()" style="background: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                    Intentar Nuevamente
                </button>
            </div>
        `;
        
        document.getElementById('wpsa-recommendations-content').innerHTML = errorMessage;
    }
    
    // Exponer funciones globalmente si es necesario
    window.WPSA = {
        verifyRecaptcha: verifyRecaptcha
    };
});
