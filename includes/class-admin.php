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
     * Construtor.
     */
    public function __construct()
    {
        // Carrega as opções definidas ou utiliza os valores padrão.
        $this->options = get_option('pmw_options', $this->defaultOptions());
        // Adiciona o menu de configurações no admin.
        add_action('admin_menu', [$this, 'addAdminMenu']);
        // Registra as configurações do plugin.
        add_action('admin_init', [$this, 'registerSettings']);
        // Enfileira os arquivos CSS e JS do admin.
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Retorna as opções padrão do plugin.
     *
     * @return array
     */
    private function defaultOptions()
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
     * Adiciona a página de configurações do plugin ao menu do WordPress.
     *
     * @return void
     */
    public function addAdminMenu()
    {
        add_options_page(
            __('Configurações ProtectMyWordPress', 'protectmywordpress'),
            'ProtectMyWordPress',
            'manage_options',
            'protectmywordpress',
            [$this, 'settingsPage']
        );
    }

    /**
     * Registra as configurações do plugin.
     *
     * @return void
     */
    public function registerSettings()
    {
        register_setting('pmw_option_group', 'pmw_options');
    }

    /**
     * Enfileira os arquivos CSS e JS para o admin.
     *
     * @return void
     */
    public function enqueueAssets()
    {
        wp_enqueue_style(
            'pmw_admin_css',
            plugin_dir_url(__FILE__) . '../assets/css/admin-style.css',
            [],
            PMW_VERSION
        );

        wp_enqueue_script(
            'pmw_admin_js',
            plugin_dir_url(__FILE__) . '../assets/js/admin-script.js',
            ['jquery'],
            PMW_VERSION,
            true
        );

        wp_localize_script('pmw_admin_js', 'pmw_ajax_obj', [
            'ajax_url'       => admin_url('admin-ajax.php'),
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
                'description' => __('Cria regras para impedir a execução de arquivos PHP na pasta uploads.', 'protectmywordpress'),
            ],
            'hide_wp_version' => [
                'title'       => __('Ocultar Versão do WP', 'protectmywordpress'),
                'description' => __('Remove a versão do WordPress do header.', 'protectmywordpress'),
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
                'description' => __('Impede que usuários sejam enumerados através de queries.', 'protectmywordpress'),
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
            
            <!-- Grid com blocos de opções -->
            <div class="pmw-grid">
                <?php
                $optionsData = $this->getOptionData();
                foreach ($this->defaultOptions() as $key => $value) {
                    $checked = isset($this->options[$key]) ? checked(1, $this->options[$key], false) : '';
                    ?>
                    <div class="pmw-option-block">
                        <h2><?php echo esc_html($optionsData[$key]['title']); ?></h2>
                        <p><?php echo esc_html($optionsData[$key]['description']); ?></p>
                        <label class="switch">
                            <input type="checkbox" name="pmw_options[<?php echo esc_attr($key); ?>]" value="1" <?php echo $checked; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                    <?php
                }
                ?>
            </div>

            <?php
            // Campos padrão para salvar as opções.
            settings_fields('pmw_option_group');
            submit_button();
            ?>

            <!-- Botão para checar status dos módulos -->
            <button id="pmw-check-status" class="button button-secondary">
                <?php esc_html_e('Checar Status dos Módulos', 'protectmywordpress'); ?>
            </button>
            <div id="pmw-status-result"></div>

            <!-- Exibe a versão do plugin -->
            <div class="pmw-version">
                <?php echo esc_html__('Versão: ', 'protectmywordpress') . PMW_VERSION; ?>
            </div>
        </div>
        <?php
    }
}
