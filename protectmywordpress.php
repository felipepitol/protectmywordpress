<?php
/**
 * Plugin Name: ProtectMyWordPress
 * Plugin URI: https://felipepitol.me/protectmywordpress
 * Description: Plugin de segurança para WordPress com diversas medidas de proteção e interface moderna.
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
define('PMW_VERSION', '1.0');

// Carrega os arquivos necessários.
require_once PMW_PLUGIN_DIR . 'includes/class-security.php';
require_once PMW_PLUGIN_DIR . 'includes/class-admin.php';
require_once PMW_PLUGIN_DIR . 'includes/functions.php';

// Inicializa as classes.
new PMWAdmin();
new PMWSecurity();
