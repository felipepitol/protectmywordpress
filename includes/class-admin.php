<?php
/**
 * Classe PMW_Admin
 *
 * Responsável por criar a interface de administração e registrar as configurações do plugin.
 *
 * @package ProtectMyWordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PMW_Admin
 */
class PMW_Admin
{
    /**
     * Array de opções do plugin.
     *
     * @var array
     */
    private $options;

    /**
     * Construtor da classe.
     */
    public function __construct()
    {
        // Carrega as opções do plugin, usando valores padrão se não existirem.
        $this->options = get_option('pmw_options', $this->defaultOptions());

        // Adiciona o menu de configurações no admin.
        add_action('admin_menu', [$this, 'addAdminMenu']);

        // Registra as configurações do plugin.
        add_action('admin_init', [$this, 'registerSettings']);
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
     * Adiciona a página de configurações do plugin ao menu de opções do WordPress.
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

        add_settings_section(
            'pmw_main_section',
            __('Configurações de Segurança', 'protectmywordpress'),
            null,
            'protectmywordpress'
        );

        foreach ($this->defaultOptions() as $key => $value) {
            add_settings_field(
                $key,
                ucwords(str_replace('_', ' ', $key)),
                [$this, 'checkboxCallback'],
                'protectmywordpress',
                'pmw_main_section',
                ['id' => $key]
            );
        }
    }

    /**
     * Renderiza o campo checkbox para cada opção.
     *
     * @param array $args Argumentos do campo.
     *
     * @return void
     */
    public function checkboxCallback($args)
    {
        $id      = $args['id'];
        $checked = isset($this->options[$id]) ? checked(1, $this->options[$id], false) : '';
        echo '<input type="checkbox" id="' . esc_attr($id) . '" name="pmw_options[' . esc_attr($id) . ']" value="1" ' . $checked . ' />';
    }

    /**
     * Renderiza a página de configurações do plugin.
     *
     * @return void
     */
    public function settingsPage()
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configurações ProtectMyWordPress', 'protectmywordpress'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('pmw_option_group');
                do_settings_sections('protectmywordpress');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
