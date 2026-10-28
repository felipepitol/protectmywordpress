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

        wp_localize_script('hardenly-admin', 'hardenlyAdmin', [
            'ajaxUrl' => wp_make_link_relative(admin_url('admin-ajax.php')),
            'nonce'   => wp_create_nonce('hardenly_check_status'),
            'i18n'    => [
                'checkFailed'  => __('Could not check the module status.', 'hardenly'),
                'requestError' => __('Could not reach the server.', 'hardenly'),
                'checking'     => __('Checking…', 'hardenly'),
                'unsaved'      => __('You have unsaved changes.', 'hardenly'),
                'ok'           => __('Working', 'hardenly'),
                'off'          => __('Off', 'hardenly'),
                'error'        => __('Problem', 'hardenly'),
                /* translators: 1: number of protections working, 2: number of protections with a problem. */
                'summary'      => __('%1$s working, %2$s with a problem.', 'hardenly'),
                /* translators: %s: number of protections working. */
                'summaryOk'    => __('All %s active protections are working.', 'hardenly'),
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
                'title'       => __('Disable registration', 'hardenly'),
                'description' => __('Prevents new user registrations.', 'hardenly'),
            ],
            'disable_file_editors' => [
                'title'       => __('Disable file editors', 'hardenly'),
                'description' => __('Disables theme and plugin file editing in the dashboard.', 'hardenly'),
            ],
            'prevent_uploads_code_execution' => [
                'title'       => __('Block PHP in uploads', 'hardenly'),
                'description' => __('Adds .htaccess rules that block PHP files in the uploads folder (Apache and LiteSpeed).', 'hardenly'),
            ],
            'hide_wp_version' => [
                'title'       => __('Hide version number', 'hardenly'),
                'description' => __('Removes the WordPress version from the page head and feeds.', 'hardenly'),
            ],
            'prevent_login_feedback' => [
                'title'       => __('Generic login errors', 'hardenly'),
                'description' => __('Shows a generic message when a login fails, without saying whether the username exists.', 'hardenly'),
            ],
            'disable_directory_browsing' => [
                'title'       => __('Disable directory listing', 'hardenly'),
                'description' => __('Adds an empty index.php to the uploads folder so its files cannot be listed.', 'hardenly'),
            ],
            'disable_user_enumeration' => [
                'title'       => __('Prevent user enumeration', 'hardenly'),
                'description' => __('Hides usernames from ?author=N, the REST API, the users sitemap and oEmbed.', 'hardenly'),
            ],
            'unset_x_powered_by' => [
                'title'       => __('Remove X-Powered-By header', 'hardenly'),
                'description' => __('Removes the header that reveals the PHP version.', 'hardenly'),
            ],
            'block_admin_username' => [
                'title'       => __('Block the "admin" username', 'hardenly'),
                'description' => __('Blocks new registrations that use the username "admin".', 'hardenly'),
            ],
            'disable_xmlrpc' => [
                'title'       => __('Disable XML-RPC', 'hardenly'),
                'description' => __('Turns off XML-RPC, including pingbacks, and removes the X-Pingback header.', 'hardenly'),
            ],
            'block_registration_same_login_display' => [
                'title'       => __('Require a display name other than the username', 'hardenly'),
                'description' => __('Prevents users from saving a profile whose public display name is their username.', 'hardenly'),
            ],
        ];
    }

    /**
     * Agrupa as opções por tipo de proteção, na ordem exibida na página.
     *
     * @return array
     */
    private function getGroups()
    {
        return [
            'accounts' => [
                'title'       => __('Accounts and login', 'hardenly'),
                'description' => __('Make it harder to guess usernames or create accounts on this site.', 'hardenly'),
                'options'     => ['disable_registration', 'block_admin_username', 'block_registration_same_login_display', 'prevent_login_feedback', 'disable_user_enumeration'],
            ],
            'files' => [
                'title'       => __('Files and uploads', 'hardenly'),
                'description' => __('Stop uploaded or edited files from being used to run code.', 'hardenly'),
                'options'     => ['disable_file_editors', 'prevent_uploads_code_execution', 'disable_directory_browsing'],
            ],
            'disclosure' => [
                'title'       => __('Server details', 'hardenly'),
                'description' => __('Hide the software versions that attackers use to pick known exploits.', 'hardenly'),
                'options'     => ['hide_wp_version', 'unset_x_powered_by'],
            ],
            'remote' => [
                'title'       => __('Remote access', 'hardenly'),
                'description' => __('Close an old remote publishing interface that is often used for brute-force attacks.', 'hardenly'),
                'options'     => ['disable_xmlrpc'],
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
        $option_data = $this->getOptionData();
        $total       = count($option_data);
        $enabled     = 0;
        foreach (array_keys($option_data) as $key) {
            $enabled += empty($this->options[$key]) ? 0 : 1;
        }
        ?>
        <div class="wrap hardenly-wrap">
            <header class="hardenly-header">
                <div class="hardenly-header__text">
                    <h1><?php esc_html_e('Hardenly', 'hardenly'); ?></h1>
                    <p><?php esc_html_e('Turn on the protections you want for this site. Changes apply as soon as you save.', 'hardenly'); ?></p>
                </div>
                <div class="hardenly-meter" data-total="<?php echo esc_attr($total); ?>">
                    <p class="hardenly-meter__label">
                        <?php
                        printf(
                            /* translators: 1: number of protections turned on, 2: total number of protections. */
                            esc_html__('%1$s of %2$s protections on', 'hardenly'),
                            '<strong class="hardenly-meter__count">' . esc_html($enabled) . '</strong>',
                            esc_html($total)
                        );
                        ?>
                    </p>
                    <ol class="hardenly-meter__bar" aria-hidden="true">
                        <?php foreach ($option_data as $key => $data) : ?>
                            <li data-key="<?php echo esc_attr($key); ?>" class="<?php echo empty($this->options[$key]) ? '' : 'is-on'; ?>" title="<?php echo esc_attr($data['title']); ?>"></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </header>

            <form method="post" action="options.php" id="hardenly-form">
                <?php settings_fields('hardenly_option_group'); ?>

                <?php foreach ($this->getGroups() as $group_key => $group) : ?>
                    <section class="hardenly-group" aria-labelledby="hardenly-group-<?php echo esc_attr($group_key); ?>">
                        <div class="hardenly-group__head">
                            <h2 id="hardenly-group-<?php echo esc_attr($group_key); ?>"><?php echo esc_html($group['title']); ?></h2>
                            <p><?php echo esc_html($group['description']); ?></p>
                        </div>
                        <ul class="hardenly-list">
                            <?php foreach ($group['options'] as $key) :
                                $data = $option_data[$key];
                                $id   = 'hardenly-' . $key;
                                ?>
                                <li class="hardenly-row" data-key="<?php echo esc_attr($key); ?>">
                                    <div class="hardenly-row__text">
                                        <label for="<?php echo esc_attr($id); ?>" class="hardenly-row__title"><?php echo esc_html($data['title']); ?></label>
                                        <p class="hardenly-row__desc" id="<?php echo esc_attr($id); ?>-desc"><?php echo esc_html($data['description']); ?></p>
                                        <p class="hardenly-row__problem" hidden></p>
                                    </div>
                                    <span class="hardenly-badge" hidden></span>
                                    <span class="hardenly-toggle">
                                        <input type="checkbox" role="switch" id="<?php echo esc_attr($id); ?>" name="hardenly_options[<?php echo esc_attr($key); ?>]" value="1" aria-describedby="<?php echo esc_attr($id); ?>-desc" <?php checked(1, $this->options[$key] ?? 0); ?>>
                                        <span class="hardenly-toggle__track" aria-hidden="true"></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>

                <div class="hardenly-actions">
                    <?php submit_button(__('Save changes', 'hardenly'), 'primary', 'submit', false); ?>
                    <button type="button" id="hardenly-check-status" class="button">
                        <?php esc_html_e('Check status', 'hardenly'); ?>
                    </button>
                    <p id="hardenly-status-result" class="hardenly-actions__status" aria-live="polite"></p>
                    <p class="hardenly-actions__version">
                        <?php
                        /* translators: %s: plugin version number. */
                        echo esc_html(sprintf(__('Version %s', 'hardenly'), HARDENLY_VERSION));
                        ?>
                    </p>
                </div>
            </form>
        </div>
        <?php
    }
}
