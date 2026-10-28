<?php
/**
 * Classe Hardenly_Security
 *
 * Responsável por aplicar as medidas de segurança do plugin e fornecer verificação de status.
 *
 * @package Hardenly
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Hardenly_Security
 */
class Hardenly_Security
{
    /**
     * Marcador do bloco de regras do plugin no .htaccess de uploads.
     */
    const HTACCESS_MARKER = 'Hardenly';

    /**
     * Marcador usado pela versão distribuída como "ProtectMyWordPress".
     */
    const LEGACY_HTACCESS_MARKER = 'ProtectMyWordPress';

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
        $this->options = hardenly_get_options();
        $this->applySecurityMeasures();
    }

    /**
     * Aplica as medidas de segurança de acordo com as opções definidas.
     *
     * @return void
     */
    public function applySecurityMeasures()
    {
        // Desabilita o registro público de usuários via filtro (sem DB write a cada request).
        if (!empty($this->options['disable_registration'])) {
            add_filter('option_users_can_register', '__return_zero');
        }

        // Desabilita os editores de arquivos internos.
        if (!empty($this->options['disable_file_editors'])) {
            if (!defined('DISALLOW_FILE_EDIT')) {
                // Constante do core do WordPress, não do plugin.
                define('DISALLOW_FILE_EDIT', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
            }

            // Remove o submenu de edição como reforço, já que a constante pode ter sido definida tarde demais.
            add_action('admin_menu', function () {
                remove_submenu_page('themes.php', 'theme-editor.php');
                remove_submenu_page('plugins.php', 'plugin-editor.php');
            }, 999);
        }

        // Regras de uploads: gravadas na ativação e ao salvar as opções; admin_init só repara
        // o arquivo se ele tiver sido alterado (nunca escreve em requests do front-end).
        add_action('admin_init', [$this, 'syncUploadsFiles']);

        // Oculta a versão do WordPress.
        if (!empty($this->options['hide_wp_version'])) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
        }

        // Previne feedback detalhado no login (só no formulário de login, para não esconder
        // os erros de registro e de recuperação de senha).
        if (!empty($this->options['prevent_login_feedback'])) {
            add_filter('login_errors', [$this, 'genericLoginError']);
        }

        // Previne a enumeração de usuários (query param, REST API, sitemap e oEmbed).
        if (!empty($this->options['disable_user_enumeration'])) {
            add_action('parse_request', [$this, 'disableUserEnumeration']);
            add_filter('rest_endpoints', [$this, 'disableUserRestEndpoint']);
            add_filter('wp_sitemaps_add_provider', [$this, 'removeUsersSitemap'], 10, 2);
            add_filter('oembed_response_data', [$this, 'removeOembedAuthor']);
        }

        // Remove o header X-Powered-By no momento correto.
        if (!empty($this->options['unset_x_powered_by'])) {
            add_action('send_headers', function () {
                header_remove('X-Powered-By');
            });
        }

        // Bloqueia o nome de usuário "admin" durante o registro.
        if (!empty($this->options['block_admin_username'])) {
            add_filter('registration_errors', [$this, 'blockAdminUsername'], 10, 3);
        }

        // Desabilita o XML-RPC. xmlrpc_enabled só bloqueia métodos autenticados; sem esvaziar
        // xmlrpc_methods, pingback.ping (usado em DDoS e varredura de portas) continua aberto.
        if (!empty($this->options['disable_xmlrpc'])) {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('xmlrpc_methods', '__return_empty_array');
            add_filter('wp_headers', [$this, 'removePingbackHeader']);
        }

        // Impede salvar um perfil cujo nome de exibição público seja o próprio login.
        if (!empty($this->options['block_registration_same_login_display'])) {
            add_action('user_profile_update_errors', [$this, 'blockSameLoginDisplay'], 10, 3);
        }
    }

    /**
     * Retorna o WP_Filesystem quando ele pode gravar diretamente (sem pedir credenciais).
     *
     * @return WP_Filesystem_Base|null
     */
    private static function filesystem()
    {
        global $wp_filesystem;

        require_once ABSPATH . 'wp-admin/includes/file.php';

        if ('direct' !== get_filesystem_method()) {
            return null;
        }

        if (!$wp_filesystem instanceof WP_Filesystem_Base && !WP_Filesystem()) {
            return null;
        }

        return $wp_filesystem;
    }

    /**
     * Pasta de uploads (com barra no final).
     *
     * @return string
     */
    private static function uploadsDir()
    {
        $upload_dir = wp_upload_dir(null, false);
        return trailingslashit($upload_dir['basedir']);
    }

    /**
     * Regras que bloqueiam a execução de PHP na pasta de uploads (Apache 2.2 e 2.4).
     *
     * Cobre extensões que alguns servidores também executam (.phtml, .php5, .phar...)
     * e nomes com extensão dupla como "arquivo.php.jpg".
     *
     * @return string[]
     */
    public static function uploadsHtaccessRules()
    {
        return [
            '<FilesMatch "\\.(?i:php[0-9]?|pht|phtml|phar|phps)(\\..*)?$">',
            '    <IfModule mod_authz_core.c>',
            '        Require all denied',
            '    </IfModule>',
            '    <IfModule !mod_authz_core.c>',
            '        Order allow,deny',
            '        Deny from all',
            '    </IfModule>',
            '</FilesMatch>',
        ];
    }

    /**
     * Grava (ou remove) o bloco de regras do plugin no .htaccess de uploads.
     *
     * Usa insert_with_markers(), que preserva regras de outros plugins no mesmo arquivo.
     *
     * @param bool $enabled Se as regras devem estar presentes.
     * @return bool Se o arquivo ficou no estado esperado.
     */
    public static function writeUploadsHtaccess($enabled)
    {
        $file = self::uploadsDir() . '.htaccess';

        if (!$enabled && !file_exists($file)) {
            return true;
        }

        require_once ABSPATH . 'wp-admin/includes/misc.php';

        return insert_with_markers($file, self::HTACCESS_MARKER, $enabled ? self::uploadsHtaccessRules() : []);
    }

    /**
     * Remove as regras gravadas pelas versões "ProtectMyWordPress" (1.1 sem marcador, 1.2 com marcador).
     *
     * @return void
     */
    public static function removeLegacyHtaccessBlock()
    {
        $file = self::uploadsDir() . '.htaccess';
        $fs   = self::filesystem();

        if (!$fs || !$fs->exists($file)) {
            return;
        }

        $content = (string) $fs->get_contents($file);
        $legacy  = "<FilesMatch \"\\.(php|php\\.)$\">\ndeny from all\n</FilesMatch>\n";

        $cleaned = $content === $legacy ? '' : preg_replace(
            '/\n?# BEGIN ' . self::LEGACY_HTACCESS_MARKER . '\n.*?# END ' . self::LEGACY_HTACCESS_MARKER . '\n?/s',
            "\n",
            $content
        );

        if ($cleaned !== $content) {
            $fs->put_contents($file, ltrim($cleaned, "\n"), FS_CHMOD_FILE);
        }
    }

    /**
     * Verifica se o bloco de regras do plugin está presente no .htaccess de uploads.
     *
     * @return bool
     */
    public static function uploadsHtaccessHasRules()
    {
        $file = self::uploadsDir() . '.htaccess';
        if (!file_exists($file)) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/misc.php';

        return extract_from_markers($file, self::HTACCESS_MARKER) === self::uploadsHtaccessRules();
    }

    /**
     * Cria um index.php vazio na pasta de uploads para impedir a listagem do diretório.
     *
     * @return bool Se o arquivo existe ao final.
     */
    public static function writeUploadsIndex()
    {
        $file = self::uploadsDir() . 'index.php';

        if (file_exists($file)) {
            return true;
        }

        $fs = self::filesystem();

        return $fs && $fs->put_contents($file, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE);
    }

    /**
     * Mantém os arquivos de uploads de acordo com as opções (repara se foram alterados ou removidos).
     *
     * @return void
     */
    public function syncUploadsFiles()
    {
        // Limpa o bloco deixado pela versão antiga quando ela não está mais ativa.
        $htaccess = self::uploadsDir() . '.htaccess';
        $fs       = self::filesystem();
        if ($fs && $fs->exists($htaccess)
            && false !== strpos((string) $fs->get_contents($htaccess), '# BEGIN ' . self::LEGACY_HTACCESS_MARKER)
            && !(function_exists('is_plugin_active') && is_plugin_active('protectmywordpress/protectmywordpress.php'))) {
            self::removeLegacyHtaccessBlock();
        }

        $enabled = !empty($this->options['prevent_uploads_code_execution']);

        if ($enabled !== self::uploadsHtaccessHasRules() && !self::writeUploadsHtaccess($enabled) && $enabled) {
            $this->fileNotice(__('Hardenly could not write the protection rules to .htaccess in the uploads folder. Please check the file permissions.', 'hardenly'));
        }

        if (!empty($this->options['disable_directory_browsing']) && !self::writeUploadsIndex()) {
            $this->fileNotice(__('Hardenly could not create index.php in the uploads folder. Please check the file permissions.', 'hardenly'));
        }
    }

    /**
     * Exibe um aviso de erro no painel.
     *
     * @param string $message Mensagem já traduzida.
     * @return void
     */
    private function fileNotice($message)
    {
        add_action('admin_notices', function () use ($message) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
        });
    }

    /**
     * Previne a enumeração de usuários via ?author=N no front-end.
     *
     * Usa a query var já interpretada pelo WordPress, que vem tanto de GET quanto de POST
     * (e aceita listas como "1,2"). O painel fica de fora: a lista de posts do admin também
     * passa por parse_request e usa ?author=N para filtrar por autor.
     *
     * @param WP $wp Objeto da requisição atual.
     * @return void
     */
    public function disableUserEnumeration($wp)
    {
        if (is_admin()) {
            return;
        }

        if (!empty($wp->query_vars['author'])) {
            wp_safe_redirect(home_url(), 301);
            exit;
        }
    }

    /**
     * Remove os endpoints REST de listagem de usuários para quem não edita posts.
     *
     * Autores e editores mantêm o acesso, que o editor de blocos usa para listar autores;
     * visitantes e assinantes ficam sem. /wp/v2/users/me não é afetado.
     *
     * @param array $endpoints Array de endpoints REST.
     * @return array
     */
    public function disableUserRestEndpoint($endpoints)
    {
        if (!current_user_can('edit_posts')) {
            unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        }
        return $endpoints;
    }

    /**
     * Remove o sitemap de usuários, que lista a URL /author/<login> de cada autor.
     *
     * @param WP_Sitemaps_Provider|false $provider Provider do sitemap.
     * @param string                     $name     Nome do provider.
     * @return WP_Sitemaps_Provider|false
     */
    public function removeUsersSitemap($provider, $name)
    {
        return 'users' === $name ? false : $provider;
    }

    /**
     * Remove nome e URL do autor da resposta oEmbed (a URL contém o login).
     *
     * @param array $data Dados da resposta oEmbed.
     * @return array
     */
    public function removeOembedAuthor($data)
    {
        unset($data['author_name'], $data['author_url']);
        return $data;
    }

    /**
     * Mensagem genérica para falhas no formulário de login.
     *
     * @param string $error Mensagem original.
     * @return string
     */
    public function genericLoginError($error)
    {
        global $action;

        if (isset($action) && 'login' !== $action) {
            return $error;
        }

        return __('Login failed.', 'hardenly');
    }

    /**
     * Remove o header X-Pingback quando o XML-RPC está desabilitado.
     *
     * @param array $headers Headers HTTP.
     * @return array
     */
    public function removePingbackHeader($headers)
    {
        unset($headers['X-Pingback']);
        return $headers;
    }

    /**
     * Bloqueia o registro se o nome de usuário for "admin".
     *
     * @param WP_Error $errors               Erros de registro.
     * @param string   $sanitized_user_login Nome de usuário sanitizado.
     * @param string   $user_email           Email do usuário.
     *
     * @return WP_Error
     */
    public function blockAdminUsername($errors, $sanitized_user_login, $user_email)
    {
        if (strtolower($sanitized_user_login) === 'admin') {
            $errors->add('username_error', __('The username "admin" is not allowed.', 'hardenly'));
        }
        return $errors;
    }

    /**
     * Impede salvar um perfil cujo nome de exibição seja igual ao login.
     *
     * O nome de exibição é público (autor dos posts, comentários, feeds); igual ao login,
     * ele entrega metade das credenciais.
     *
     * @param WP_Error $errors Erros do perfil.
     * @param bool     $update Se é uma atualização de usuário existente.
     * @param stdClass $user   Dados do usuário enviados no formulário.
     * @return void
     */
    public function blockSameLoginDisplay($errors, $update, $user)
    {
        if (!$update || empty($user->display_name)) {
            return;
        }

        $login = isset($user->user_login) ? $user->user_login : get_userdata($user->ID)->user_login;

        if (strtolower($login) === strtolower($user->display_name)) {
            $errors->add('hardenly_display_name', __('Your public display name cannot be the same as your username.', 'hardenly'));
        }
    }

    /**
     * Monta um item de status.
     *
     * @param string $state   ok, error ou off.
     * @param string $message Mensagem já traduzida.
     * @return array
     */
    private static function status($state, $message)
    {
        return ['state' => $state, 'message' => $message];
    }

    /**
     * Verifica o status dos módulos de segurança.
     *
     * @return array<string, array{state: string, message: string}>
     */
    public static function checkModulesStatus()
    {
        $options = hardenly_get_options();
        $basedir = self::uploadsDir();

        $checks = [
            'disable_registration' => [
                0 === (int) get_option('users_can_register'),
                __('Error: users_can_register is enabled.', 'hardenly'),
            ],
            'disable_file_editors' => [
                defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT === true,
                __('Error: DISALLOW_FILE_EDIT is not defined.', 'hardenly'),
            ],
            // Confere o bloco de regras do plugin, não só a existência do arquivo.
            'prevent_uploads_code_execution' => [
                self::uploadsHtaccessHasRules(),
                __('Error: the protection rules are missing from .htaccess in the uploads folder.', 'hardenly'),
            ],
            'disable_directory_browsing' => [
                file_exists($basedir . 'index.php'),
                __('Error: index.php does not exist in the uploads folder.', 'hardenly'),
            ],
            // Lê os filtros do core para confirmar que o XML-RPC está mesmo desligado.
            'disable_xmlrpc' => [
                apply_filters('xmlrpc_enabled', true) === false // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                    && apply_filters('xmlrpc_methods', ['pingback.ping' => 'this:pingback_ping']) === [], // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                __('Error: XML-RPC is enabled.', 'hardenly'),
            ],
            'hide_wp_version' => [
                has_action('wp_head', 'wp_generator') === false,
                __('Error: wp_generator is still active.', 'hardenly'),
            ],
        ];

        $status = [];
        foreach (array_keys(hardenly_default_options()) as $key) {
            if (empty($options[$key])) {
                $status[$key] = self::status('off', __('Module disabled.', 'hardenly'));
            } elseif (isset($checks[$key]) && !$checks[$key][0]) {
                $status[$key] = self::status('error', $checks[$key][1]);
            } else {
                // Módulos baseados em filtros ficam ativos sempre que a opção está ligada.
                $status[$key] = self::status('ok', __('Active.', 'hardenly'));
            }
        }

        return $status;
    }
}
