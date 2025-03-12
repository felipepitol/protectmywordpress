<?php
/**
 * Plugin Name: ProtectMyWordPress
 * Plugin URI: https://felipepitol.me/protectmywordpress
 * Description: Plugin de segurança para WordPress com diversas medidas de proteção.
 * Version: 1.0
 * Author: Felipe Pitol
 * Author URI: https://felipepitol.me
 * License: GPL2
 *
 * @package ProtectMyWordPress
 */

if (!defined('ABSPATH')) {
    exit; // Evita acesso direto.
}

// Define constantes para o plugin.
define('PMW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PMW_PLUGIN_URL', plugin_dir_url(__FILE__));

// Carrega os arquivos necessários.
require_once PMW_PLUGIN_DIR . 'includes/class-security.php';
require_once PMW_PLUGIN_DIR . 'includes/class-admin.php';
require_once PMW_PLUGIN_DIR . 'includes/functions.php';

// Inicializa a classe de administração.
new PMW_Admin();

// Inicializa a classe de segurança.
new PMW_Security();
