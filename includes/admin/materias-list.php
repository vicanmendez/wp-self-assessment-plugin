<?php
/**
 * Lista de materias
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Materias', 'wp-self-assessment'); ?>
    </h1>
    <a href="<?php echo admin_url('admin.php?page=wpsa-materias&action=add'); ?>" class="page-title-action">
        <?php _e('Agregar Nueva', 'wp-self-assessment'); ?>
    </a>
    <hr class="wp-header-end">
    
    <!-- Filtros -->
    <div class="wpsa-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="wpsa-materias" />
            
            <div class="wpsa-filter-group">
                <label for="grado"><?php _e('Filtrar por Grado:', 'wp-self-assessment'); ?></label>
                <select name="grado" id="grado">
                    <option value=""><?php _e('Todos los grados', 'wp-self-assessment'); ?></option>
                    <?php foreach ($grados as $grado): ?>
                        <option value="<?php echo esc_attr($grado); ?>" <?php selected(isset($_GET['grado']) ? $_GET['grado'] : '', $grado); ?>>
                            <?php echo esc_html($grado); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="wpsa-filter-group">
                <label for="search"><?php _e('Buscar:', 'wp-self-assessment'); ?></label>
                <input type="text" name="search" id="search" value="<?php echo esc_attr(isset($_GET['search']) ? $_GET['search'] : ''); ?>" placeholder="<?php _e('Nombre o descripción...', 'wp-self-assessment'); ?>" />
            </div>
            
            <div class="wpsa-filter-group">
                <input type="submit" class="button" value="<?php _e('Filtrar', 'wp-self-assessment'); ?>" />
                <a href="<?php echo admin_url('admin.php?page=wpsa-materias'); ?>" class="button">
                    <?php _e('Limpiar', 'wp-self-assessment'); ?>
                </a>
            </div>
        </form>
    </div>
    
    <!-- Lista de materias -->
    <?php if (!empty($materias)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-primary">
                        <?php _e('Materia', 'wp-self-assessment'); ?>
                    </th>
                    <th scope="col" class="manage-column">
                        <?php _e('Grado', 'wp-self-assessment'); ?>
                    </th>
                    <th scope="col" class="manage-column">
                        <?php _e('Descripción', 'wp-self-assessment'); ?>
                    </th>
                    <th scope="col" class="manage-column">
                        <?php _e('Temario', 'wp-self-assessment'); ?>
                    </th>
                    <th scope="col" class="manage-column">
                        <?php _e('Fecha Creación', 'wp-self-assessment'); ?>
                    </th>
                    <th scope="col" class="manage-column">
                        <?php _e('Acciones', 'wp-self-assessment'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($materias as $materia): ?>
                    <tr>
                        <td class="column-primary">
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=wpsa-materias&action=edit&id=' . $materia->id); ?>">
                                    <?php echo esc_html($materia->nombre); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <span class="wpsa-grade-badge"><?php echo esc_html($materia->grado); ?></span>
                        </td>
                        <td>
                            <?php if (!empty($materia->descripcion)): ?>
                                <?php echo esc_html(wp_trim_words($materia->descripcion, 15)); ?>
                            <?php else: ?>
                                <span class="wpsa-no-data"><?php _e('Sin descripción', 'wp-self-assessment'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($materia->temario_analizado)): ?>
                                <span class="wpsa-status-ok"><?php _e('Analizado', 'wp-self-assessment'); ?></span>
                            <?php elseif (!empty($materia->temario)): ?>
                                <span class="wpsa-status-warning"><?php _e('Manual', 'wp-self-assessment'); ?></span>
                            <?php else: ?>
                                <span class="wpsa-status-error"><?php _e('Sin temario', 'wp-self-assessment'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($materia->created_at))); ?>
                        </td>
                        <td>
                            <div class="wpsa-actions">
                                <a href="<?php echo admin_url('admin.php?page=wpsa-materias&action=edit&id=' . $materia->id); ?>" class="button button-small">
                                    <?php _e('Editar', 'wp-self-assessment'); ?>
                                </a>
                                <button type="button" 
                                        class="button button-small button-link-delete wpsa-delete-materia" 
                                        data-id="<?php echo esc_attr($materia->id); ?>"
                                        data-nombre="<?php echo esc_attr($materia->nombre); ?>">
                                    <?php _e('Eliminar', 'wp-self-assessment'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Estadísticas de la tabla -->
        <div class="wpsa-table-stats">
            <p>
                <?php 
                printf(
                    _n('Se encontró %d materia', 'Se encontraron %d materias', count($materias), 'wp-self-assessment'),
                    count($materias)
                );
                ?>
            </p>
        </div>
        
    <?php else: ?>
        <div class="wpsa-no-data-message">
            <h2><?php _e('No se encontraron materias', 'wp-self-assessment'); ?></h2>
            <p><?php _e('No hay materias que coincidan con los filtros aplicados.', 'wp-self-assessment'); ?></p>
            <a href="<?php echo admin_url('admin.php?page=wpsa-materias&action=add'); ?>" class="button button-primary">
                <?php _e('Agregar Primera Materia', 'wp-self-assessment'); ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de confirmación de eliminación -->
<div id="wpsa-delete-modal" class="wpsa-modal" style="display: none;">
    <div class="wpsa-modal-content">
        <h3><?php _e('Confirmar Eliminación', 'wp-self-assessment'); ?></h3>
        <p><?php _e('¿Estás seguro de que deseas eliminar la materia', 'wp-self-assessment'); ?> "<span id="materia-nombre"></span>"?</p>
        <p class="wpsa-warning"><?php _e('Esta acción no se puede deshacer. También se eliminarán todas las autoevaluaciones asociadas.', 'wp-self-assessment'); ?></p>
        <div class="wpsa-modal-actions">
            <button type="button" id="confirm-delete" class="button button-primary button-link-delete">
                <?php _e('Eliminar', 'wp-self-assessment'); ?>
            </button>
            <button type="button" id="cancel-delete" class="button">
                <?php _e('Cancelar', 'wp-self-assessment'); ?>
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var deleteModal = $('#wpsa-delete-modal');
    var materiaId = null;
    
    // Abrir modal de eliminación
    $('.wpsa-delete-materia').on('click', function() {
        materiaId = $(this).data('id');
        var nombre = $(this).data('nombre');
        $('#materia-nombre').text(nombre);
        deleteModal.show();
    });
    
    // Confirmar eliminación
    $('#confirm-delete').on('click', function() {
        if (materiaId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpsa_delete_materia',
                    materia_id: materiaId,
                    nonce: '<?php echo wp_create_nonce('wpsa_delete_materia'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('<?php _e('Error de conexión', 'wp-self-assessment'); ?>');
                }
            });
        }
        deleteModal.hide();
    });
    
    // Cancelar eliminación
    $('#cancel-delete').on('click', function() {
        deleteModal.hide();
    });
    
    // Cerrar modal al hacer clic fuera
    deleteModal.on('click', function(e) {
        if (e.target === this) {
            deleteModal.hide();
        }
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

.wpsa-grade-badge {
    background: #0073aa;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.wpsa-no-data {
    color: #999;
    font-style: italic;
}

.wpsa-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.wpsa-table-stats {
    margin-top: 15px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
    color: #666;
}

.wpsa-no-data-message {
    text-align: center;
    padding: 40px 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 20px 0;
}

.wpsa-no-data-message h2 {
    color: #666;
    margin-bottom: 10px;
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

.wpsa-modal-content h3 {
    margin-top: 0;
    color: #d63638;
}

.wpsa-warning {
    color: #d63638;
    font-weight: bold;
    background: #f8d7da;
    padding: 10px;
    border-radius: 3px;
    border-left: 4px solid #d63638;
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
</style>
