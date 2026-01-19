/**
 * 2007-2026 PrestaShop
 * Custom Registration Fields Logic - Premium & Dynamic
 */

$(document).ready(function () {
    const $form = $('#customer-form');
    if (!$form.length) return;

    const getFieldRow = (name) => {
        const $el = $('input[name="' + name + '"], select[name="' + name + '"]');
        return $el.closest('.form-control-row, .form-group');
    };

    const $fields = {
        ragione_sociale: getFieldRow('ragione_sociale'),
        codice_fiscale: getFieldRow('codice_fiscale'),
        piva: getFieldRow('piva'),
        pec: getFieldRow('pec'),
        codice_destinatario: getFieldRow('codice_destinatario')
    };

    function updateFields() {
        const isProf = parseInt($('input[name="is_professional"]:checked').val()) === 1;
        const idCountry = $('select[name="id_country"]').val() || $('input[name="id_country"]').val();

        if (!idCountry) {
            hideAllFields();
            return;
        }

        $.ajax({
            url: crf_ajax_url,
            data: { id_country: idCountry },
            dataType: 'json',
            success: function (settings) {
                hideAllFields();

                const enabledFields = isProf ? settings.enabled : settings.enabled_private;

                if (enabledFields) {
                    enabledFields.forEach(field => {
                        const $row = $fields[field];
                        if ($row && $row.length) {
                            $row.addClass('crf-visible').removeClass('crf-hidden');
                        }
                    });
                }
            },
            error: function () {
                if (isProf) {
                    Object.values($fields).forEach($f => $f.addClass('crf-visible').removeClass('crf-hidden'));
                }
            }
        });
    }

    function hideAllFields() {
        Object.values($fields).forEach($f => {
            if ($f && $f.length) {
                $f.addClass('crf-hidden').removeClass('crf-visible');
            }
        });
    }

    $(document).on('change', 'input[name="is_professional"]', updateFields);
    $(document).on('change', 'select[name="id_country"]', updateFields);

    updateFields();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('complete_profile')) {
        const msgText = 'Per favore, completa il tuo profilo con i dati mancanti per procedere.';
        const $msg = $('<div class="alert alert-info">' + msgText + '</div>');
        $form.prepend($msg);
    }
});
