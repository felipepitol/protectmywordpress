<?php
/**
 * Executa a limpeza ao desinstalar o Hardenly.
 *
 * @package Hardenly
 */

// Garante que está sendo chamado pelo WordPress.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove as opções do plugin (e as da versão antiga, se ainda existirem).
delete_option('hardenly_options');
delete_option('pmw_options');

// Remove o bloco de regras do plugin do .htaccess de uploads, se existir.
$hardenly_upload_dir = wp_upload_dir(null, false);
$hardenly_htaccess   = trailingslashit($hardenly_upload_dir['basedir']) . '.htaccess';
if (file_exists($hardenly_htaccess)) {
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    insert_with_markers($hardenly_htaccess, 'Hardenly', []);
}
