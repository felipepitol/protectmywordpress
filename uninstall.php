<?php
/**
 * Executa a limpeza ao desinstalar o plugin ProtectMyWordPress.
 *
 * @package ProtectMyWordPress
 */

// Garante que está sendo chamado pelo WordPress.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove as opções do plugin do banco de dados.
delete_option('pmw_options');
