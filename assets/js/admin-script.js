jQuery(document).ready(function ($) {
    /**
     * Exibe uma notificação tipo toast (XSS-safe).
     *
     * @param {string} message A mensagem a ser exibida.
     */
    function showToast(message) {
        var $toast = $('<div class="pmw-toast"></div>').text(message);
        $('body').append($toast);
        setTimeout(function () {
            $toast.fadeOut(500, function () {
                $(this).remove();
            });
        }, 3000);
    }

    // Quando um switch é alternado, dispara uma verificação via AJAX.
    $('.pmw-switch input[type="checkbox"]').change(function () {
        var checkbox = $(this);
        var optionName = checkbox.attr('name').match(/\[(.*?)\]/)[1];

        $.ajax({
            url: pmw_ajax_obj.ajax_url,
            method: 'POST',
            data: {
                action: 'pmw_check_status',
                nonce: pmw_ajax_obj.nonce
            },
            success: function (response) {
                if (response.success) {
                    var status = response.data[optionName];
                    if (status === 'OK') {
                        showToast(optionName + ' ativado com sucesso.');
                    } else {
                        showToast(optionName + ' não ativado: ' + status);
                    }
                } else {
                    showToast('Erro ao verificar o status.');
                }
            },
            error: function () {
                showToast('Erro na comunicação com o servidor.');
            }
        });
    });

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
                        var $p = $('<p></p>').text(key + ': ' + status);
                        $result.append($p);
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
