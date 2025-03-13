jQuery(document).ready(function ($) {
    /**
     * Exibe uma notificação tipo toast.
     *
     * @param {string} message A mensagem a ser exibida.
     */
    function showToast(message) {
        var $toast = $('<div class="toast">' + message + '</div>');
        $('body').append($toast);
        setTimeout(function () {
            $toast.fadeOut(500, function () {
                $(this).remove();
            });
        }, 3000);
    }

    // Quando um switch é alternado, dispara uma verificação via AJAX.
    $('.switch input[type="checkbox"]').change(function () {
        var checkbox = $(this);
        // Extrai o nome da opção a partir do atributo name (ex: pmw_options[disable_registration])
        var optionName = checkbox.attr('name').match(/\[(.*?)\]/)[1];

        $.ajax({
            url: pmw_ajax_obj.ajax_url,
            method: 'POST',
            data: {
                action: 'pmw_check_status'
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
                action: 'pmw_check_status'
            },
            success: function (response) {
                var $result = $('#pmw-status-result');
                $result.empty();
                if (response.success) {
                    $.each(response.data, function (key, status) {
                        $result.append('<p>' + key + ': ' + status + '</p>');
                    });
                } else {
                    $result.append('<p>Erro ao verificar status.</p>');
                }
            },
            error: function () {
                $('#pmw-status-result').html('<p>Erro na comunicação com o servidor.</p>');
            }
        });
    });
});
