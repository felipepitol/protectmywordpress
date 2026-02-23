jQuery(document).ready(function ($) {
    /**
     * Exibe uma notificação tipo toast empilhada no canto inferior direito (XSS-safe).
     *
     * @param {string} message A mensagem a ser exibida.
     * @param {string} type    'ok' | 'error' | 'disabled'
     */
    function showToast(message, type) {
        var $container = $('#pmw-toast-container');
        if (!$container.length) {
            $container = $('<div id="pmw-toast-container"></div>');
            $('body').append($container);
        }
        var $toast = $('<div class="pmw-toast pmw-' + type + '"></div>').text(message);
        $container.append($toast);
        setTimeout(function () {
            $toast.fadeOut(500, function () { $(this).remove(); });
        }, 4000);
    }

    // Ao clicar no botão de checar status, preenche uma área com os resultados.
    $('#pmw-check-status').click(function (e) {
        e.preventDefault();
        $.ajax({
            url: pmw_ajax_obj.ajax_url,
            method: 'POST',
            data: {
                action: 'pmw_check_status',
                nonce: pmw_ajax_obj.nonce
            },
            success: function (response) {
                var $result = $('#pmw-status-result');
                $result.empty();
                if (response.success) {
                    $.each(response.data, function (key, status) {
                        var type;
                        if (status === 'OK') {
                            type = 'ok';
                        } else if (/^Err/i.test(status)) {
                            type = 'error';
                        } else {
                            type = 'disabled';
                        }
                        var $p = $('<p></p>').text(key + ': ' + status);
                        $result.append($p);
                        showToast(key + ': ' + status, type);
                    });
                } else {
                    $result.append($('<p></p>').text('Erro ao verificar status.'));
                }
            },
            error: function () {
                $('#pmw-status-result').empty().append(
                    $('<p></p>').text('Erro na comunicação com o servidor.')
                );
            }
        });
    });
});
