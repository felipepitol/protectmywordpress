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
 * Função AJAX para checar o status dos módulos.
 */
function pmw_ajax_check_status()
{
    // Verifica se o usuário tem permissão.
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Acesso negado.', 'protectmywordpress'));
    }

    // Instancia a classe de segurança para obter o status.
    $pmw_security = new PMWSecurity();
    $status       = $pmw_security->checkModulesStatus();
    wp_send_json_success($status);
}
add_action('wp_ajax_pmw_check_status', 'pmw_ajax_check_status');
