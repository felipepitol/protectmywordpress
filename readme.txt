=== ProtectMyWordPress ===
Contributors: felipepitol
Tags: segurança, proteção, admin, xml-rpc, uploads
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ProtectMyWordPress é um plugin de segurança para WordPress que oferece diversas medidas de proteção, como a desabilitação de registro de usuários, ocultação da versão do WP, prevenção de execução de código na pasta de uploads, e muito mais. Qualquer sugestão de otimização pode ser feito usando o repositório do Github: https://github.com/felipepitol/protectmywordpress.

== Descrição ==
O plugin apresenta uma interface administrativa moderna e amigável. Cada funcionalidade é apresentada em um bloco com uma breve descrição e um switch para ativação ou desativação. Há também uma opção para verificar o status dos módulos de segurança, com feedback visual.

== Instalação ==
1. Faça o upload dos arquivos do plugin para a pasta `/wp-content/plugins/protectmywordpress`.
2. Ative o plugin através do menu "Plugins" no WordPress.
3. Configure as opções em **Configurações > ProtectMyWordPress**.

== Changelog ==
= 1.2 =
* Proteção de uploads: as regras agora são gravadas num bloco próprio do .htaccess (insert_with_markers), mesmo quando o arquivo já existe. Antes, um .htaccess de outro plugin ou do servidor impedia a gravação e o PHP continuava executando em uploads, com o status mostrando "OK".
* Regras de uploads compatíveis com Apache 2.2 e 2.4, cobrindo também .phtml, .php5, .phar e extensões duplas (arquivo.php.jpg).
* Status da proteção de uploads agora confere as regras, não só a existência do arquivo.
* Regras removidas ao desativar ou desinstalar o plugin; regras antigas da 1.1 são migradas.
* XML-RPC: pingback.ping e demais métodos agora ficam realmente desabilitados (xmlrpc_enabled só bloqueava métodos autenticados) e o header X-Pingback é removido.
* Enumeração de usuários: sitemap de autores e autor no oEmbed removidos (ambos expunham o login).
* Enumeração de usuários: o filtro por autor na lista de posts do painel (?author=N) não redireciona mais para a home.
* Enumeração de usuários: ?author=N bloqueado também via POST e listas (1,2); endpoints REST de usuários liberados só para quem edita posts, então autores e editores voltam a ver a lista de autores no editor de blocos.
* Mensagem genérica de login aplicada só ao formulário de login; erros de registro (ex.: usuário "admin" bloqueado) voltam a aparecer.
* Opções novas usam o valor padrão em sites que atualizaram (pmw_get_options mescla os padrões).
* Arquivos em uploads não são mais verificados em toda request do front-end.
* Cabeçalho de licença no formato aceito pelo WordPress.org.

= 1.1 =
* Corrigido formulário de configurações (form tag ausente impedia salvar).
* Corrigida falha de segurança no AJAX (nonce não era enviado).
* Corrigida vulnerabilidade XSS nas notificações JavaScript.
* AJAX handler não re-instancia mais a classe de segurança (sem efeitos colaterais).
* Opção de desabilitar registro agora usa filtro em vez de escrita no banco a cada request.
* Reforço na desabilitação do editor de arquivos (remoção de submenus).
* Adicionada sanitização de opções ao salvar (sanitize_callback).
* Assets CSS/JS carregados apenas na página do plugin.
* Sanitização de $_POST['display_name'] e nonce no handler AJAX.
* Opções padrão centralizadas em função compartilhada (pmw_default_options).
* Prevenção de enumeração de usuários agora inclui REST API.
* Adicionados hooks de ativação, desativação e desinstalação.
* Adicionado load_plugin_textdomain para suporte a traduções.
* Substituído @file_put_contents por tratamento de erros com admin notices.
* Classes CSS prefixadas com pmw- para evitar conflitos.
* Header X-Powered-By removido via send_headers (timing correto).
* Atualizado readme.txt com versões corretas.

= 1.0 =
* Versão inicial com medidas de segurança e interface moderna.
* Inclusão de verificação de status dos módulos via AJAX.
* Interface UX/UI aprimorada e feedback com toast notifications.

== FAQ ==
= O plugin coleta dados dos usuários? =
Não. O plugin não rastreia nem coleta nenhum dado do usuário sem o seu consentimento.

= O plugin funciona com Nginx? =
A maioria das funcionalidades funciona em qualquer servidor. A prevenção de execução de PHP na pasta uploads utiliza .htaccess, que é específico do Apache. Para Nginx, configure regras equivalentes manualmente.

== Notas de Privacidade ==
Este plugin não envia dados para servidores externos. Todo o processamento é realizado localmente, garantindo a privacidade dos dados.

== Licença ==
Este plugin é distribuído sob a licença GPLv2 ou superior.
