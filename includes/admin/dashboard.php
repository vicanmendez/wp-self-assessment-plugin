<?php
/**
 * Página de dashboard del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Dashboard - Autoevaluaciones', 'wp-self-assessment'); ?></h1>
    
    <div class="wpsa-dashboard">
        <!-- Estadísticas generales -->
        <div class="wpsa-stats-grid">
            <div class="wpsa-stat-card">
                <h3><?php _e('Total Autoevaluaciones', 'wp-self-assessment'); ?></h3>
                <div class="wpsa-stat-number"><?php echo esc_html($estadisticas->total_autoevaluaciones ?? 0); ?></div>
            </div>
            
            <div class="wpsa-stat-card">
                <h3><?php _e('Completadas', 'wp-self-assessment'); ?></h3>
                <div class="wpsa-stat-number"><?php echo esc_html($estadisticas->completadas ?? 0); ?></div>
            </div>
            
            <div class="wpsa-stat-card">
                <h3><?php _e('En Progreso', 'wp-self-assessment'); ?></h3>
                <div class="wpsa-stat-number"><?php echo esc_html($estadisticas->en_progreso ?? 0); ?></div>
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
                <h3><?php _e('Materias Registradas', 'wp-self-assessment'); ?></h3>
                <div class="wpsa-stat-number"><?php echo esc_html(count($materias)); ?></div>
            </div>
        </div>
        
        <!-- Acciones rápidas -->
        <div class="wpsa-quick-actions">
            <h2><?php _e('Acciones Rápidas', 'wp-self-assessment'); ?></h2>
            <div class="wpsa-action-buttons">
                <a href="<?php echo admin_url('admin.php?page=wpsa-materias&action=add'); ?>" class="button button-primary">
                    <?php _e('Agregar Nueva Materia', 'wp-self-assessment'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=wpsa-settings'); ?>" class="button">
                    <?php _e('Configurar API', 'wp-self-assessment'); ?>
                </a>
            </div>
        </div>
        
        <!-- Autoevaluaciones recientes -->
        <div class="wpsa-recent-evaluations">
            <div class="wpsa-evaluations-header">
                <div class="wpsa-evaluations-title">
                    <h2><?php _e('Autoevaluaciones Recientes', 'wp-self-assessment'); ?></h2>
                    <?php if (isset($_GET['materia_id']) || isset($_GET['fecha_desde']) || isset($_GET['fecha_hasta'])): ?>
                        <div class="wpsa-active-filters">
                            <span class="wpsa-filter-label"><?php _e('Filtros activos:', 'wp-self-assessment'); ?></span>
                            <?php if (isset($_GET['materia_id']) && !empty($_GET['materia_id'])): ?>
                                <?php 
                                $materia_filtrada = null;
                                foreach ($materias as $materia) {
                                    if ($materia->id == $_GET['materia_id']) {
                                        $materia_filtrada = $materia;
                                        break;
                                    }
                                }
                                ?>
                                <span class="wpsa-filter-tag">
                                    <?php _e('Materia:', 'wp-self-assessment'); ?> 
                                    <strong><?php echo esc_html($materia_filtrada ? $materia_filtrada->nombre : 'ID ' . $_GET['materia_id']); ?></strong>
                                </span>
                            <?php endif; ?>
                            <?php if (isset($_GET['fecha_desde']) && !empty($_GET['fecha_desde'])): ?>
                                <span class="wpsa-filter-tag">
                                    <?php _e('Desde:', 'wp-self-assessment'); ?> 
                                    <strong><?php echo esc_html(date_i18n('d/m/Y', strtotime($_GET['fecha_desde']))); ?></strong>
                                </span>
                            <?php endif; ?>
                            <?php if (isset($_GET['fecha_hasta']) && !empty($_GET['fecha_hasta'])): ?>
                                <span class="wpsa-filter-tag">
                                    <?php _e('Hasta:', 'wp-self-assessment'); ?> 
                                    <strong><?php echo esc_html(date_i18n('d/m/Y', strtotime($_GET['fecha_hasta']))); ?></strong>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Filtros -->
                <div class="wpsa-filters">
                    <form method="GET" action="" class="wpsa-filter-form">
                        <input type="hidden" name="page" value="wpsa-dashboard">
                        
                        <select name="materia_id" id="filter-materia">
                            <option value=""><?php _e('Todas las materias', 'wp-self-assessment'); ?></option>
                            <?php foreach ($materias as $materia): ?>
                                <option value="<?php echo esc_attr($materia->id); ?>" 
                                        <?php selected(isset($_GET['materia_id']) ? $_GET['materia_id'] : '', $materia->id); ?>>
                                    <?php echo esc_html($materia->nombre . ' (' . $materia->grado . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="date" name="fecha_desde" id="filter-fecha-desde" 
                               value="<?php echo esc_attr(isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : ''); ?>" 
                               placeholder="<?php _e('Fecha desde', 'wp-self-assessment'); ?>" />
                        
                        <input type="date" name="fecha_hasta" id="filter-fecha-hasta" 
                               value="<?php echo esc_attr(isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : ''); ?>" 
                               placeholder="<?php _e('Fecha hasta', 'wp-self-assessment'); ?>" />
                        
                        
                        <button type="submit" class="button button-primary">
                            <?php _e('Filtrar', 'wp-self-assessment'); ?>
                        </button>
                        
                        <?php if (isset($_GET['materia_id']) || isset($_GET['fecha_desde']) || isset($_GET['fecha_hasta'])): ?>
                            <a href="<?php echo admin_url('admin.php?page=wpsa-dashboard'); ?>" class="button">
                                <?php _e('Limpiar', 'wp-self-assessment'); ?>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($autoevaluaciones_recientes)): ?>
                <div class="wpsa-bulk-actions">
                    <button type="button" id="delete-selected-evaluations" class="button button-link-delete" disabled>
                        <?php _e('Eliminar seleccionadas', 'wp-self-assessment'); ?>
                    </button>
                    <span id="selected-count" class="wpsa-selected-count">0 <?php _e('seleccionadas', 'wp-self-assessment'); ?></span>
                </div>
                
                <div class="wpsa-results-info">
                    <p class="wpsa-results-count">
                        <?php 
                        $total_results = count($autoevaluaciones_recientes);
                        if (isset($_GET['materia_id']) || isset($_GET['fecha_desde']) || isset($_GET['fecha_hasta'])) {
                            printf(
                                _n(
                                    'Mostrando %d autoevaluación que coincide con los filtros',
                                    'Mostrando %d autoevaluaciones que coinciden con los filtros',
                                    $total_results,
                                    'wp-self-assessment'
                                ),
                                $total_results
                            );
                        } else {
                            printf(
                                _n(
                                    'Mostrando %d autoevaluación reciente',
                                    'Mostrando %d autoevaluaciones recientes',
                                    $total_results,
                                    'wp-self-assessment'
                                ),
                                $total_results
                            );
                        }
                        ?>
                    </p>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="wpsa-select-column">
                                <input type="checkbox" id="select-all-evaluations" title="<?php _e('Seleccionar/Deseleccionar todas', 'wp-self-assessment'); ?>">
                            </th>
                            <th><?php _e('Estudiante', 'wp-self-assessment'); ?></th>
                            <th><?php _e('Materia', 'wp-self-assessment'); ?></th>
                            <th><?php _e('Modalidad', 'wp-self-assessment'); ?></th>
                            <th><?php _e('Puntuación', 'wp-self-assessment'); ?></th>
                            <th><?php _e('Fecha', 'wp-self-assessment'); ?></th>
                            <th><?php _e('Acciones', 'wp-self-assessment'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($autoevaluaciones_recientes as $autoeval): ?>
                            <tr>
                                <td class="wpsa-select-column">
                                    <input type="checkbox" class="evaluation-checkbox" 
                                           value="<?php echo esc_attr($autoeval->id); ?>" 
                                           data-student="<?php echo esc_attr($autoeval->estudiante_nombre ?: 'Anónimo'); ?>"
                                           data-subject="<?php echo esc_attr($autoeval->materia_nombre); ?>">
                                </td>
                                <td><?php echo esc_html($autoeval->estudiante_nombre ?: __('Anónimo', 'wp-self-assessment')); ?></td>
                                <td><?php echo esc_html($autoeval->materia_nombre); ?></td>
                                <td>
                                    <?php
                                    $modalidades = array(
                                        'preguntas_simples' => __('Preguntas Simples', 'wp-self-assessment'),
                                        'ejercicios' => __('Ejercicios', 'wp-self-assessment'),
                                        'codigo' => __('Análisis de Código', 'wp-self-assessment')
                                    );
                                    echo esc_html($modalidades[$autoeval->modalidad] ?? $autoeval->modalidad);
                                    ?>
                                </td>
                                <td>
                                    <?php if ($autoeval->estado === 'completada'): ?>
                                        <?php echo esc_html($autoeval->puntuacion_obtenida . '/' . $autoeval->puntuacion_total); ?>
                                        (<?php echo esc_html($autoeval->porcentaje); ?>%)
                                    <?php else: ?>
                                        <span class="wpsa-in-progress"><?php _e('En progreso', 'wp-self-assessment'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($autoeval->created_at))); ?></td>
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
                                        <button type="button" 
                                                class="button button-small button-link-delete wpsa-delete-evaluation" 
                                                data-id="<?php echo esc_attr($autoeval->id); ?>"
                                                data-student="<?php echo esc_attr($autoeval->estudiante_nombre ?: 'Anónimo'); ?>"
                                                data-subject="<?php echo esc_attr($autoeval->materia_nombre); ?>"
                                                title="<?php _e('Eliminar esta autoevaluación', 'wp-self-assessment'); ?>">
                                            <?php _e('Eliminar', 'wp-self-assessment'); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No hay autoevaluaciones recientes.', 'wp-self-assessment'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Materias disponibles -->
        <div class="wpsa-available-subjects">
            <h2><?php _e('Materias Disponibles', 'wp-self-assessment'); ?></h2>
            
            <?php if (!empty($materias)): ?>
                <div class="wpsa-subjects-grid">
                    <?php foreach ($materias as $materia): ?>
                        <div class="wpsa-subject-card">
                            <h3><?php echo esc_html($materia->nombre); ?></h3>
                            <p class="wpsa-subject-grade"><?php echo esc_html($materia->grado); ?></p>
                            <?php if (!empty($materia->descripcion)): ?>
                                <p class="wpsa-subject-description"><?php echo esc_html(wp_trim_words($materia->descripcion, 20)); ?></p>
                            <?php endif; ?>
                            <div class="wpsa-subject-actions">
                                <a href="<?php echo admin_url('admin.php?page=wpsa-materias&action=edit&id=' . $materia->id); ?>" class="button button-small">
                                    <?php _e('Editar', 'wp-self-assessment'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><?php _e('No hay materias registradas. ', 'wp-self-assessment'); ?>
                <a href="<?php echo admin_url('admin.php?page=wpsa-materias&action=add'); ?>"><?php _e('Agregar la primera materia', 'wp-self-assessment'); ?></a></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.wpsa-dashboard {
    margin-top: 20px;
}

.wpsa-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
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

.wpsa-quick-actions {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
}

.wpsa-action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.wpsa-recent-evaluations,
.wpsa-available-subjects {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
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

.wpsa-in-progress {
    color: #666;
    font-style: italic;
}

.wpsa-subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.wpsa-subject-card {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    background: #f9f9f9;
}

.wpsa-subject-card h3 {
    margin: 0 0 5px 0;
    color: #0073aa;
}

.wpsa-subject-grade {
    margin: 0 0 10px 0;
    font-weight: bold;
    color: #666;
}

.wpsa-subject-description {
    margin: 0 0 15px 0;
    color: #555;
    line-height: 1.4;
}

.wpsa-subject-actions {
    text-align: right;
}

/* Estilos para filtros */
.wpsa-evaluations-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.wpsa-evaluations-title {
    flex: 1;
    min-width: 300px;
}

.wpsa-active-filters {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.wpsa-filter-label {
    font-weight: 600;
    color: #666;
    font-size: 13px;
}

.wpsa-filter-tag {
    background: #0073aa;
    color: white;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.wpsa-filter-tag strong {
    font-weight: 600;
}

.wpsa-results-info {
    margin-bottom: 15px;
    padding: 10px 15px;
    background: #f8f9fa;
    border-left: 4px solid #0073aa;
    border-radius: 0 3px 3px 0;
}

.wpsa-results-count {
    margin: 0;
    font-size: 14px;
    color: #666;
    font-weight: 500;
}

/* Estilos para selección múltiple */
.wpsa-bulk-actions {
    margin-bottom: 15px;
    padding: 10px 15px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 3px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.wpsa-bulk-actions #delete-selected-evaluations {
    margin: 0;
}

.wpsa-bulk-actions #delete-selected-evaluations:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.wpsa-selected-count {
    font-size: 13px;
    color: #666;
    font-weight: 500;
}

.wpsa-select-column {
    width: 30px;
    text-align: center;
    vertical-align: middle;
}

.wpsa-select-column input[type="checkbox"] {
    margin: 0;
    transform: scale(1.2);
}

.wpsa-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.wpsa-filter-form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.wpsa-filter-form select {
    min-width: 200px;
    padding: 5px 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
    background: white;
}

.wpsa-filter-form .button {
    margin: 0;
    white-space: nowrap;
}

@media (max-width: 768px) {
    .wpsa-evaluations-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .wpsa-filters {
        width: 100%;
        justify-content: flex-start;
    }
    
    .wpsa-filter-form {
        width: 100%;
        flex-wrap: wrap;
    }
    
    .wpsa-filter-form select {
        min-width: 150px;
        flex: 1;
    }
}

/* Estilos para botón de eliminar */
.wpsa-delete-evaluation {
    color: #a00 !important;
    border-color: #a00 !important;
}

.wpsa-delete-evaluation:hover {
    color: #fff !important;
    background: #a00 !important;
    border-color: #a00 !important;
}

.wpsa-delete-evaluation:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.wpsa-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.wpsa-actions .button {
    margin: 0;
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
</style>

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
    
    // Eliminar autoevaluación
    $('.wpsa-delete-evaluation').on('click', function() {
        var evaluationId = $(this).data('id');
        var studentName = $(this).data('student');
        var subjectName = $(this).data('subject');
        var button = $(this);
        
        // Confirmación personalizada
        if (confirm('¿Estás seguro de que quieres eliminar esta autoevaluación?\n\n' +
                   'Estudiante: ' + studentName + '\n' +
                   'Materia: ' + subjectName + '\n\n' +
                   'Esta acción no se puede deshacer y afectará las estadísticas.')) {
            
            // Deshabilitar botón y mostrar loading
            button.prop('disabled', true).text('<?php _e('Eliminando...', 'wp-self-assessment'); ?>');
            
            // Enviar petición AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpsa_delete_evaluation',
                    evaluation_id: evaluationId,
                    nonce: '<?php echo wp_create_nonce('wpsa_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Eliminar fila de la tabla
                        button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            
                            // Actualizar contador de filas
                            var remainingRows = $('.wpsa-recent-evaluations tbody tr').length;
                            if (remainingRows === 0) {
                                $('.wpsa-recent-evaluations tbody').html(
                                    '<tr><td colspan="7" class="wpsa-no-data"><?php _e('No hay autoevaluaciones disponibles', 'wp-self-assessment'); ?></td></tr>'
                                );
                            }
                            
                            // Mostrar mensaje de éxito
                            showNotification('<?php _e('Autoevaluación eliminada correctamente', 'wp-self-assessment'); ?>', 'success');
                            
                            // Recargar página para actualizar estadísticas
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        });
                    } else {
                        // Mostrar error
                        showNotification(response.data || '<?php _e('Error al eliminar la autoevaluación', 'wp-self-assessment'); ?>', 'error');
                        button.prop('disabled', false).text('<?php _e('Eliminar', 'wp-self-assessment'); ?>');
                    }
                },
                error: function() {
                    // Mostrar error de conexión
                    showNotification('<?php _e('Error de conexión. Inténtalo de nuevo.', 'wp-self-assessment'); ?>', 'error');
                    button.prop('disabled', false).text('<?php _e('Eliminar', 'wp-self-assessment'); ?>');
                }
            });
        }
    });
    
    // Función para mostrar notificaciones
    function showNotification(message, type) {
        var notificationClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notification = $('<div class="notice ' + notificationClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Insertar notificación al inicio del contenido
        $('.wrap h1').after(notification);
        
        // Auto-ocultar después de 5 segundos
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Funcionalidad de selección múltiple
    var selectAllCheckbox = $('#select-all-evaluations');
    var evaluationCheckboxes = $('.evaluation-checkbox');
    var deleteSelectedBtn = $('#delete-selected-evaluations');
    var selectedCountSpan = $('#selected-count');
    
    // Seleccionar/Deseleccionar todas
    selectAllCheckbox.on('change', function() {
        var isChecked = $(this).is(':checked');
        evaluationCheckboxes.prop('checked', isChecked);
        updateSelectedCount();
        updateDeleteButton();
    });
    
    // Selección individual
    evaluationCheckboxes.on('change', function() {
        updateSelectedCount();
        updateDeleteButton();
        updateSelectAllCheckbox();
    });
    
    // Actualizar contador de seleccionadas
    function updateSelectedCount() {
        var selectedCount = evaluationCheckboxes.filter(':checked').length;
        selectedCountSpan.text(selectedCount + ' <?php _e('seleccionadas', 'wp-self-assessment'); ?>');
    }
    
    // Actualizar botón de eliminar
    function updateDeleteButton() {
        var selectedCount = evaluationCheckboxes.filter(':checked').length;
        deleteSelectedBtn.prop('disabled', selectedCount === 0);
    }
    
    // Actualizar checkbox "Seleccionar todo"
    function updateSelectAllCheckbox() {
        var totalCheckboxes = evaluationCheckboxes.length;
        var checkedCheckboxes = evaluationCheckboxes.filter(':checked').length;
        
        if (checkedCheckboxes === 0) {
            selectAllCheckbox.prop('indeterminate', false).prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            selectAllCheckbox.prop('indeterminate', false).prop('checked', true);
        } else {
            selectAllCheckbox.prop('indeterminate', true);
        }
    }
    
    // Eliminar seleccionadas
    deleteSelectedBtn.on('click', function() {
        var selectedCheckboxes = evaluationCheckboxes.filter(':checked');
        var selectedCount = selectedCheckboxes.length;
        
        if (selectedCount === 0) {
            return;
        }
        
        // Crear lista de evaluaciones para mostrar en la confirmación
        var evaluationsList = '';
        selectedCheckboxes.each(function() {
            var student = $(this).data('student');
            var subject = $(this).data('subject');
            evaluationsList += '• ' + student + ' - ' + subject + '\n';
        });
        
        // Mostrar confirmación personalizada
        if (confirm('¿Estás seguro de que quieres eliminar las siguientes ' + selectedCount + ' autoevaluaciones?\n\n' + evaluationsList + '\nEsta acción no se puede deshacer y afectará las estadísticas.')) {
            
            // Deshabilitar botón y mostrar loading
            deleteSelectedBtn.prop('disabled', true).text('<?php _e('Eliminando...', 'wp-self-assessment'); ?>');
            
            // Obtener IDs de las evaluaciones seleccionadas
            var evaluationIds = [];
            selectedCheckboxes.each(function() {
                evaluationIds.push($(this).val());
            });
            
            // Enviar petición AJAX para eliminación masiva
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpsa_delete_multiple_evaluations',
                    evaluation_ids: evaluationIds,
                    nonce: '<?php echo wp_create_nonce('wpsa_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Eliminar filas de la tabla
                        selectedCheckboxes.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            
                            // Actualizar contador de filas
                            var remainingRows = $('.wpsa-recent-evaluations tbody tr').length;
                            if (remainingRows === 0) {
                                $('.wpsa-recent-evaluations tbody').html(
                                    '<tr><td colspan="7" class="wpsa-no-data"><?php _e('No hay autoevaluaciones disponibles', 'wp-self-assessment'); ?></td></tr>'
                                );
                            }
                            
                            // Mostrar mensaje de éxito
                            showNotification('<?php _e('Autoevaluaciones eliminadas correctamente', 'wp-self-assessment'); ?>', 'success');
                            
                            // Recargar página para actualizar estadísticas
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        });
                    } else {
                        // Mostrar error
                        showNotification(response.data || '<?php _e('Error al eliminar las autoevaluaciones', 'wp-self-assessment'); ?>', 'error');
                        deleteSelectedBtn.prop('disabled', false).text('<?php _e('Eliminar seleccionadas', 'wp-self-assessment'); ?>');
                    }
                },
                error: function() {
                    // Mostrar error de conexión
                    showNotification('<?php _e('Error de conexión. Inténtalo de nuevo.', 'wp-self-assessment'); ?>', 'error');
                    deleteSelectedBtn.prop('disabled', false).text('<?php _e('Eliminar seleccionadas', 'wp-self-assessment'); ?>');
                }
            });
        }
    });
});
</script>
