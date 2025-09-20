<?php
/**
 * Página de configuración del plugin - Versión simplificada
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Configuración - Autoevaluaciones', 'wp-self-assessment'); ?></h1>
    
    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php _e('¡Configuración guardada correctamente!', 'wp-self-assessment'); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('wpsa_settings', '_wpnonce', true, true); ?>
        
        <div style="float: right; margin-top: -50px; margin-bottom: 20px;">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Guardar Cambios', 'wp-self-assessment'); ?>" />
        </div>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="wpsa_gemini_api_key"><?php _e('API Key de Gemini', 'wp-self-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="wpsa_gemini_api_key" 
                               name="wpsa_gemini_api_key" 
                               value="<?php echo esc_attr(get_option('wpsa_gemini_api_key', '')); ?>" 
                               class="regular-text" 
                               required />
                        <p class="description">
                            <?php _e('Ingresa tu API Key de Google Gemini.', 'wp-self-assessment'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpsa_max_questions_per_session"><?php _e('Máximo de Preguntas por Sesión', 'wp-self-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="wpsa_max_questions_per_session" 
                               name="wpsa_max_questions_per_session" 
                               value="<?php echo esc_attr(get_option('wpsa_max_questions_per_session', 10)); ?>" 
                               min="1" 
                               max="50" 
                               class="small-text" 
                               required />
                        <p class="description">
                            <?php _e('Número máximo de preguntas que un estudiante puede solicitar en una sola sesión.', 'wp-self-assessment'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpsa_recaptcha_site_key"><?php _e('Site Key de reCAPTCHA', 'wp-self-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="wpsa_recaptcha_site_key" 
                               name="wpsa_recaptcha_site_key" 
                               value="<?php echo esc_attr(get_option('wpsa_recaptcha_site_key', '')); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e('Clave pública de reCAPTCHA (opcional).', 'wp-self-assessment'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpsa_recaptcha_secret_key"><?php _e('Secret Key de reCAPTCHA', 'wp-self-assessment'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="wpsa_recaptcha_secret_key" 
                               name="wpsa_recaptcha_secret_key" 
                               value="<?php echo esc_attr(get_option('wpsa_recaptcha_secret_key', '')); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php _e('Clave secreta de reCAPTCHA (opcional).', 'wp-self-assessment'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2><?php _e('Estado de la Configuración', 'wp-self-assessment'); ?></h2>
        <div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                <span style="font-weight: bold; color: #333;"><?php _e('API de Gemini:', 'wp-self-assessment'); ?></span>
                <span style="padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; text-transform: uppercase; <?php echo !empty(get_option('wpsa_gemini_api_key', '')) ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;'; ?>">
                    <?php echo !empty(get_option('wpsa_gemini_api_key', '')) ? __('Configurada', 'wp-self-assessment') : __('No configurada', 'wp-self-assessment'); ?>
                </span>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                <span style="font-weight: bold; color: #333;"><?php _e('reCAPTCHA:', 'wp-self-assessment'); ?></span>
                <span style="padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; text-transform: uppercase; <?php echo (!empty(get_option('wpsa_recaptcha_site_key', '')) && !empty(get_option('wpsa_recaptcha_secret_key', ''))) ? 'background: #d4edda; color: #155724;' : 'background: #fff3cd; color: #856404;'; ?>">
                    <?php echo (!empty(get_option('wpsa_recaptcha_site_key', '')) && !empty(get_option('wpsa_recaptcha_secret_key', ''))) ? __('Configurado', 'wp-self-assessment') : __('Opcional - No configurado', 'wp-self-assessment'); ?>
                </span>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0;">
                <span style="font-weight: bold; color: #333;"><?php _e('Materias registradas:', 'wp-self-assessment'); ?></span>
                <span style="padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold;">
                    <?php
                    $materias = WPSA_Database::get_instance()->get_materias();
                    echo count($materias);
                    ?>
                </span>
            </div>
        </div>
        
    </form>
</div>
