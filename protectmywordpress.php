<?php
/**
 * Plugin Name: ProtectMyWordPress
 * Plugin URI: https://felipepitol.me/protectmywordpress
 * Description: Plugin de segurança para WordPress com diversas medidas de proteção e interface moderna.
 * Version: 1.1
 * Author: Felipe Pitol
 * Author URI: https://felipepitol.me
 * License: GPL2
 * Text Domain: protectmywordpress
 * Domain Path: /languages
 *
 * @package ProtectMyWordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constantes para o plugin.
define('PMW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PMW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PMW_VERSION', '1.1');

// Carrega os arquivos necessários (functions.php primeiro, pois contém pmw_default_options/pmw_get_options).
require_once PMW_PLUGIN_DIR . 'includes/functions.php';
require_once PMW_PLUGIN_DIR . 'includes/class-security.php';
require_once PMW_PLUGIN_DIR . 'includes/class-admin.php';

// Carrega o text domain para traduções.
add_action('init', function () {
    load_plugin_textdomain('protectmywordpress', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Hook de ativação.
register_activation_hook(__FILE__, 'pmw_activate');

/**
 * Executa ações na ativação do plugin.
 *
 * @return void
 */
function pmw_activate()
{
    // Define opções padrão se ainda não existirem.
    if (false === get_option('pmw_options')) {
        update_option('pmw_options', pmw_default_options());
    }

    // Cria arquivos de proteção na pasta uploads.
    $upload_dir = wp_upload_dir();
    $basedir    = trailingslashit($upload_dir['basedir']);

    // .htaccess para bloquear execução de PHP.
    $htaccess_file = $basedir . '.htaccess';
    if (!file_exists($htaccess_file)) {
        $rules  = "<FilesMatch \"\.(php|php\.)$\">\n";
        $rules .= "deny from all\n";
        $rules .= "</FilesMatch>\n";
        file_put_contents($htaccess_file, $rules);
    }

    // index.php para impedir listagem de diretório.
    $index_file = $basedir . 'index.php';
    if (!file_exists($index_file)) {
        file_put_contents($index_file, "<?php // Silence is golden.");
    }
}

// Hook de desativação.
register_deactivation_hook(__FILE__, 'pmw_deactivate');

/**
 * Executa ações na desativação do plugin.
 *
 * @return void
 */
function pmw_deactivate()
{
    // Remove o filtro que força users_can_register = 0,
    // restaurando o comportamento padrão do WordPress.
    remove_filter('option_users_can_register', '__return_zero');
}

// Inicializa as classes.
new PMWSecurity();

if (is_admin()) {
    new PMWAdmin();
}
