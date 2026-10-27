<?php
/**
 * Plugin Name: ProtectMyWordPress
 * Plugin URI: https://felipepitol.me/protectmywordpress
 * Description: Plugin de segurança para WordPress com diversas medidas de proteção e interface moderna.
 * Version: 1.2
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * Author: Felipe Pitol
 * Author URI: https://felipepitol.me
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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
define('PMW_VERSION', '1.2');

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

    $options = pmw_get_options();

    // Cria os arquivos de proteção na pasta uploads.
    PMWSecurity::writeUploadsHtaccess(!empty($options['prevent_uploads_code_execution']));

    if (!empty($options['disable_directory_browsing'])) {
        $upload_dir = wp_upload_dir(null, false);
        $index_file = trailingslashit($upload_dir['basedir']) . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, "<?php // Silence is golden.");
        }
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
    // Remove as regras do plugin do .htaccess de uploads (regras de outros plugins são mantidas).
    // Os filtros (registro, XML-RPC etc.) deixam de valer sozinhos quando o plugin não carrega.
    PMWSecurity::writeUploadsHtaccess(false);
}

// Inicializa as classes.
new PMWSecurity();

if (is_admin()) {
    new PMWAdmin();
}
