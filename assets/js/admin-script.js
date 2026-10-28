jQuery(function ($) {
    var $form = $('#hardenly-form');
    var $status = $('#hardenly-status-result');
    var $count = $('.hardenly-meter__count');
    var dirty = false;

    /**
     * Substitui %1$s, %2$s... (ou %s) pelos valores informados.
     *
     * @param {string} text Texto traduzido.
     * @return {string}
     */
    function format(text) {
        var args = Array.prototype.slice.call(arguments, 1);
        var i = 0;
        return text.replace(/%(\d+\$)?s/g, function (match, pos) {
            return String(args[pos ? parseInt(pos, 10) - 1 : i++]);
        });
    }

    function setStatus(text, state) {
        $status.removeClass('is-dirty is-error').addClass(state ? 'is-' + state : '').text(text);
    }

    // Atualiza o medidor do cabeçalho a partir dos switches (antes mesmo de salvar).
    function updateMeter() {
        var on = 0;
        $form.find('.hardenly-row').each(function () {
            var key = $(this).data('key');
            var checked = $(this).find('input[type="checkbox"]').prop('checked');
            on += checked ? 1 : 0;
            $('.hardenly-meter__bar li[data-key="' + key + '"]').toggleClass('is-on', checked);
        });
        $count.text(on);
    }

    $form.on('change', 'input[type="checkbox"]', function () {
        dirty = true;
        updateMeter();
        // O resultado da última verificação não vale mais para esta linha.
        $(this).closest('.hardenly-row').find('.hardenly-badge').addClass('is-stale');
        setStatus(hardenlyAdmin.i18n.unsaved, 'dirty');
    });

    $form.on('submit', function () {
        dirty = false;
    });

    $(window).on('beforeunload', function () {
        if (dirty) {
            return hardenlyAdmin.i18n.unsaved;
        }
    });

    // Mostra o resultado da verificação em cada linha.
    function renderStatus(data) {
        var ok = 0;
        var problems = 0;

        $('.hardenly-meter__bar li').removeClass('is-error');

        $.each(data, function (key, item) {
            var $row = $form.find('.hardenly-row[data-key="' + key + '"]');
            var isError = item.state === 'error';

            $row.toggleClass('is-error', isError);
            $row.find('.hardenly-badge')
                .removeClass('is-ok is-off is-error is-stale')
                .addClass('is-' + item.state)
                .text(hardenlyAdmin.i18n[item.state] || item.state)
                .prop('hidden', false);
            $row.find('.hardenly-row__problem').text(isError ? item.message : '').prop('hidden', !isError);
            $('.hardenly-meter__bar li[data-key="' + key + '"]').toggleClass('is-error', isError);

            ok += item.state === 'ok' ? 1 : 0;
            problems += isError ? 1 : 0;
        });

        if (problems) {
            setStatus(format(hardenlyAdmin.i18n.summary, ok, problems), 'error');
        } else {
            setStatus(format(hardenlyAdmin.i18n.summaryOk, ok));
        }
    }

    function checkStatus() {
        var $button = $('#hardenly-check-status').prop('disabled', true);
        setStatus(hardenlyAdmin.i18n.checking);

        $.ajax({
            url: hardenlyAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hardenly_check_status',
                nonce: hardenlyAdmin.nonce
            }
        }).done(function (response) {
            if (!response || !response.success) {
                setStatus(hardenlyAdmin.i18n.checkFailed, 'error');
                return;
            }
            renderStatus(response.data);
        }).fail(function () {
            setStatus(hardenlyAdmin.i18n.requestError, 'error');
        }).always(function () {
            $button.prop('disabled', false);
        });
    }

    $('#hardenly-check-status').on('click', function (e) {
        e.preventDefault();
        checkStatus();
    });

    // Verifica ao abrir a página, para o status aparecer sem clique extra.
    checkStatus();
});
