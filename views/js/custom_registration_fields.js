/**
 * 2007-2026 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 */

$(document).ready(function () {
    const $form = $('#customer-form');
    if (!$form.length) return;

    const $isProfessional = $('input[name="is_professional"]');
    const $fields = {
        ragione_sociale: $('input[name="ragione_sociale"]').closest('.form-group'),
        codice_fiscale: $('input[name="codice_fiscale"]').closest('.form-group'),
        piva: $('input[name="piva"]').closest('.form-group'),
        pec: $('input[name="pec"]').closest('.form-group'),
        codice_destinatario: $('input[name="codice_destinatario"]').closest('.form-group')
    };

    function updateFields() {
        const isProf = parseInt($('input[name="is_professional"]:checked').val());
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

                settings.enabled_fields.forEach(field => {
                    if ($fields[field]) {
                        // Logic for Professional vs Private
                        if (isProf) {
                            $fields[field].show();
                        } else {
                            // In Private mode, usually only Codice Fiscale is shown if enabled
                            if (field === 'codice_fiscale') {
                                $fields[field].show();
                            }
                        }
                    }
                });

                // Mark required fields visually if needed, though PrestaShop handles this via FormField
            }
        });
    }

    function hideAllFields() {
        Object.values($fields).forEach($f => $f.hide());
    }

    $isProfessional.on('change', updateFields);
    $('select[name="id_country"]').on('change', updateFields);

    // Initial run
    updateFields();

    // Google Login completion reminder
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('complete_profile')) {
        const $msg = $('<div class="alert alert-info">' +
            'Per favore, completa il tuo profilo con i dati mancanti (Codice Fiscale, P.IVA, ecc.) per procedere.' +
            '</div>');
        $form.prepend($msg);
    }
});
