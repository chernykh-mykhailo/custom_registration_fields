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
        codice_destinatario: getFieldRow('codice_destinatario'),
        phone: getFieldRow('phone'),
        address1: getFieldRow('address1'),
        city: getFieldRow('city'),
        postcode: getFieldRow('postcode')
    };

    function updateFields() {
        const isProf = parseInt($('input[name="is_professional"]:checked').val()) === 1;
        const idCountry = $('select[name="id_country"]').val() || $('input[name="id_country"]').val() || crf_default_country;

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
                const requiredFields = isProf ? settings.required : settings.required_private;

                // If we have settings in DB, follow them
                if (enabledFields && Array.isArray(enabledFields) && enabledFields.length > 0) {
                    enabledFields.forEach(field => {
                        const $row = $fields[field];
                        if ($row && $row.length) {
                            $row.addClass('crf-visible').removeClass('crf-hidden');

                            // Dynamically manage "required" status
                            const isReq = requiredFields && requiredFields.includes(field);
                            const $input = $row.find('input, select, textarea'); // Target all relevant input types
                            if (isReq) {
                                $input.prop('required', true);
                                $row.find('.form-control-comment').text('').hide(); // Hide "Optional" label if PrestaShop added it
                            } else {
                                $input.prop('required', false);
                            }
                        }
                    });
                } else if (isProf) {
                    // Fallback: If no settings saved yet and it's a Professional, show all fields
                    Object.values($fields).forEach($f => $f.addClass('crf-visible').removeClass('crf-hidden'));
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
