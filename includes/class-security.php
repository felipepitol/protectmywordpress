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

        // Previne a execução de código na pasta de uploads (criação movida para activation hook).
        // Mantém verificação no init apenas como fallback caso o arquivo tenha sido removido.
        if (!empty($this->options['prevent_uploads_code_execution'])) {
            add_action('init', [$this, 'createUploadsHtaccess']);
        }

        // Oculta a versão do WordPress.
        if (!empty($this->options['hide_wp_version'])) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
        }

        // Previne feedback detalhado no login.
        if (!empty($this->options['prevent_login_feedback'])) {
            add_filter('login_errors', function () {
                return __('Login falhou.', 'protectmywordpress');
            });
        }

        // Desabilita a navegação de diretórios na pasta uploads (criação movida para activation hook).
        if (!empty($this->options['disable_directory_browsing'])) {
            add_action('init', [$this, 'createUploadsIndex']);
        }

        // Previne a enumeração de usuários (query param + REST API).
        if (!empty($this->options['disable_user_enumeration'])) {
            add_action('init', [$this, 'disableUserEnumeration']);
            add_filter('rest_endpoints', [$this, 'disableUserRestEndpoint']);
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

        // Desabilita o XML-RPC.
        if (!empty($this->options['disable_xmlrpc'])) {
            add_filter('xmlrpc_enabled', '__return_false');
        }

        // Bloqueia registros onde o login e o nome de exibição sejam iguais.
        if (!empty($this->options['block_registration_same_login_display'])) {
            add_filter('registration_errors', [$this, 'blockSameLoginDisplay'], 10, 3);
        }
    }

    /**
     * Cria um arquivo .htaccess na pasta de uploads para prevenir a execução de arquivos PHP.
     *
     * @return void
     */
    public function createUploadsHtaccess()
    {
        $upload_dir    = wp_upload_dir();
        $htaccess_file = trailingslashit($upload_dir['basedir']) . '.htaccess';

        if (!file_exists($htaccess_file)) {
            $rules  = "<FilesMatch \"\.(php|php\.)$\">\n";
            $rules .= "deny from all\n";
            $rules .= "</FilesMatch>\n";

            if (false === file_put_contents($htaccess_file, $rules)) {
                if (is_admin()) {
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-error"><p>';
                        esc_html_e('ProtectMyWordPress: Não foi possível criar .htaccess na pasta uploads. Verifique as permissões.', 'protectmywordpress');
                        echo '</p></div>';
                    });
                }
            }
        }
    }

    /**
     * Cria um arquivo index.php na pasta de uploads para desabilitar a navegação de diretórios.
     *
     * @return void
     */
    public function createUploadsIndex()
    {
        $upload_dir = wp_upload_dir();
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
     * Previne a enumeração de usuários sanitizando a query 'author'.
     *
     * @return void
     */
    public function disableUserEnumeration()
    {
        if (isset($_REQUEST['author']) && is_numeric($_REQUEST['author'])) {
            wp_safe_redirect(home_url(), 301);
            exit;
        }
    }

    /**
     * Remove o endpoint REST API de listagem de usuários para não-administradores.
     *
     * @param array $endpoints Array de endpoints REST.
     * @return array
     */
    public function disableUserRestEndpoint($endpoints)
    {
        if (!current_user_can('list_users')) {
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
        if (isset($_POST['display_name'])) {
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
        $options = pmw_get_options();
        $status  = [];

        // Verifica se o registro de usuários está desabilitado.
        $status['disable_registration'] = (get_option('users_can_register') == 0)
            ? 'OK'
            : __('Erro: users_can_register ativado.', 'protectmywordpress');

        // Verifica se o editor de arquivos está desabilitado.
        $status['disable_file_editors'] = (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT === true)
            ? 'OK'
            : __('Erro: DISALLOW_FILE_EDIT não definido.', 'protectmywordpress');

        // Verifica a existência do .htaccess na pasta de uploads.
        $upload_dir    = wp_upload_dir();
        $htaccess_file = trailingslashit($upload_dir['basedir']) . '.htaccess';
        $status['prevent_uploads_code_execution'] = (file_exists($htaccess_file))
            ? 'OK'
            : __('Erro: .htaccess não encontrado em uploads.', 'protectmywordpress');

        // Verifica se o index.php na pasta de uploads existe.
        $index_file = trailingslashit($upload_dir['basedir']) . 'index.php';
        $status['disable_directory_browsing'] = (file_exists($index_file))
            ? 'OK'
            : __('Erro: index.php não existe em uploads.', 'protectmywordpress');

        // Verifica se o XML-RPC está desabilitado.
        $xmlrpc_status = apply_filters('xmlrpc_enabled', true);
        $status['disable_xmlrpc'] = ($xmlrpc_status === false)
            ? 'OK'
            : __('Erro: XML-RPC ativado.', 'protectmywordpress');

        return $status;
    }
}
