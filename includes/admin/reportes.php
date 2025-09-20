<?php
/**
 * Página de reportes
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Reportes de Autoevaluaciones', 'wp-self-assessment'); ?></h1>
    
    <!-- Filtros -->
    <div class="wpsa-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wpsa-reportes" />
            
            <div class="wpsa-filter-group">
                <label for="materia_id"><?php _e('Materia:', 'wp-self-assessment'); ?></label>
                <select name="materia_id" id="materia_id">
                    <option value=""><?php _e('Todas las materias', 'wp-self-assessment'); ?></option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?php echo esc_attr($materia->id); ?>" <?php selected(isset($_GET['materia_id']) ? $_GET['materia_id'] : '', $materia->id); ?>>
                            <?php echo esc_html($materia->nombre . ' (' . $materia->grado . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="wpsa-filter-group">
                <label for="fecha_desde"><?php _e('Fecha Desde:', 'wp-self-assessment'); ?></label>
                <input type="date" name="fecha_desde" id="fecha_desde" value="<?php echo esc_attr(isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : ''); ?>" />
            </div>
            
            <div class="wpsa-filter-group">
                <label for="fecha_hasta"><?php _e('Fecha Hasta:', 'wp-self-assessment'); ?></label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?php echo esc_attr(isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : ''); ?>" />
            </div>
            
            
            <div class="wpsa-filter-group">
                <input type="submit" class="button button-primary" value="<?php _e('Aplicar Filtros', 'wp-self-assessment'); ?>" />
                <a href="<?php echo admin_url('admin.php?page=wpsa-reportes'); ?>" class="button">
                    <?php _e('Limpiar', 'wp-self-assessment'); ?>
                </a>
            </div>
        </form>
    </div>
    
    <!-- Estadísticas generales -->
    <div class="wpsa-stats-grid">
        <div class="wpsa-stat-card">
            <h3><?php _e('Total Autoevaluaciones', 'wp-self-assessment'); ?></h3>
            <div class="wpsa-stat-number"><?php echo esc_html($estadisticas->total_autoevaluaciones ?? 0); ?></div>
        </div>
        
        <div class="wpsa-stat-card">
            <h3><?php _e('Completadas', 'wp-self-assessment'); ?></h3>
            <div class="wpsa-stat-number"><?php echo esc_html($estadisticas->completadas ?? 0); ?></div>
            <div class="wpsa-stat-percentage">
                <?php 
                $total = $estadisticas->total_autoevaluaciones ?? 1;
                $completadas = $estadisticas->completadas ?? 0;
                echo esc_html(round(($completadas / $total) * 100, 1)); 
                ?>%
            </div>
        </div>
        
        <div class="wpsa-stat-card">
            <h3><?php _e('En Progreso', 'wp-self-assessment'); ?></h3>
            <div class="wpsa-stat-number"><?php echo esc_html($estadisticas->en_progreso ?? 0); ?></div>
            <div class="wpsa-stat-percentage">
                <?php 
                $en_progreso = $estadisticas->en_progreso ?? 0;
                echo esc_html(round(($en_progreso / $total) * 100, 1)); 
                ?>%
            </div>
        </div>
        
        <div class="wpsa-stat-card">
            <h3><?php _e('Promedio General', 'wp-self-assessment'); ?></h3>
            <div class="wpsa-stat-number"><?php echo esc_html(number_format($estadisticas->promedio_porcentaje ?? 0, 1)); ?>%</div>
        </div>
        
        <div class="wpsa-stat-card">
            <h3><?php _e('Estudiantes Únicos', 'wp-self-assessment'); ?></h3>
            <div class="wpsa-stat-number"><?php echo esc_html($estadisticas->estudiantes_unicos ?? 0); ?></div>
        </div>
        
        <div class="wpsa-stat-card">
            <h3><?php _e('Tasa de Finalización', 'wp-self-assessment'); ?></h3>
            <div class="wpsa-stat-number">
                <?php 
                $tasa = $total > 0 ? round(($completadas / $total) * 100, 1) : 0;
                echo esc_html($tasa); 
                ?>%
            </div>
        </div>
    </div>
    
    <!-- Lista de autoevaluaciones -->
    <div class="wpsa-evaluations-section">
        <h2><?php _e('Detalle de Autoevaluaciones', 'wp-self-assessment'); ?></h2>
        
        <?php if (!empty($autoevaluaciones)): ?>
            <div class="wpsa-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php _e('ID', 'wp-self-assessment'); ?></th>
                            <th scope="col"><?php _e('Estudiante', 'wp-self-assessment'); ?></th>
                            <th scope="col"><?php _e('Materia', 'wp-self-assessment'); ?></th>
                            <th scope="col"><?php _e('Grado', 'wp-self-assessment'); ?></th>
                            <th scope="col"><?php _e('Tema', 'wp-self-assessment'); ?></th>
                            <th scope="col"><?php _e('Modalidad', 'wp-self-assessment'); ?></th>
                            <th scope="col"><?php _e('Puntuación', 'wp-self-assessment'); ?></th>
                            <th scope="col"><?php _e('Fecha', 'wp-self-assessment'); ?></th>
                            <th scope="col"><?php _e('Acciones', 'wp-self-assessment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($autoevaluaciones as $autoeval): ?>
                            <tr>
                                <td><?php echo esc_html($autoeval->id); ?></td>
                                <td>
                                    <?php if (!empty($autoeval->estudiante_nombre)): ?>
                                        <?php echo esc_html($autoeval->estudiante_nombre); ?>
                                    <?php else: ?>
                                        <span class="wpsa-anonymous"><?php _e('Anónimo', 'wp-self-assessment'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($autoeval->materia_nombre); ?></td>
                                <td>
                                    <span class="wpsa-grade-badge"><?php echo esc_html($autoeval->grado); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($autoeval->tema)): ?>
                                        <?php echo esc_html($autoeval->tema); ?>
                                    <?php else: ?>
                                        <span class="wpsa-no-data"><?php _e('General', 'wp-self-assessment'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $modalidades = array(
                                        'preguntas_simples' => __('Preguntas', 'wp-self-assessment'),
                                        'ejercicios' => __('Ejercicios', 'wp-self-assessment'),
                                        'codigo' => __('Código', 'wp-self-assessment')
                                    );
                                    echo esc_html($modalidades[$autoeval->modalidad] ?? $autoeval->modalidad);
                                    ?>
                                </td>
                                <td>
                                    <?php if ($autoeval->estado === 'completada'): ?>
                                        <div class="wpsa-score">
                                            <span class="wpsa-score-value"><?php echo esc_html($autoeval->puntuacion_obtenida . '/' . $autoeval->puntuacion_total); ?></span>
                                            <span class="wpsa-score-percentage">(<?php echo esc_html($autoeval->porcentaje); ?>%)</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="wpsa-in-progress"><?php _e('En progreso', 'wp-self-assessment'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="wpsa-date">
                                        <div class="wpsa-date-main"><?php echo esc_html(date_i18n('d/m/Y', strtotime($autoeval->created_at))); ?></div>
                                        <div class="wpsa-date-time"><?php echo esc_html(date_i18n('H:i', strtotime($autoeval->created_at))); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wpsa-actions">
                                        <button type="button" 
                                                class="button button-small wpsa-view-details" 
                                                data-id="<?php echo esc_attr($autoeval->id); ?>">
                                            <?php _e('Ver', 'wp-self-assessment'); ?>
                                        </button>
                                        <?php if ($autoeval->estado === 'completada'): ?>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=wpsa_export_pdf&evaluation_id=' . $autoeval->id), 'wpsa_export_nonce', 'nonce'); ?>" 
                                               class="button button-small wpsa-download-report" 
                                               data-id="<?php echo esc_attr($autoeval->id); ?>">
                                                <?php _e('PDF', 'wp-self-assessment'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <div class="wpsa-pagination">
                <p>
                    <?php 
                    printf(
                        _n('Mostrando %d autoevaluación', 'Mostrando %d autoevaluaciones', count($autoevaluaciones), 'wp-self-assessment'),
                        count($autoevaluaciones)
                    );
                    ?>
                </p>
            </div>
            
        <?php else: ?>
            <div class="wpsa-no-data-message">
                <h3><?php _e('No se encontraron autoevaluaciones', 'wp-self-assessment'); ?></h3>
                <p><?php _e('No hay autoevaluaciones que coincidan con los filtros aplicados.', 'wp-self-assessment'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Exportar datos -->
    <div class="wpsa-export-section">
        <h2><?php _e('Exportar Datos', 'wp-self-assessment'); ?></h2>
        <div class="wpsa-export-actions">
            <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=wpsa_export_csv&' . http_build_query($_GET)), 'wpsa_export_nonce', 'nonce'); ?>" 
               class="button" id="export-csv">
                <?php _e('Exportar CSV', 'wp-self-assessment'); ?>
            </a>
            <a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=wpsa_export_excel&' . http_build_query($_GET)), 'wpsa_export_nonce', 'nonce'); ?>" 
               class="button" id="export-excel">
                <?php _e('Exportar Excel', 'wp-self-assessment'); ?>
            </a>
            <button type="button" id="generate-report" class="button button-primary">
                <?php _e('Generar Reporte Completo', 'wp-self-assessment'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal de detalles con iframe -->
<div id="wpsa-details-modal" class="wpsa-modal-iframe" style="display: none;">
    <div class="wpsa-modal-iframe-container">
        <div class="wpsa-modal-iframe-header">
            <h3><?php _e('Detalles de Autoevaluación', 'wp-self-assessment'); ?></h3>
            <button type="button" id="close-details" class="wpsa-close-button" title="<?php _e('Cerrar', 'wp-self-assessment'); ?>">
                <span class="wpsa-close-icon">&times;</span>
            </button>
        </div>
        <div class="wpsa-modal-iframe-content">
            <iframe id="wpsa-details-iframe" src="about:blank" frameborder="0"></iframe>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var detailsModal = $('#wpsa-details-modal');
    
    // Ver detalles
    $('.wpsa-view-details').on('click', function() {
        var autoevalId = $(this).data('id');
        
        // Mostrar modal
        detailsModal.show();
        
        // Crear URL para el iframe
        var iframeUrl = ajaxurl + '?action=wpsa_get_evaluation_details&evaluation_id=' + autoevalId + '&nonce=<?php echo wp_create_nonce('wpsa_nonce'); ?>&format=html';
        
        // Cargar contenido en iframe
        $('#wpsa-details-iframe').attr('src', iframeUrl);
    });
    
    // Función para mostrar los detalles de la evaluación
    function displayEvaluationDetails(data) {
        var modalidades = {
            'preguntas_simples': '<?php _e('Preguntas', 'wp-self-assessment'); ?>',
            'ejercicios': '<?php _e('Ejercicios', 'wp-self-assessment'); ?>',
            'codigo': '<?php _e('Código', 'wp-self-assessment'); ?>'
        };
        
        var estados = {
            'en_progreso': '<?php _e('En Progreso', 'wp-self-assessment'); ?>',
            'completada': '<?php _e('Completada', 'wp-self-assessment'); ?>',
            'cancelada': '<?php _e('Cancelada', 'wp-self-assessment'); ?>'
        };
        
        var html = '<div class="wpsa-evaluation-details">';
        html += '<h3><?php _e('Información General', 'wp-self-assessment'); ?></h3>';
        html += '<table class="wpsa-details-table">';
        html += '<tr><th><?php _e('ID', 'wp-self-assessment'); ?>:</th><td>' + data.id + '</td></tr>';
        html += '<tr><th><?php _e('Estudiante', 'wp-self-assessment'); ?>:</th><td>' + data.estudiante_nombre + '</td></tr>';
        html += '<tr><th><?php _e('Materia', 'wp-self-assessment'); ?>:</th><td>' + data.materia_nombre + '</td></tr>';
        html += '<tr><th><?php _e('Grado', 'wp-self-assessment'); ?>:</th><td>' + data.grado + '</td></tr>';
        html += '<tr><th><?php _e('Tema', 'wp-self-assessment'); ?>:</th><td>' + data.tema + '</td></tr>';
        html += '<tr><th><?php _e('Modalidad', 'wp-self-assessment'); ?>:</th><td>' + (modalidades[data.modalidad] || data.modalidad) + '</td></tr>';
        html += '<tr><th><?php _e('Puntuación', 'wp-self-assessment'); ?>:</th><td><strong>' + data.puntuacion_obtenida + '/' + data.puntuacion_total + ' (' + data.porcentaje + '%)</strong></td></tr>';
        html += '<tr><th><?php _e('Fecha de Creación', 'wp-self-assessment'); ?>:</th><td>' + data.created_at + '</td></tr>';
        if (data.completed_at) {
            html += '<tr><th><?php _e('Fecha de Finalización', 'wp-self-assessment'); ?>:</th><td>' + data.completed_at + '</td></tr>';
        }
        html += '</table>';
        
        if (data.preguntas_respuestas && data.preguntas_respuestas.length > 0) {
            html += '<h3><?php _e('Preguntas y Respuestas', 'wp-self-assessment'); ?></h3>';
            html += '<div class="wpsa-questions-list">';
            
            data.preguntas_respuestas.forEach(function(pregunta, index) {
                html += '<div class="wpsa-question-detail">';
                html += '<h4><?php _e('Pregunta', 'wp-self-assessment'); ?> ' + (index + 1) + '</h4>';
                html += '<div class="wpsa-question-text">' + pregunta.pregunta.replace(/\n/g, '<br>') + '</div>';
                
                html += '<div class="wpsa-answer-section">';
                html += '<strong><?php _e('Respuesta del Estudiante', 'wp-self-assessment'); ?>:</strong><br>';
                html += '<div class="wpsa-answer-text">' + (pregunta.respuesta_estudiante ? pregunta.respuesta_estudiante.replace(/\n/g, '<br>') : '<?php _e('Sin respuesta', 'wp-self-assessment'); ?>') + '</div>';
                html += '</div>';
                
                if (pregunta.respuesta_correcta) {
                    html += '<div class="wpsa-answer-section">';
                    html += '<strong><?php _e('Respuesta Correcta', 'wp-self-assessment'); ?>:</strong><br>';
                    html += '<div class="wpsa-answer-text">' + pregunta.respuesta_correcta.replace(/\n/g, '<br>') + '</div>';
                    html += '</div>';
                }
                
                html += '<div class="wpsa-score-section">';
                html += '<strong><?php _e('Puntuación', 'wp-self-assessment'); ?>:</strong> <span class="wpsa-score">' + pregunta.puntuacion_obtenida + '/' + pregunta.puntuacion + '</span>';
                html += '</div>';
                
                if (pregunta.feedback) {
                    html += '<div class="wpsa-feedback-section">';
                    html += '<strong><?php _e('Comentarios', 'wp-self-assessment'); ?>:</strong><br>';
                    html += '<div class="wpsa-feedback-text">' + pregunta.feedback.replace(/\n/g, '<br>') + '</div>';
                    html += '</div>';
                }
                
                html += '</div>';
            });
            
            html += '</div>';
        }
        
        if (data.recomendaciones) {
            html += '<h3><?php _e('Recomendaciones', 'wp-self-assessment'); ?></h3>';
            html += '<div class="wpsa-recommendations">' + data.recomendaciones.replace(/\n/g, '<br>') + '</div>';
        }
        
        html += '</div>';
        
        $('#wpsa-details-content').html(html);
    }
    
    // Cerrar modal
    $('#close-details').on('click', function() {
        detailsModal.hide();
        // Limpiar iframe
        $('#wpsa-details-iframe').attr('src', 'about:blank');
    });
    
    // Cerrar modal al hacer clic fuera
    detailsModal.on('click', function(e) {
        if (e.target === this) {
            detailsModal.hide();
            // Limpiar iframe
            $('#wpsa-details-iframe').attr('src', 'about:blank');
        }
    });
    
    // Cerrar modal con tecla Escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && detailsModal.is(':visible')) {
            detailsModal.hide();
            // Limpiar iframe
            $('#wpsa-details-iframe').attr('src', 'about:blank');
        }
    });
    
    
    // Generar reporte completo
    $('#generate-report').on('click', function() {
        // Mostrar opciones de reporte
        var reportOptions = '<div class="wpsa-report-options">';
        reportOptions += '<h3><?php _e('Opciones de Reporte', 'wp-self-assessment'); ?></h3>';
        reportOptions += '<p><?php _e('Selecciona el tipo de reporte que deseas generar:', 'wp-self-assessment'); ?></p>';
        reportOptions += '<div class="wpsa-report-buttons">';
        reportOptions += '<a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=wpsa_export_csv&' . http_build_query($_GET)), 'wpsa_export_nonce', 'nonce'); ?>" class="button button-primary"><?php _e('Descargar CSV Completo', 'wp-self-assessment'); ?></a>';
        reportOptions += '<a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=wpsa_export_excel&' . http_build_query($_GET)), 'wpsa_export_nonce', 'nonce'); ?>" class="button button-primary"><?php _e('Descargar Excel Completo', 'wp-self-assessment'); ?></a>';
        reportOptions += '</div>';
        reportOptions += '</div>';
        
        $('#wpsa-details-content').html(reportOptions);
        detailsModal.show();
    });
});
</script>

<style>
.wpsa-filters {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 20px 0;
    display: flex;
    gap: 20px;
    align-items: end;
    flex-wrap: wrap;
}

.wpsa-filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.wpsa-filter-group label {
    font-weight: bold;
    color: #333;
}

.wpsa-filter-group input,
.wpsa-filter-group select {
    padding: 5px 10px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.wpsa-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.wpsa-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wpsa-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.wpsa-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
    margin: 0;
}

.wpsa-stat-percentage {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

.wpsa-evaluations-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.wpsa-table-container {
    overflow-x: auto;
}

.wpsa-grade-badge {
    background: #0073aa;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.wpsa-anonymous {
    color: #999;
    font-style: italic;
}

.wpsa-no-data {
    color: #999;
    font-style: italic;
}

.wpsa-score {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.wpsa-score-value {
    font-weight: bold;
    color: #0073aa;
}

.wpsa-score-percentage {
    font-size: 12px;
    color: #666;
}

.wpsa-in-progress {
    color: #666;
    font-style: italic;
}

.wpsa-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.wpsa-status-en_progreso {
    background: #fff3cd;
    color: #856404;
}

.wpsa-status-completada {
    background: #d4edda;
    color: #155724;
}

.wpsa-status-cancelada {
    background: #f8d7da;
    color: #721c24;
}

.wpsa-date {
    text-align: center;
}

.wpsa-date-main {
    font-weight: bold;
    color: #333;
}

.wpsa-date-time {
    font-size: 12px;
    color: #666;
}

.wpsa-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.wpsa-pagination {
    margin-top: 15px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
    color: #666;
    text-align: center;
}

.wpsa-no-data-message {
    text-align: center;
    padding: 40px 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 20px 0;
}

.wpsa-no-data-message h3 {
    color: #666;
    margin-bottom: 10px;
}

.wpsa-export-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.wpsa-export-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.wpsa-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wpsa-modal-content {
    background: white;
    padding: 30px;
    border-radius: 4px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.wpsa-modal-large {
    max-width: 800px;
}

.wpsa-modal-actions {
    margin-top: 20px;
    text-align: right;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.wp-list-table th {
    font-weight: bold;
}

.wp-list-table td {
    vertical-align: middle;
}

/* Estilos para el modal de detalles con iframe */
.wpsa-modal-iframe {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wpsa-modal-iframe-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    width: 95%;
    height: 90%;
    max-width: 1200px;
    max-height: 800px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.wpsa-modal-iframe-header {
    background: #0073aa;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.wpsa-modal-iframe-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.wpsa-close-button {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
    font-size: 18px;
    line-height: 1;
}

.wpsa-close-button:hover {
    background: rgba(255, 255, 255, 0.3);
}

.wpsa-close-icon {
    font-size: 20px;
    font-weight: bold;
}

.wpsa-modal-iframe-content {
    flex: 1;
    overflow: hidden;
}

.wpsa-modal-iframe-content iframe {
    width: 100%;
    height: 100%;
    border: none;
    background: white;
}

/* Estilos para el modal de detalles */
.wpsa-evaluation-details {
    max-height: 70vh;
    overflow-y: auto;
}


.wpsa-details-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.wpsa-details-table th,
.wpsa-details-table td {
    border: 1px solid #ddd;
    padding: 8px 12px;
    text-align: left;
}

.wpsa-details-table th {
    background-color: #f5f5f5;
    font-weight: bold;
    width: 30%;
}

.wpsa-questions-list {
    margin-top: 20px;
}

.wpsa-question-detail {
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 15px;
    background-color: #fafafa;
}

.wpsa-question-detail h4 {
    margin-top: 0;
    color: #0073aa;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
}

.wpsa-question-text {
    background-color: #fff;
    padding: 10px;
    border-radius: 3px;
    margin: 10px 0;
    border-left: 4px solid #0073aa;
}

.wpsa-answer-section {
    margin: 10px 0;
}

.wpsa-answer-text {
    background-color: #f9f9f9;
    padding: 10px;
    border-radius: 3px;
    margin-top: 5px;
    border-left: 4px solid #28a745;
}

.wpsa-score-section {
    margin: 10px 0;
    padding: 8px;
    background-color: #e7f3ff;
    border-radius: 3px;
}

.wpsa-score {
    font-weight: bold;
    color: #0073aa;
}

.wpsa-feedback-section {
    margin: 10px 0;
}

.wpsa-feedback-text {
    background-color: #fff3cd;
    padding: 10px;
    border-radius: 3px;
    margin-top: 5px;
    border-left: 4px solid #ffc107;
}

.wpsa-recommendations {
    background-color: #e7f3ff;
    padding: 15px;
    border-radius: 5px;
    margin-top: 15px;
    border-left: 4px solid #0073aa;
}

.wpsa-report-options {
    text-align: center;
    padding: 20px;
}

.wpsa-report-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 20px;
    flex-wrap: wrap;
}

.error {
    color: #d63384;
    font-weight: bold;
}
</style>
