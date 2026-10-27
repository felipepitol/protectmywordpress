jQuery(document).ready(function ($) {
    /**
     * Exibe uma notificação tipo toast empilhada no canto inferior direito (XSS-safe).
     *
     * @param {string} message A mensagem a ser exibida.
     * @param {string} type    'ok' | 'error' | 'off'
     */
    function showToast(message, type) {
        var $container = $('#hardenly-toast-container');
        if (!$container.length) {
            $container = $('<div id="hardenly-toast-container"></div>');
            $('body').append($container);
        }
        var $toast = $('<div class="hardenly-toast"></div>').addClass('hardenly-' + type).text(message);
        $container.append($toast);
        setTimeout(function () {
            $toast.fadeOut(500, function () { $(this).remove(); });
        }, 4000);
    }

    // Ao clicar no botão de checar status, preenche uma área com os resultados.
    $('#hardenly-check-status').on('click', function (e) {
        e.preventDefault();
        var $result = $('#hardenly-status-result');
        $.ajax({
            url: hardenlyAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hardenly_check_status',
                nonce: hardenlyAdmin.nonce
            },
            success: function (response) {
                $result.empty();
                if (!response || !response.success) {
                    $result.append($('<p></p>').text(hardenlyAdmin.i18n.checkFailed));
                    return;
                }
                $.each(response.data, function (key, item) {
                    var label = hardenlyAdmin.labels[key] || key;
                    var text = label + ': ' + item.message;
                    $result.append($('<p></p>').addClass('hardenly-' + item.state).text(text));
                    showToast(text, item.state);
                });
            },
            error: function () {
                $result.empty().append($('<p></p>').text(hardenlyAdmin.i18n.requestError));
            }
        });
    });
});
