<?php
/**
 * Classe PMWSecurity
 *
 * Responsável por aplicar as medidas de segurança do plugin e fornecer verificação de status.
 *
 * @package ProtectMyWordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PMWSecurity
 */
class PMWSecurity
{
    /**
     * Marcador do bloco de regras do plugin no .htaccess de uploads.
     */
    const HTACCESS_MARKER = 'ProtectMyWordPress';

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
        $this->options = pmw_get_options();
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
                define('DISALLOW_FILE_EDIT', true);
            }

            // Remove o submenu de edição como reforço, já que a constante pode ter sido definida tarde demais.
            add_action('admin_menu', function () {
                remove_submenu_page('themes.php', 'theme-editor.php');
                remove_submenu_page('plugins.php', 'plugin-editor.php');
            }, 999);
        }

        // Regras de uploads: gravadas na ativação e ao salvar as opções; admin_init só repara
        // o arquivo se ele tiver sido alterado (nunca escreve em requests do front-end).
        add_action('admin_init', [$this, 'syncUploadsHtaccess']);

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

        // Desabilita a navegação de diretórios na pasta uploads.
        if (!empty($this->options['disable_directory_browsing'])) {
            add_action('admin_init', [$this, 'createUploadsIndex']);
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

        // Bloqueia registros onde o login e o nome de exibição sejam iguais.
        if (!empty($this->options['block_registration_same_login_display'])) {
            add_filter('registration_errors', [$this, 'blockSameLoginDisplay'], 10, 3);
        }
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
     * Caminho do .htaccess da pasta de uploads.
     *
     * @return string
     */
    private static function uploadsHtaccessPath()
    {
        $upload_dir = wp_upload_dir(null, false);
        return trailingslashit($upload_dir['basedir']) . '.htaccess';
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
        $file = self::uploadsHtaccessPath();

        if (!$enabled && !file_exists($file)) {
            return true;
        }

        // Remove as regras sem marcador gravadas pela versão 1.1 (Apache 2.2 apenas).
        $legacy = "<FilesMatch \"\\.(php|php\\.)$\">\ndeny from all\n</FilesMatch>\n";
        if (file_exists($file) && file_get_contents($file) === $legacy) {
            file_put_contents($file, '');
        }

        require_once ABSPATH . 'wp-admin/includes/misc.php';

        return insert_with_markers($file, self::HTACCESS_MARKER, $enabled ? self::uploadsHtaccessRules() : []);
    }

    /**
     * Verifica se o bloco de regras do plugin está presente no .htaccess de uploads.
     *
     * @return bool
     */
    public static function uploadsHtaccessHasRules()
    {
        $file = self::uploadsHtaccessPath();
        if (!file_exists($file)) {
            return false;
        }

        require_once ABSPATH . 'wp-admin/includes/misc.php';

        return extract_from_markers($file, self::HTACCESS_MARKER) === self::uploadsHtaccessRules();
    }

    /**
     * Mantém o .htaccess de uploads de acordo com a opção (repara se foi alterado ou removido).
     *
     * @return void
     */
    public function syncUploadsHtaccess()
    {
        $enabled = !empty($this->options['prevent_uploads_code_execution']);

        if ($enabled === self::uploadsHtaccessHasRules()) {
            return;
        }

        if (!self::writeUploadsHtaccess($enabled) && $enabled) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>';
                esc_html_e('ProtectMyWordPress: Não foi possível criar .htaccess na pasta uploads. Verifique as permissões.', 'protectmywordpress');
                echo '</p></div>';
            });
        }
    }

    /**
     * Cria um arquivo index.php na pasta de uploads para desabilitar a navegação de diretórios.
     *
     * @return void
     */
    public function createUploadsIndex()
    {
        $upload_dir = wp_upload_dir(null, false);
        $index_file = trailingslashit($upload_dir['basedir']) . 'index.php';

        if (!file_exists($index_file)) {
            $content = "<?php // Silence is golden.";
            if (false === file_put_contents($index_file, $content)) {
                if (is_admin()) {
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-error"><p>';
                        esc_html_e('ProtectMyWordPress: Não foi possível criar index.php na pasta uploads. Verifique as permissões.', 'protectmywordpress');
                        echo '</p></div>';
                    });
                }
            }
        }
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
            if (isset($endpoints['/wp/v2/users'])) {
                unset($endpoints['/wp/v2/users']);
            }
            if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
                unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
            }
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

        return __('Login falhou.', 'protectmywordpress');
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
     * @param WP_Error $errors             Erros de registro.
     * @param string   $sanitized_user_login Nome de usuário sanitizado.
     * @param string   $user_email          Email do usuário.
     *
     * @return WP_Error
     */
    public function blockAdminUsername($errors, $sanitized_user_login, $user_email)
    {
        if (strtolower($sanitized_user_login) === 'admin') {
            $errors->add('username_error', __('O nome de usuário "admin" não é permitido.', 'protectmywordpress'));
        }
        return $errors;
    }

    /**
     * Bloqueia o registro se o login e o nome de exibição forem iguais.
     *
     * @param WP_Error $errors             Erros de registro.
     * @param string   $sanitized_user_login Nome de usuário sanitizado.
     * @param string   $user_email          Email do usuário.
     *
     * @return WP_Error
     */
    public function blockSameLoginDisplay($errors, $sanitized_user_login, $user_email)
    {
        // O formulário de registro do core não tem nonce; leitura só para validação.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['display_name'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $display_name = sanitize_text_field(wp_unslash($_POST['display_name']));
            if (strtolower($sanitized_user_login) === strtolower($display_name)) {
                $errors->add('displayname_error', __('Seu nome de exibição não pode ser igual ao nome de usuário.', 'protectmywordpress'));
            }
        }
        return $errors;
    }

    /**
     * Verifica o status dos módulos de segurança.
     *
     * Método estático para evitar efeitos colaterais ao ser chamado do AJAX handler.
     *
     * @return array
     */
    public static function checkModulesStatus()
    {
        $options    = pmw_get_options();
        $status     = [];
        $upload_dir = wp_upload_dir(null, false);
        $basedir    = trailingslashit($upload_dir['basedir']);

        // Verifica se o registro de usuários está desabilitado.
        if (empty($options['disable_registration'])) {
            $status['disable_registration'] = __('Módulo desativado.', 'protectmywordpress');
        } else {
            $status['disable_registration'] = (get_option('users_can_register') == 0)
                ? 'OK'
                : __('Erro: users_can_register ativado.', 'protectmywordpress');
        }

        // Verifica se o editor de arquivos está desabilitado.
        if (empty($options['disable_file_editors'])) {
            $status['disable_file_editors'] = __('Módulo desativado.', 'protectmywordpress');
        } else {
            $status['disable_file_editors'] = (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT === true)
                ? 'OK'
                : __('Erro: DISALLOW_FILE_EDIT não definido.', 'protectmywordpress');
        }

        // Verifica a existência do .htaccess na pasta de uploads.
        if (empty($options['prevent_uploads_code_execution'])) {
            $status['prevent_uploads_code_execution'] = __('Módulo desativado.', 'protectmywordpress');
        } else {
            // Confere o bloco de regras do plugin, não só a existência do arquivo.
            $status['prevent_uploads_code_execution'] = self::uploadsHtaccessHasRules()
                ? 'OK'
                : __('Erro: .htaccess não encontrado em uploads.', 'protectmywordpress');
        }

        // Verifica se o index.php na pasta de uploads existe.
        if (empty($options['disable_directory_browsing'])) {
            $status['disable_directory_browsing'] = __('Módulo desativado.', 'protectmywordpress');
        } else {
            $status['disable_directory_browsing'] = file_exists($basedir . 'index.php')
                ? 'OK'
                : __('Erro: index.php não existe em uploads.', 'protectmywordpress');
        }

        // Verifica se o XML-RPC está desabilitado.
        if (empty($options['disable_xmlrpc'])) {
            $status['disable_xmlrpc'] = __('Módulo desativado.', 'protectmywordpress');
        } else {
            $status['disable_xmlrpc'] = (apply_filters('xmlrpc_enabled', true) === false
                && apply_filters('xmlrpc_methods', ['pingback.ping' => 'this:pingback_ping']) === [])
                ? 'OK'
                : __('Erro: XML-RPC ativado.', 'protectmywordpress');
        }

        // Verifica se a versão do WP está oculta (wp_generator removido do wp_head).
        if (empty($options['hide_wp_version'])) {
            $status['hide_wp_version'] = __('Módulo desativado.', 'protectmywordpress');
        } else {
            $status['hide_wp_version'] = (has_action('wp_head', 'wp_generator') === false)
                ? 'OK'
                : __('Erro: wp_generator ainda ativo.', 'protectmywordpress');
        }

        // Módulos baseados em filtros: confirmados como ativos se a opção está habilitada.
        $status['prevent_login_feedback'] = !empty($options['prevent_login_feedback'])
            ? 'OK'
            : __('Módulo desativado.', 'protectmywordpress');

        $status['disable_user_enumeration'] = !empty($options['disable_user_enumeration'])
            ? 'OK'
            : __('Módulo desativado.', 'protectmywordpress');

        $status['unset_x_powered_by'] = !empty($options['unset_x_powered_by'])
            ? 'OK'
            : __('Módulo desativado.', 'protectmywordpress');

        $status['block_admin_username'] = !empty($options['block_admin_username'])
            ? 'OK'
            : __('Módulo desativado.', 'protectmywordpress');

        $status['block_registration_same_login_display'] = !empty($options['block_registration_same_login_display'])
            ? 'OK'
            : __('Módulo desativado.', 'protectmywordpress');

        return $status;
    }
}
