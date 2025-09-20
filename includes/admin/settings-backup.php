<?php
/**
 * Página de configuración del plugin - Versión de respaldo
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Configuración - Autoevaluaciones', 'wp-self-assessment'); ?></h1>
    
    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php _e('¡Configuración guardada correctamente!', 'wp-self-assessment'); ?></strong></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="" id="wpsa-settings-form">
        <?php wp_nonce_field('wpsa_settings', '_wpnonce', true, true); ?>
        
        <h2><?php _e('Configuración de API', 'wp-self-assessment'); ?></h2>
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
            </tbody>
        </table>
        
        <h2><?php _e('Configuración de reCAPTCHA', 'wp-self-assessment'); ?></h2>
        <table class="form-table">
            <tbody>
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
        
        <div class="wpsa-settings-actions">
            <h3><?php _e('Guardar Configuración', 'wp-self-assessment'); ?></h3>
            <p class="description"><?php _e('Haz clic en el botón para guardar todos los cambios realizados.', 'wp-self-assessment'); ?></p>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary button-large" value="<?php _e('Guardar Configuración', 'wp-self-assessment'); ?>" />
            </p>
        </div>
    </form>
</div>

<style>
.wpsa-settings-actions {
    margin-top: 30px;
    padding: 25px;
    border-top: 3px solid #0073aa;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    text-align: left;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.wpsa-settings-actions h3 {
    margin: 0 0 10px 0;
    color: #0073aa;
    font-size: 18px;
    font-weight: 600;
}

.wpsa-settings-actions .description {
    margin: 0 0 20px 0;
    color: #666;
    font-style: italic;
}

.wpsa-settings-actions .submit {
    margin: 0;
    padding: 0;
}

.wpsa-settings-actions .button-primary {
    font-size: 18px;
    padding: 15px 30px;
    height: auto;
    line-height: 1.4;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(0,115,170,0.3);
    border: none;
    border-radius: 6px;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
    color: white;
    cursor: pointer;
}

.wpsa-settings-actions .button-primary:hover {
    background: linear-gradient(135deg, #005a87 0%, #004466 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,115,170,0.4);
}

.wpsa-settings-actions .button-primary:active {
    transform: translateY(0);
    box-shadow: 0 2px 8px rgba(0,115,170,0.3);
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('Página de configuración cargada');
    console.log('Formulario encontrado:', $('#wpsa-settings-form').length);
    console.log('Botón encontrado:', $('#submit').length);
    
    // Verificar que el formulario funcione
    $('#wpsa-settings-form').on('submit', function(e) {
        console.log('Formulario enviado');
        return true;
    });
});
</script>
