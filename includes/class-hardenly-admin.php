<?php
/**
 * Classe Hardenly_Admin
 *
 * Responsável por criar a interface de administração e registrar as configurações do plugin.
 *
 * @package Hardenly
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Hardenly_Admin
 */
class Hardenly_Admin
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
        $this->options = hardenly_get_options();
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('plugin_action_links_' . plugin_basename(HARDENLY_PLUGIN_DIR . 'hardenly.php'), [$this, 'actionLinks']);
        add_action('admin_notices', [$this, 'legacyPluginNotice']);
    }

    /**
     * Avisa quando a versão antiga (ProtectMyWordPress, distribuída no GitHub) ainda está ativa.
     *
     * @return void
     */
    public function legacyPluginNotice()
    {
        if (!current_user_can('activate_plugins') || !function_exists('is_plugin_active')
            || !is_plugin_active('protectmywordpress/protectmywordpress.php')) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html__('Hardenly replaces ProtectMyWordPress and has already imported its settings. Please deactivate and delete ProtectMyWordPress.', 'hardenly')
        );
    }

    /**
     * Adiciona a página de configurações do plugin ao menu do WordPress.
     *
     * @return void
     */
    public function addAdminMenu()
    {
        $this->hook_suffix = add_options_page(
            __('Hardenly Settings', 'hardenly'),
            'Hardenly',
            'manage_options',
            'hardenly',
            [$this, 'settingsPage']
        );
    }

    /**
     * Link "Settings" na lista de plugins.
     *
     * @param array $links Links existentes.
     * @return array
     */
    public function actionLinks($links)
    {
        array_unshift($links, sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=hardenly')),
            esc_html__('Settings', 'hardenly')
        ));
        return $links;
    }

    /**
     * Registra as configurações do plugin com sanitização.
     *
     * @return void
     */
    public function registerSettings()
    {
        register_setting('hardenly_option_group', 'hardenly_options', [
            'type'              => 'array',
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

        foreach (array_keys(hardenly_default_options()) as $key) {
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
            'hardenly-admin',
            HARDENLY_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            HARDENLY_VERSION
        );

        wp_enqueue_script(
            'hardenly-admin',
            HARDENLY_PLUGIN_URL . 'assets/js/admin-script.js',
            ['jquery'],
            HARDENLY_VERSION,
            true
        );

        $labels = [];
        foreach ($this->getOptionData() as $key => $data) {
            $labels[$key] = $data['title'];
        }

        wp_localize_script('hardenly-admin', 'hardenlyAdmin', [
            'ajaxUrl' => wp_make_link_relative(admin_url('admin-ajax.php')),
            'nonce'   => wp_create_nonce('hardenly_check_status'),
            'labels'  => $labels,
            'i18n'    => [
                'checkFailed'  => __('Could not check the module status.', 'hardenly'),
                'requestError' => __('Could not reach the server.', 'hardenly'),
            ],
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
                'title'       => __('Disable Registration', 'hardenly'),
                'description' => __('Prevents new user registrations.', 'hardenly'),
            ],
            'disable_file_editors' => [
                'title'       => __('Disable File Editors', 'hardenly'),
                'description' => __('Disables theme and plugin file editing in the dashboard.', 'hardenly'),
            ],
            'prevent_uploads_code_execution' => [
                'title'       => __('Prevent Code Execution in Uploads', 'hardenly'),
                'description' => __('Adds .htaccess rules that block PHP files in the uploads folder (Apache and LiteSpeed).', 'hardenly'),
            ],
            'hide_wp_version' => [
                'title'       => __('Hide Version Number', 'hardenly'),
                'description' => __('Removes the WordPress version from the page head and feeds.', 'hardenly'),
            ],
            'prevent_login_feedback' => [
                'title'       => __('Generic Login Errors', 'hardenly'),
                'description' => __('Shows a generic message when a login fails, without saying whether the username exists.', 'hardenly'),
            ],
            'disable_directory_browsing' => [
                'title'       => __('Disable Directory Browsing', 'hardenly'),
                'description' => __('Adds an empty index.php to the uploads folder so its files cannot be listed.', 'hardenly'),
            ],
            'disable_user_enumeration' => [
                'title'       => __('Prevent User Enumeration', 'hardenly'),
                'description' => __('Hides usernames from ?author=N, the REST API, the users sitemap and oEmbed.', 'hardenly'),
            ],
            'unset_x_powered_by' => [
                'title'       => __('Remove X-Powered-By', 'hardenly'),
                'description' => __('Removes the header that reveals the PHP version.', 'hardenly'),
            ],
            'block_admin_username' => [
                'title'       => __('Block "admin" Username', 'hardenly'),
                'description' => __('Blocks new registrations that use the username "admin".', 'hardenly'),
            ],
            'disable_xmlrpc' => [
                'title'       => __('Disable XML-RPC', 'hardenly'),
                'description' => __('Turns off XML-RPC, including pingbacks, and removes the X-Pingback header.', 'hardenly'),
            ],
            'block_registration_same_login_display' => [
                'title'       => __('Hide Username as Display Name', 'hardenly'),
                'description' => __('Prevents users from saving a profile whose public display name is their username.', 'hardenly'),
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
        <div class="wrap hardenly-wrap">
            <h1><?php esc_html_e('Hardenly Settings', 'hardenly'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('hardenly_option_group'); ?>

                <!-- Grid com blocos de opções -->
                <div class="hardenly-grid">
                    <?php foreach ($this->getOptionData() as $key => $data) : ?>
                        <div class="hardenly-option-block">
                            <h2 id="hardenly-<?php echo esc_attr($key); ?>-title"><?php echo esc_html($data['title']); ?></h2>
                            <p><?php echo esc_html($data['description']); ?></p>
                            <label class="hardenly-switch">
                                <input type="checkbox" name="hardenly_options[<?php echo esc_attr($key); ?>]" value="1" aria-labelledby="hardenly-<?php echo esc_attr($key); ?>-title" <?php checked(1, $this->options[$key] ?? 0); ?>>
                                <span class="hardenly-slider hardenly-round"></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php submit_button(); ?>
            </form>

            <!-- Botão para checar status dos módulos -->
            <button type="button" id="hardenly-check-status" class="button button-secondary">
                <?php esc_html_e('Check Module Status', 'hardenly'); ?>
            </button>
            <div id="hardenly-status-result" aria-live="polite"></div>

            <!-- Exibe a versão do plugin -->
            <div class="hardenly-version">
                <?php
                /* translators: %s: plugin version number. */
                echo esc_html(sprintf(__('Version %s', 'hardenly'), HARDENLY_VERSION));
                ?>
            </div>
        </div>
        <?php
    }
}
