/**
 * JavaScript para ocultar notificaciones molestas de otros plugins
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Función para ocultar notificaciones molestas
    function hideAnnoyingNotifications() {
        // Ocultar notificaciones por clase
        $('.notice:not(.notice-success.is-dismissible):not(.notice-error.is-dismissible)').hide();
        
        // Ocultar notificaciones específicas de WP Travel Engine
        $('.notice').each(function() {
            var $notice = $(this);
            var text = $notice.text().toLowerCase();
            var classes = $notice.attr('class') || '';
            
            if (text.includes('travel') || 
                text.includes('wte') || 
                text.includes('engine') ||
                text.includes('wp-travel') ||
                classes.includes('wte') ||
                classes.includes('travel') ||
                classes.includes('wp-travel')) {
                $notice.hide();
            }
        });
        
        // Ocultar notificaciones por ID
        $('#wte-admin-notice, .wte-admin-notice, .travel-engine-notice, .wp-travel-notice').hide();
        
        // Ocultar notificaciones de actualización
        $('.notice.notice-info, .notice.notice-warning').hide();
        
        // Asegurar que nuestro botón sea visible
        $('.wpsa-save-button-container').css({
            'position': 'relative',
            'z-index': '99999',
            'background': 'white',
            'padding': '5px',
            'border-radius': '4px',
            'box-shadow': '0 2px 4px rgba(0,0,0,0.1)'
        });
    }
    
    // Ejecutar al cargar la página
    hideAnnoyingNotifications();
    
    // Ejecutar cada 500ms para capturar notificaciones que aparezcan dinámicamente
    setInterval(hideAnnoyingNotifications, 500);
    
    // Observar cambios en el DOM para notificaciones que aparezcan después
    if (window.MutationObserver) {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    $(mutation.addedNodes).each(function() {
                        if ($(this).hasClass('notice')) {
                            var $notice = $(this);
                            var text = $notice.text().toLowerCase();
                            var classes = $notice.attr('class') || '';
                            
                            if (text.includes('travel') || 
                                text.includes('wte') || 
                                text.includes('engine') ||
                                text.includes('wp-travel') ||
                                classes.includes('wte') ||
                                classes.includes('travel') ||
                                classes.includes('wp-travel')) {
                                $notice.hide();
                            }
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // Ocultar notificaciones que aparezcan por eventos de WordPress
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).hasClass('notice')) {
            var $notice = $(e.target);
            var text = $notice.text().toLowerCase();
            var classes = $notice.attr('class') || '';
            
            if (text.includes('travel') || 
                text.includes('wte') || 
                text.includes('engine') ||
                text.includes('wp-travel') ||
                classes.includes('wte') ||
                classes.includes('travel') ||
                classes.includes('wp-travel')) {
                $notice.hide();
            }
        }
    });
    
    // Forzar visibilidad de elementos importantes
    $('.wp-heading-inline, .wrap h1, .wrap .form-table').css({
        'position': 'relative',
        'z-index': '99998'
    });
    
    // Asegurar que el botón de guardar esté siempre visible
    $('.wpsa-save-button-container .button-primary').css({
        'position': 'relative',
        'z-index': '100000'
    });
});
