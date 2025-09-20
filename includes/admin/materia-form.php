<?php
/**
 * Formulario de materia
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($materia);
$title = $is_edit ? __('Editar Materia', 'wp-self-assessment') : __('Agregar Nueva Materia', 'wp-self-assessment');
?>

<div class="wrap">
    <h1><?php echo esc_html($title); ?></h1>
    
    <form method="post" action="" id="wpsa-materia-form" enctype="multipart/form-data">
        <?php wp_nonce_field('wpsa_materia'); ?>
        <input type="hidden" name="materia_id" value="<?php echo esc_attr($materia ? $materia->id : 0); ?>" />
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="nombre"><?php _e('Nombre de la Materia', 'wp-self-assessment'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="nombre" 
                               name="nombre" 
                               value="<?php echo esc_attr($materia ? $materia->nombre : ''); ?>" 
                               class="regular-text" 
                               required />
                        <p class="description">
                            <?php _e('Nombre completo de la materia (ej: Matemáticas Avanzadas, Programación I)', 'wp-self-assessment'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="grado"><?php _e('Grado/Nivel', 'wp-self-assessment'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="grado" 
                               name="grado" 
                               value="<?php echo esc_attr($materia ? $materia->grado : ''); ?>" 
                               class="regular-text" 
                               required />
                        <p class="description">
                            <?php _e('Grado académico o nivel (ej: 1er Año, Bachillerato, Universitario)', 'wp-self-assessment'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="descripcion"><?php _e('Descripción', 'wp-self-assessment'); ?></label>
                    </th>
                    <td>
                        <textarea id="descripcion" 
                                  name="descripcion" 
                                  rows="4" 
                                  class="large-text"><?php echo esc_textarea($materia ? $materia->descripcion : ''); ?></textarea>
                        <p class="description">
                            <?php _e('Descripción breve de la materia y sus objetivos', 'wp-self-assessment'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="temario"><?php _e('Temario Manual', 'wp-self-assessment'); ?></label>
                    </th>
                    <td>
                        <textarea id="temario" 
                                  name="temario" 
                                  rows="8" 
                                  class="large-text"><?php echo esc_textarea($materia ? $materia->temario : ''); ?></textarea>
                        <p class="description">
                            <?php _e('Temario escrito manualmente. Se puede usar junto con el análisis automático de PDF.', 'wp-self-assessment'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="programa_pdf"><?php _e('Programa PDF', 'wp-self-assessment'); ?></label>
                    </th>
                    <td>
                        <div class="wpsa-pdf-upload-container">
                            <div class="wpsa-pdf-upload-method">
                                <label>
                                    <input type="radio" name="pdf_method" value="url" checked>
                                    <?php _e('URL del PDF', 'wp-self-assessment'); ?>
                                </label>
                                <label>
                                    <input type="radio" name="pdf_method" value="upload">
                                    <?php _e('Subir archivo PDF', 'wp-self-assessment'); ?>
                                </label>
                            </div>
                            
                            <div id="pdf-url-container">
                                <input type="url" 
                                       id="programa_pdf" 
                                       name="programa_pdf" 
                                       value="<?php echo esc_attr($materia ? $materia->programa_pdf : ''); ?>" 
                                       class="regular-text" 
                                       placeholder="https://ejemplo.com/programa.pdf" />
                            </div>
                            
                            <div id="pdf-upload-container" style="display: none;">
                                <input type="file" 
                                       id="pdf_upload" 
                                       name="pdf_upload" 
                                       accept=".pdf" 
                                       class="regular-text" />
                                <div id="pdf-upload-progress" style="display: none;">
                                    <div class="wpsa-progress-bar">
                                        <div class="wpsa-progress-fill"></div>
                                    </div>
                                    <p class="wpsa-progress-text"><?php _e('Subiendo archivo...', 'wp-self-assessment'); ?></p>
                                </div>
                            </div>
                            
                            <div class="wpsa-pdf-actions">
                                <button type="button" id="analyze-pdf" class="button" disabled>
                                    <?php _e('Analizar PDF con IA', 'wp-self-assessment'); ?>
                                </button>
                                <button type="button" id="upload-pdf" class="button" style="display: none;">
                                    <?php _e('Subir y Analizar', 'wp-self-assessment'); ?>
                                </button>
                            </div>
                        </div>
                        <p class="description">
                            <?php _e('Puedes proporcionar una URL del PDF o subir el archivo directamente. El análisis se realizará con Gemini AI.', 'wp-self-assessment'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="temario_analizado"><?php _e('Temario Analizado por IA', 'wp-self-assessment'); ?></label>
                    </th>
                    <td>
                        <div class="wpsa-temario-container">
                            <textarea id="temario_analizado" 
                                      name="temario_analizado" 
                                      rows="10" 
                                      class="large-text" 
                                      readonly><?php echo esc_textarea($materia ? $materia->temario_analizado : ''); ?></textarea>
                            <div class="wpsa-temario-actions">
                                <button type="button" id="edit-temario" class="button button-small">
                                    <?php _e('Editar', 'wp-self-assessment'); ?>
                                </button>
                                <button type="button" id="save-temario" class="button button-small button-primary" style="display: none;">
                                    <?php _e('Guardar', 'wp-self-assessment'); ?>
                                </button>
                                <button type="button" id="cancel-temario" class="button button-small" style="display: none;">
                                    <?php _e('Cancelar', 'wp-self-assessment'); ?>
                                </button>
                            </div>
                        </div>
                        <p class="description">
                            <?php _e('Temario generado automáticamente por IA. Puedes editarlo si es necesario.', 'wp-self-assessment'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div class="wpsa-form-actions">
            <?php submit_button(__('Guardar Materia', 'wp-self-assessment'), 'primary', 'submit', false); ?>
            <a href="<?php echo admin_url('admin.php?page=wpsa-materias'); ?>" class="button">
                <?php _e('Cancelar', 'wp-self-assessment'); ?>
            </a>
        </div>
    </form>
</div>

<!-- Modal de análisis de PDF con iframe -->
<div id="wpsa-analyze-modal" class="wpsa-modal-iframe" style="display: none;">
    <div class="wpsa-modal-iframe-container">
        <div class="wpsa-modal-iframe-header">
            <h3><?php _e('Analizando PDF con IA', 'wp-self-assessment'); ?></h3>
            <button type="button" id="close-analyze-modal" class="wpsa-close-button" title="<?php _e('Cerrar', 'wp-self-assessment'); ?>">
                <span class="wpsa-close-icon">&times;</span>
            </button>
        </div>
        <div class="wpsa-modal-iframe-content">
            <iframe id="wpsa-analyze-iframe" src="about:blank" frameborder="0"></iframe>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var analyzeModal = $('#wpsa-analyze-modal');
    var temarioTextarea = $('#temario_analizado');
    var isEditing = false;
    
    // Análisis de PDF por URL
    $('#analyze-pdf').on('click', function() {
        var pdfUrl = $('#programa_pdf').val();
        var materiaId = $('input[name="materia_id"]').val();
        
        if (!pdfUrl) {
            alert('<?php _e('Por favor, ingresa una URL válida del PDF', 'wp-self-assessment'); ?>');
            return;
        }
        
        // Mostrar modal con iframe
        analyzeModal.show();
        
        // Usar el nonce del formulario principal
        var nonce = $('input[name="_wpnonce"]').val();
        
        // Construir URL para el iframe
        var iframeUrl = ajaxurl + '?action=wpsa_analyze_pdf_iframe' +
            '&pdf_url=' + encodeURIComponent(pdfUrl) +
            '&materia_id=' + materiaId +
            '&nonce=' + nonce;
        
        // Cargar iframe
        $('#wpsa-analyze-iframe').attr('src', iframeUrl);
    });
    
    // Subida y análisis de PDF
    $('#upload-pdf').on('click', function() {
        var fileInput = $('#pdf_upload')[0];
        var materiaId = $('input[name="materia_id"]').val();
        
        if (!fileInput.files.length) {
            alert('<?php _e('Por favor, selecciona un archivo PDF', 'wp-self-assessment'); ?>');
            return;
        }
        
        var file = fileInput.files[0];
        
        // Verificar que sea PDF
        if (file.type !== 'application/pdf') {
            alert('<?php _e('Por favor, selecciona un archivo PDF válido', 'wp-self-assessment'); ?>');
            return;
        }
        
        // Verificar tamaño (máximo 20MB)
        if (file.size > 20 * 1024 * 1024) {
            alert('<?php _e('El archivo es demasiado grande. Máximo 20MB', 'wp-self-assessment'); ?>');
            return;
        }
        
        // Mostrar progreso de subida
        $('#pdf-upload-progress').show();
        $('#upload-pdf').prop('disabled', true);
        
        // Subir archivo
        var formData = new FormData();
        formData.append('action', 'wpsa_upload_pdf');
        formData.append('pdf_file', file);
        formData.append('materia_id', materiaId);
        formData.append('nonce', $('input[name="_wpnonce"]').val());
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#pdf-upload-progress').hide();
                $('#upload-pdf').prop('disabled', false);
                
                if (response.success) {
                    // Mostrar modal con iframe para análisis
                    analyzeModal.show();
                    
                    var iframeUrl = ajaxurl + '?action=wpsa_analyze_pdf_iframe' +
                        '&pdf_file_id=' + response.data.file_id +
                        '&materia_id=' + materiaId +
                        '&nonce=' + $('input[name="_wpnonce"]').val();
                    
                    $('#wpsa-analyze-iframe').attr('src', iframeUrl);
                } else {
                    showNotification('<?php _e('Error al subir archivo: ', 'wp-self-assessment'); ?>' + response.data, 'error');
                }
            },
            error: function() {
                $('#pdf-upload-progress').hide();
                $('#upload-pdf').prop('disabled', false);
                showNotification('<?php _e('Error de conexión al subir archivo', 'wp-self-assessment'); ?>', 'error');
            }
        });
    });
    
    // Cerrar modal
    $('#close-analyze-modal').on('click', function() {
        analyzeModal.hide();
        $('#wpsa-analyze-iframe').attr('src', 'about:blank');
    });
    
    // Cerrar modal al hacer clic fuera
    analyzeModal.on('click', function(e) {
        if (e.target === this) {
            analyzeModal.hide();
            $('#wpsa-analyze-iframe').attr('src', 'about:blank');
        }
    });
    
    // Escuchar mensajes del iframe
    window.addEventListener('message', function(event) {
        if (event.data.type === 'pdf_analysis_success') {
            // El análisis fue exitoso, pero esperamos a que el usuario acepte
            console.log('Análisis completado exitosamente');
        } else if (event.data.type === 'pdf_analysis_accept') {
            // Usuario aceptó el temario
            temarioTextarea.val(event.data.temario);
            analyzeModal.hide();
            $('#wpsa-analyze-iframe').attr('src', 'about:blank');
            
            // Mostrar notificación de éxito
            showNotification('<?php _e('Temario generado y guardado correctamente', 'wp-self-assessment'); ?>', 'success');
        } else if (event.data.type === 'pdf_analysis_reject') {
            // Usuario rechazó el temario
            analyzeModal.hide();
            $('#wpsa-analyze-iframe').attr('src', 'about:blank');
        } else if (event.data.type === 'pdf_analysis_error') {
            // Error en el análisis
            analyzeModal.hide();
            $('#wpsa-analyze-iframe').attr('src', 'about:blank');
            showNotification('<?php _e('Error al analizar PDF: ', 'wp-self-assessment'); ?>' + event.data.error, 'error');
        }
    });
    
    // Editar temario analizado
    $('#edit-temario').on('click', function() {
        isEditing = true;
        temarioTextarea.prop('readonly', false);
        $(this).hide();
        $('#save-temario, #cancel-temario').show();
    });
    
    // Guardar temario editado
    $('#save-temario').on('click', function() {
        isEditing = false;
        temarioTextarea.prop('readonly', true);
        $('#edit-temario').show();
        $('#save-temario, #cancel-temario').hide();
    });
    
    // Cancelar edición
    $('#cancel-temario').on('click', function() {
        isEditing = false;
        temarioTextarea.prop('readonly', true);
        $('#edit-temario').show();
        $('#save-temario, #cancel-temario').hide();
        // Restaurar valor original si es necesario
    });
    
    // Cambiar método de PDF
    $('input[name="pdf_method"]').on('change', function() {
        var method = $(this).val();
        if (method === 'url') {
            $('#pdf-url-container').show();
            $('#pdf-upload-container').hide();
            $('#analyze-pdf').show();
            $('#upload-pdf').hide();
            updateAnalyzeButton();
        } else {
            $('#pdf-url-container').hide();
            $('#pdf-upload-container').show();
            $('#analyze-pdf').hide();
            $('#upload-pdf').show();
            updateUploadButton();
        }
    });
    
    // Habilitar/deshabilitar botón de análisis según URL
    $('#programa_pdf').on('input', function() {
        updateAnalyzeButton();
    });
    
    // Habilitar/deshabilitar botón de subida según archivo
    $('#pdf_upload').on('change', function() {
        updateUploadButton();
    });
    
    function updateAnalyzeButton() {
        var hasUrl = $('#programa_pdf').val().trim() !== '';
        $('#analyze-pdf').prop('disabled', !hasUrl);
    }
    
    function updateUploadButton() {
        var hasFile = $('#pdf_upload')[0].files.length > 0;
        $('#upload-pdf').prop('disabled', !hasFile);
    }
    
    // Función para mostrar notificaciones
    function showNotification(message, type) {
        var notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notification);
        
        // Auto-ocultar después de 5 segundos
        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
    }
});
</script>

<style>
.wpsa-form-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
    display: flex;
    gap: 10px;
}

.wpsa-temario-container {
    position: relative;
}

.wpsa-temario-actions {
    margin-top: 10px;
    display: flex;
    gap: 5px;
}

.wpsa-modal-iframe {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wpsa-modal-iframe-container {
    background: white;
    border-radius: 8px;
    max-width: 90%;
    max-height: 90%;
    width: 1000px;
    height: 700px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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
    border-bottom: 1px solid #005a87;
}

.wpsa-modal-iframe-header h3 {
    margin: 0;
    font-size: 18px;
}

.wpsa-close-button {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.2s;
}

.wpsa-close-button:hover {
    background: rgba(255,255,255,0.2);
}

.wpsa-close-icon {
    line-height: 1;
}

.wpsa-modal-iframe-content {
    flex: 1;
    overflow: hidden;
}

.wpsa-modal-iframe-content iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.wpsa-pdf-upload-container {
    max-width: 600px;
}

.wpsa-pdf-upload-method {
    margin-bottom: 15px;
    display: flex;
    gap: 20px;
}

.wpsa-pdf-upload-method label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: normal;
    cursor: pointer;
}

.wpsa-pdf-upload-method input[type="radio"] {
    margin: 0;
}

.wpsa-pdf-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.wpsa-progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
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
    margin: 5px 0;
    color: #666;
    font-size: 14px;
    text-align: center;
}


.required {
    color: #d63638;
}

.form-table th {
    width: 200px;
}

.form-table td {
    padding-left: 20px;
}

textarea.large-text {
    width: 100%;
    max-width: 600px;
}
</style>
