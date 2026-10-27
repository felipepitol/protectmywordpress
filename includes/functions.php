<?php
/**
 * Funções auxiliares do Hardenly.
 *
 * @package Hardenly
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retorna as opções padrão do plugin.
 *
 * @return array
 */
function hardenly_default_options()
{
    return [
        'disable_registration'                  => 1,
        'disable_file_editors'                  => 1,
        'prevent_uploads_code_execution'        => 1,
        'hide_wp_version'                       => 1,
        'prevent_login_feedback'                => 1,
        'disable_directory_browsing'            => 1,
        'disable_user_enumeration'              => 1,
        'unset_x_powered_by'                    => 1,
        'block_admin_username'                  => 1,
        'disable_xmlrpc'                        => 1,
        'block_registration_same_login_display' => 1,
    ];
}

/**
 * Retorna as opções do plugin mescladas com os padrões.
 *
 * @return array
 */
function hardenly_get_options()
{
    return wp_parse_args((array) get_option('hardenly_options', []), hardenly_default_options());
}

/**
 * Migra a configuração da versão distribuída no GitHub como "ProtectMyWordPress".
 *
 * Copia as opções antigas (pmw_options) e remove o bloco antigo do .htaccess de uploads.
 *
 * @return void
 */
function hardenly_migrate_legacy()
{
    $legacy = get_option('pmw_options');

    if (false !== $legacy) {
        if (false === get_option('hardenly_options')) {
            update_option('hardenly_options', wp_parse_args((array) $legacy, hardenly_default_options()));
        }
        delete_option('pmw_options');
    }

    Hardenly_Security::removeLegacyHtaccessBlock();
}

/**
 * Aplica as regras do .htaccess de uploads assim que as opções são salvas.
 *
 * @param mixed $old_value Valor anterior.
 * @param mixed $value     Novo valor.
 * @return void
 */
function hardenly_options_updated($old_value, $value)
{
    Hardenly_Security::writeUploadsHtaccess(!empty($value['prevent_uploads_code_execution']));

    if (!empty($value['disable_directory_browsing'])) {
        Hardenly_Security::writeUploadsIndex();
    }
}
add_action('update_option_hardenly_options', 'hardenly_options_updated', 10, 2);

/**
 * Função AJAX para checar o status dos módulos.
 *
 * @return void
 */
function hardenly_ajax_check_status()
{
    check_ajax_referer('hardenly_check_status', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Access denied.', 'hardenly'), 403);
    }

    wp_send_json_success(Hardenly_Security::checkModulesStatus());
}
add_action('wp_ajax_hardenly_check_status', 'hardenly_ajax_check_status');
