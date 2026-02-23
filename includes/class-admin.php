<?php
/**
 * Classe PMWAdmin
 *
 * Responsável por criar a interface de administração e registrar as configurações do plugin.
 *
 * @package ProtectMyWordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PMWAdmin
 */
class PMWAdmin
{
    /**
     * Array de opções do plugin.
     *
     * @var array
     */
    private $options;

    /**
     * Hook suffix da página de configurações.
     *
     * @var string
     */
    private $hook_suffix;

    /**
     * Construtor.
     */
    public function __construct()
    {
        $this->options = pmw_get_options();
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Adiciona a página de configurações do plugin ao menu do WordPress.
     *
     * @return void
     */
    public function addAdminMenu()
    {
        $this->hook_suffix = add_options_page(
            __('Configurações ProtectMyWordPress', 'protectmywordpress'),
            'ProtectMyWordPress',
            'manage_options',
            'protectmywordpress',
            [$this, 'settingsPage']
        );
    }

    /**
     * Registra as configurações do plugin com sanitização.
     *
     * @return void
     */
    public function registerSettings()
    {
        register_setting('pmw_option_group', 'pmw_options', [
            'sanitize_callback' => [$this, 'sanitizeOptions'],
        ]);
    }

    /**
     * Sanitiza as opções antes de salvar no banco de dados.
     *
     * @param array $input Valores recebidos do formulário.
     * @return array Valores sanitizados.
     */
    public function sanitizeOptions($input)
    {
        $sanitized = [];
        $valid_keys = array_keys(pmw_default_options());

        foreach ($valid_keys as $key) {
            $sanitized[$key] = !empty($input[$key]) ? 1 : 0;
        }

        return $sanitized;
    }

    /**
     * Enfileira os arquivos CSS e JS apenas na página do plugin.
     *
     * @param string $hook_suffix O sufixo do hook da página atual.
     * @return void
     */
    public function enqueueAssets($hook_suffix)
    {
        if ($hook_suffix !== $this->hook_suffix) {
            return;
        }

        wp_enqueue_style(
            'pmw_admin_css',
            PMW_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            PMW_VERSION
        );

        wp_enqueue_script(
            'pmw_admin_js',
            PMW_PLUGIN_URL . 'assets/js/admin-script.js',
            ['jquery'],
            PMW_VERSION,
            true
        );

        wp_localize_script('pmw_admin_js', 'pmw_ajax_obj', [
            'ajax_url'       => wp_make_link_relative(admin_url('admin-ajax.php')),
            'nonce'          => wp_create_nonce('pmw_ajax_nonce'),
            'plugin_version' => PMW_VERSION,
        ]);
    }

    /**
     * Retorna os dados (título e descrição) para cada opção.
     *
     * @return array
     */
    private function getOptionData()
    {
        return [
            'disable_registration' => [
                'title'       => __('Desabilitar Registro', 'protectmywordpress'),
                'description' => __('Impede o registro de novos usuários.', 'protectmywordpress'),
            ],
            'disable_file_editors' => [
                'title'       => __('Desabilitar Editores de Arquivo', 'protectmywordpress'),
                'description' => __('Desativa a edição de arquivos através do painel.', 'protectmywordpress'),
            ],
            'prevent_uploads_code_execution' => [
                'title'       => __('Prevenir Execução de Código em Uploads', 'protectmywordpress'),
                'description' => __('Cria regras para impedir a execução de arquivos PHP na pasta uploads (Apache).', 'protectmywordpress'),
            ],
            'hide_wp_version' => [
                'title'       => __('Ocultar Versão do WP', 'protectmywordpress'),
                'description' => __('Remove a versão do WordPress do header e feeds.', 'protectmywordpress'),
            ],
            'prevent_login_feedback' => [
                'title'       => __('Prevenir Feedback de Login', 'protectmywordpress'),
                'description' => __('Exibe uma mensagem genérica em caso de falha no login.', 'protectmywordpress'),
            ],
            'disable_directory_browsing' => [
                'title'       => __('Desabilitar Navegação de Diretórios', 'protectmywordpress'),
                'description' => __('Previne a listagem de arquivos em diretórios, especialmente na pasta uploads.', 'protectmywordpress'),
            ],
            'disable_user_enumeration' => [
                'title'       => __('Prevenir Enumeração de Usuários', 'protectmywordpress'),
                'description' => __('Impede a enumeração de usuários via query string e REST API.', 'protectmywordpress'),
            ],
            'unset_x_powered_by' => [
                'title'       => __('Remover X-Powered-By', 'protectmywordpress'),
                'description' => __('Remove o header que revela a versão do PHP.', 'protectmywordpress'),
            ],
            'block_admin_username' => [
                'title'       => __('Bloquear Usuário Admin', 'protectmywordpress'),
                'description' => __('Bloqueia o registro usando o nome de usuário "admin".', 'protectmywordpress'),
            ],
            'disable_xmlrpc' => [
                'title'       => __('Desabilitar XML-RPC', 'protectmywordpress'),
                'description' => __('Desativa a funcionalidade XML-RPC.', 'protectmywordpress'),
            ],
            'block_registration_same_login_display' => [
                'title'       => __('Bloquear Registro com Nome Igual', 'protectmywordpress'),
                'description' => __('Evita registros onde o login e o nome de exibição são iguais.', 'protectmywordpress'),
            ],
        ];
    }

    /**
     * Renderiza a página de configurações do plugin.
     *
     * @return void
     */
    public function settingsPage()
    {
        ?>
        <div class="wrap pmw-wrap">
            <h1><?php esc_html_e('Configurações ProtectMyWordPress', 'protectmywordpress'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('pmw_option_group'); ?>

                <!-- Grid com blocos de opções -->
                <div class="pmw-grid">
                    <?php
                    $optionsData = $this->getOptionData();
                    $defaults    = pmw_default_options();
                    foreach ($defaults as $key => $value) {
                        $checked = isset($this->options[$key]) ? checked(1, $this->options[$key], false) : '';
                        ?>
                        <div class="pmw-option-block">
                            <h2><?php echo esc_html($optionsData[$key]['title']); ?></h2>
                            <p><?php echo esc_html($optionsData[$key]['description']); ?></p>
                            <label class="pmw-switch">
                                <input type="checkbox" name="pmw_options[<?php echo esc_attr($key); ?>]" value="1" <?php echo $checked; ?>>
                                <span class="pmw-slider pmw-round"></span>
                            </label>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <?php submit_button(); ?>
            </form>

            <!-- Botão para checar status dos módulos -->
            <button id="pmw-check-status" class="button button-secondary">
                <?php esc_html_e('Checar Status dos Módulos', 'protectmywordpress'); ?>
            </button>
            <div id="pmw-status-result"></div>

            <!-- Exibe a versão do plugin -->
            <div class="pmw-version">
                <?php echo esc_html__('Versão: ', 'protectmywordpress') . esc_html(PMW_VERSION); ?>
            </div>
        </div>
        <?php
    }
}
