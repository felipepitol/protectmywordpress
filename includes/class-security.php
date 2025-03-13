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
        // Carrega as opções definidas ou utiliza os valores padrão.
        $this->options = get_option('pmw_options', $this->defaultOptions());
        // Aplica as medidas de segurança conforme as opções.
        $this->applySecurityMeasures();
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
     * Aplica as medidas de segurança de acordo com as opções definidas.
     *
     * @return void
     */
    public function applySecurityMeasures()
    {
        // Desabilita o registro público de usuários.
        if (!empty($this->options['disable_registration'])) {
            update_option('users_can_register', 0);
        }

        // Desabilita os editores de arquivos internos.
        if (!empty($this->options['disable_file_editors'])) {
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', true);
            }
        }

        // Previne a execução de código na pasta de uploads.
        if (!empty($this->options['prevent_uploads_code_execution'])) {
            add_action('init', [$this, 'createUploadsHtaccess']);
        }

        // Oculta a versão do WordPress.
        if (!empty($this->options['hide_wp_version'])) {
            remove_action('wp_head', 'wp_generator');
        }

        // Previne feedback detalhado no login.
        if (!empty($this->options['prevent_login_feedback'])) {
            add_filter('login_errors', function () {
                return __('Login falhou.', 'protectmywordpress');
            });
        }

        // Desabilita a navegação de diretórios na pasta uploads.
        if (!empty($this->options['disable_directory_browsing'])) {
            add_action('init', [$this, 'createUploadsIndex']);
        }

        // Previne a enumeração de usuários.
        if (!empty($this->options['disable_user_enumeration'])) {
            add_action('init', [$this, 'disableUserEnumeration']);
        }

        // Remove o header X-Powered-By.
        if (!empty($this->options['unset_x_powered_by'])) {
            add_action('init', function () {
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
            @file_put_contents($htaccess_file, $rules);
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
            @file_put_contents($index_file, $content);
        }
    }

    /**
     * Previne a enumeração de usuários sanitizando a query 'author'.
     *
     * @return void
     */
    public function disableUserEnumeration()
    {
        if (isset($_REQUEST['author'])) {
            if (is_numeric($_REQUEST['author'])) {
                $_REQUEST['author'] = '';
            }
        }
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
        if (isset($_POST['display_name']) && strtolower($sanitized_user_login) === strtolower($_POST['display_name'])) {
            $errors->add('displayname_error', __('Seu nome de exibição não pode ser igual ao nome de usuário.', 'protectmywordpress'));
        }
        return $errors;
    }

    /**
     * Verifica o status dos módulos de segurança.
     *
     * Retorna um array associativo onde cada chave representa um módulo e seu valor indica
     * "OK" ou uma mensagem de erro.
     *
     * @return array
     */
    public function checkModulesStatus()
    {
        $status = [];

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
