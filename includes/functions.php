<?php
/**
 * Funções auxiliares para o plugin ProtectMyWordPress.
 *
 * @package ProtectMyWordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retorna as opções padrão do plugin.
 *
 * @return array
 */
function pmw_default_options()
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
function pmw_get_options()
{
    return wp_parse_args((array) get_option('pmw_options', []), pmw_default_options());
}

/**
 * Aplica as regras do .htaccess de uploads assim que as opções são salvas.
 *
 * @param mixed $old_value Valor anterior.
 * @param mixed $value     Novo valor.
 * @return void
 */
function pmw_options_updated($old_value, $value)
{
    PMWSecurity::writeUploadsHtaccess(!empty($value['prevent_uploads_code_execution']));
}
add_action('update_option_pmw_options', 'pmw_options_updated', 10, 2);

/**
 * Função AJAX para checar o status dos módulos.
 */
function pmw_ajax_check_status()
{
    // Verifica nonce para segurança.
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'pmw_ajax_nonce')) {
        wp_send_json_error(__('Falha na verificação de segurança.', 'protectmywordpress'));
    }

    // Verifica se o usuário tem permissão.
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Acesso negado.', 'protectmywordpress'));
    }

    // Usa método estático para obter o status sem efeitos colaterais.
    $status = PMWSecurity::checkModulesStatus();
    wp_send_json_success($status);
}
add_action('wp_ajax_pmw_check_status', 'pmw_ajax_check_status');
