<?php
/**
 * Plugin Name: Hardenly – Security Hardening
 * Plugin URI: https://github.com/felipepitol/protectmywordpress
 * Description: Turn on common security hardening measures with simple switches: block PHP in uploads, disable XML-RPC, stop user enumeration, hide the version and more.
 * Version: 1.2.0
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * Author: Felipe Pitol
 * Author URI: https://felipepitol.me
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hardenly
 *
 * @package Hardenly
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constantes para o plugin.
define('HARDENLY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HARDENLY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HARDENLY_VERSION', '1.2.0');

// Carrega os arquivos necessários (functions.php primeiro, pois contém hardenly_default_options/hardenly_get_options).
require_once HARDENLY_PLUGIN_DIR . 'includes/functions.php';
require_once HARDENLY_PLUGIN_DIR . 'includes/class-hardenly-security.php';
require_once HARDENLY_PLUGIN_DIR . 'includes/class-hardenly-admin.php';

// Hook de ativação.
register_activation_hook(__FILE__, 'hardenly_activate');

/**
 * Executa ações na ativação do plugin.
 *
 * @return void
 */
function hardenly_activate()
{
    hardenly_migrate_legacy();

    // Define opções padrão se ainda não existirem.
    if (false === get_option('hardenly_options')) {
        update_option('hardenly_options', hardenly_default_options());
    }

    $options = hardenly_get_options();

    // Cria os arquivos de proteção na pasta uploads.
    Hardenly_Security::writeUploadsHtaccess(!empty($options['prevent_uploads_code_execution']));

    if (!empty($options['disable_directory_browsing'])) {
        Hardenly_Security::writeUploadsIndex();
    }
}

// Hook de desativação.
register_deactivation_hook(__FILE__, 'hardenly_deactivate');

/**
 * Executa ações na desativação do plugin.
 *
 * @return void
 */
function hardenly_deactivate()
{
    // Remove as regras do plugin do .htaccess de uploads (regras de outros plugins são mantidas).
    // Os filtros (registro, XML-RPC etc.) deixam de valer sozinhos quando o plugin não carrega.
    Hardenly_Security::writeUploadsHtaccess(false);
}

// Inicializa as classes.
new Hardenly_Security();

if (is_admin()) {
    new Hardenly_Admin();
}
