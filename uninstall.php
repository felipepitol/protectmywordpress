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

// Remove o bloco de regras do plugin do .htaccess de uploads, se existir.
$pmw_upload_dir = wp_upload_dir(null, false);
$pmw_htaccess   = trailingslashit($pmw_upload_dir['basedir']) . '.htaccess';
if (file_exists($pmw_htaccess)) {
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    insert_with_markers($pmw_htaccess, 'ProtectMyWordPress', []);
}
