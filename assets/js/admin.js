/**
 * JavaScript para el admin del plugin WP Self Assessment
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Inicializar
    init();
    
    function init() {
        bindEvents();
    }
    
    function bindEvents() {
        // Toggle API key visibility
        $('#toggle-api-key').on('click', function() {
            togglePasswordVisibility('#wpsa_gemini_api_key', $(this));
        });
        
        // Toggle reCAPTCHA secret visibility
        $('#toggle-recaptcha-secret').on('click', function() {
            togglePasswordVisibility('#wpsa_recaptcha_secret_key', $(this));
        });
        
        // Copy shortcode
        $('#copy-shortcode').on('click', function() {
            copyToClipboard('[wpsa_autoevaluacion]', $(this));
        });
        
        // Análisis de PDF
        $('#analyze-pdf').on('click', function() {
            analyzePDF();
        });
        
        // Aceptar temario generado
        $('#accept-temario').on('click', function() {
            acceptTemario();
        });
        
        // Rechazar temario
        $('#reject-temario').on('click', function() {
            rejectTemario();
        });
        
        // Editar temario analizado
        $('#edit-temario').on('click', function() {
            editTemario();
        });
        
        // Guardar temario editado
        $('#save-temario').on('click', function() {
            saveTemario();
        });
        
        // Cancelar edición
        $('#cancel-temario').on('click', function() {
            cancelEditTemario();
        });
        
        // Habilitar/deshabilitar botón de análisis según URL
        $('#programa_pdf').on('input', function() {
            $('#analyze-pdf').prop('disabled', !$(this).val());
        });
        
        // Eliminar materia
        $('.wpsa-delete-materia').on('click', function() {
            showDeleteModal($(this));
        });
        
        // Confirmar eliminación
        $('#confirm-delete').on('click', function() {
            confirmDelete();
        });
        
        // Cancelar eliminación
        $('#cancel-delete').on('click', function() {
            hideDeleteModal();
        });
        
        // Cerrar modales al hacer clic fuera
        $('.wpsa-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        // Ver detalles de autoevaluación
        $('.wpsa-view-details').on('click', function() {
            viewEvaluationDetails($(this));
        });
        
        // Cerrar modal de detalles
        $('#close-details').on('click', function() {
            hideDetailsModal();
        });
        
        // Exportar datos
        $('#export-csv').on('click', function() {
            exportData('csv');
        });
        
        $('#export-excel').on('click', function() {
            exportData('excel');
        });
        
        $('#generate-report').on('click', function() {
            generateReport();
        });
    }
    
    function togglePasswordVisibility(inputSelector, button) {
        const input = $(inputSelector);
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            button.text('Ocultar');
        } else {
            input.attr('type', 'password');
            button.text('Mostrar');
        }
    }
    
    function copyToClipboard(text, button) {
        navigator.clipboard.writeText(text).then(function() {
            const originalText = button.text();
            button.text('¡Copiado!');
            
            setTimeout(function() {
                button.text(originalText);
            }, 2000);
        }).catch(function() {
            // Fallback para navegadores que no soportan clipboard API
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            const originalText = button.text();
            button.text('¡Copiado!');
            
            setTimeout(function() {
                button.text(originalText);
            }, 2000);
        });
    }
    
    function analyzePDF() {
        const pdfUrl = $('#programa_pdf').val();
        const materiaId = $('input[name="materia_id"]').val();
        
        if (!pdfUrl) {
            alert('Por favor, ingresa una URL válida del PDF');
            return;
        }
        
        const modal = $('#wpsa-analyze-modal');
        modal.show();
        $('.wpsa-analyze-progress').show();
        $('.wpsa-analyze-result').hide();
        
        // Simular progreso
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += 10;
            $('.wpsa-progress-fill').css('width', progress + '%');
            
            if (progress >= 50) {
                $('.wpsa-progress-text').text('Enviando a Gemini AI...');
            }
            
            if (progress >= 100) {
                clearInterval(progressInterval);
            }
        }, 200);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpsa_analyze_pdf',
                pdf_url: pdfUrl,
                materia_id: materiaId,
                nonce: $('#_wpnonce').val()
            },
            success: function(response) {
                clearInterval(progressInterval);
                $('.wpsa-progress-fill').css('width', '100%');
                
                if (response.success) {
                    $('.wpsa-analyze-progress').hide();
                    $('.wpsa-analyze-result').show();
                    $('.wpsa-temario-preview').text(response.data.temario);
                } else {
                    alert('Error: ' + response.data);
                    modal.hide();
                }
            },
            error: function() {
                clearInterval(progressInterval);
                alert('Error de conexión');
                modal.hide();
            }
        });
    }
    
    function acceptTemario() {
        const temario = $('.wpsa-temario-preview').text();
        $('#temario_analizado').val(temario);
        $('#wpsa-analyze-modal').hide();
    }
    
    function rejectTemario() {
        $('#wpsa-analyze-modal').hide();
    }
    
    function editTemario() {
        $('#temario_analizado').prop('readonly', false);
        $('#edit-temario').hide();
        $('#save-temario, #cancel-temario').show();
    }
    
    function saveTemario() {
        $('#temario_analizado').prop('readonly', true);
        $('#edit-temario').show();
        $('#save-temario, #cancel-temario').hide();
    }
    
    function cancelEditTemario() {
        $('#temario_analizado').prop('readonly', true);
        $('#edit-temario').show();
        $('#save-temario, #cancel-temario').hide();
    }
    
    function showDeleteModal(button) {
        const materiaId = button.data('id');
        const nombre = button.data('nombre');
        
        $('#materia-nombre').text(nombre);
        $('#wpsa-delete-modal').data('materia-id', materiaId).show();
    }
    
    function hideDeleteModal() {
        $('#wpsa-delete-modal').hide();
    }
    
    function confirmDelete() {
        const materiaId = $('#wpsa-delete-modal').data('materia-id');
        
        if (materiaId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpsa_delete_materia',
                    materia_id: materiaId,
                    nonce: $('#_wpnonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error de conexión');
                }
            });
        }
        
        hideDeleteModal();
    }
    
    function viewEvaluationDetails(button) {
        const autoevalId = button.data('id');
        const modal = $('#wpsa-details-modal');
        
        modal.show();
        $('#wpsa-details-content').html('<p>Cargando...</p>');
        
        // Simular carga de detalles
        setTimeout(function() {
            $('#wpsa-details-content').html(`
                <div class="wpsa-details">
                    <h4>Detalles de la Autoevaluación #${autoevalId}</h4>
                    <p>Esta funcionalidad está en desarrollo. Aquí se mostrarían los detalles completos de la autoevaluación.</p>
                </div>
            `);
        }, 1000);
    }
    
    function hideDetailsModal() {
        $('#wpsa-details-modal').hide();
    }
    
    function exportData(format) {
        // Implementar exportación de datos
        alert(`Funcionalidad de exportación ${format.toUpperCase()} en desarrollo`);
    }
    
    function generateReport() {
        // Implementar generación de reporte
        alert('Funcionalidad de generación de reporte en desarrollo');
    }
    
    // Funciones de utilidad
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Descartar este aviso.</span>
                </button>
            </div>
        `);
        
        $('.wrap h1').after(notification);
        
        // Auto-dismiss después de 5 segundos
        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
        
        // Dismiss al hacer clic en el botón
        notification.find('.notice-dismiss').on('click', function() {
            notification.fadeOut();
        });
    }
    
    function showLoading(element) {
        $(element).addClass('wpsa-loading');
    }
    
    function hideLoading(element) {
        $(element).removeClass('wpsa-loading');
    }
    
    // Validación de formularios
    function validateForm(formSelector) {
        const form = $(formSelector);
        let isValid = true;
        
        form.find('input[required], textarea[required], select[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        return isValid;
    }
    
    // Aplicar validación en tiempo real
    $('input[required], textarea[required], select[required]').on('blur', function() {
        if (!$(this).val()) {
            $(this).addClass('error');
        } else {
            $(this).removeClass('error');
        }
    });
    
    // Estilos para errores de validación
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .error {
                border-color: #d63638 !important;
                box-shadow: 0 0 0 1px #d63638 !important;
            }
        `)
        .appendTo('head');
});
