/**
 * 2007-2026 PrestaShop
 * Custom Registration Fields Logic - Premium & Dynamic
 */

$(document).ready(function () {
    const $form = $('#customer-form');
    if (!$form.length) return;

    const getFieldRow = (name) => {
        const $el = $('input[name="' + name + '"], select[name="' + name + '"]');
        const $row = $el.closest('.form-control-row, .form-group');
        if ($row.length) {
            // Save original comment text to restore it later
            const $comment = $row.find('.form-control-comment');
            if ($comment.length && !$row.data('original-comment')) {
                $row.data('original-comment', $comment.text());
            }
            // Add data attribute for CSS
            $row.attr('data-field-name', name);
        }
        return $row;
    };

    const $fields = {
        ragione_sociale: getFieldRow('ragione_sociale'),
        codice_fiscale: getFieldRow('codice_fiscale'),
        piva: getFieldRow('piva'),
        pec: getFieldRow('pec'),
        codice_destinatario: getFieldRow('codice_destinatario'),
        phone: getFieldRow('phone'),
        phone_mobile: getFieldRow('phone_mobile'),
        address1: getFieldRow('address1'),
        city: getFieldRow('city'),
        postcode: getFieldRow('postcode'),
        id_state: getFieldRow('id_state')
    };

    function updateFields() {
        const isProf = $('input[name="is_professional"]:checked').val() == '1';
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

                // 1. Manage field visibility and required status
                const enabledFields = isProf ? settings.enabled : settings.enabled_private;
                const requiredFields = isProf ? settings.required : settings.required_private;

                let addressFieldsVisible = false;

                if (enabledFields && Array.isArray(enabledFields)) {
                    enabledFields.forEach(field => {
                        const $row = $fields[field];
                        if ($row && $row.length) {
                            $row.addClass('crf-visible').removeClass('crf-hidden');

                            const isReq = requiredFields && requiredFields.includes(field);
                            const $input = $row.find('input, select, textarea');
                            const $comment = $row.find('.form-control-comment');

                            if (isReq) {
                                $input.prop('required', true);
                                $comment.hide();
                            } else {
                                $input.prop('required', false);
                                if ($row.data('original-comment')) {
                                    $comment.text($row.data('original-comment')).show();
                                }
                            }

                            if (field === 'codice_fiscale') {
                                const newLabel = isProf ? crf_labels.cf_prof : crf_labels.cf_private;
                                $row.find('.form-control-label').text(newLabel);
                            }

                            if (['address1', 'city', 'postcode', 'id_state'].includes(field)) {
                                addressFieldsVisible = true;
                            }
                        }
                    });
                }

                // Handle Address Section Header via JS Injection
                $('.crf-section-header-js').remove();
                if (addressFieldsVisible) {
                    const $addr1Row = $fields['address1'];
                    if ($addr1Row && $addr1Row.length) {
                        $addr1Row.before('<div class="crf-section-header-js">Indirizzo di spedizione</div>');
                    }
                }

                // 2. Populate states (provinces)
                const $stateSelect = $('select[name="id_state"]');
                const $stateRow = $fields['id_state'];

                if ($stateSelect.length) {
                    $stateSelect.empty();

                    if (settings.states && settings.states.length > 0) {
                        $stateSelect.append('<option value="">Seleziona provincia</option>');
                        settings.states.forEach(state => {
                            $stateSelect.append('<option value="' + state.id + '">' + state.name + '</option>');
                        });

                        if ($stateRow && $stateRow.hasClass('crf-visible')) {
                            $stateRow.show().removeClass('crf-hidden');
                        }

                        // Initialize Select2 for searching
                        if ($.fn.select2) {
                            $stateSelect.select2({
                                placeholder: "Seleziona provincia",
                                allowClear: true,
                                width: '100%',
                                language: {
                                    noResults: function () {
                                        return "Nessuna provincia trovata";
                                    }
                                }
                            });
                        }
                    } else {
                        // Hide state if country has no states
                        if ($.fn.select2 && $stateSelect.data('select2')) {
                            $stateSelect.select2('destroy');
                        }
                        $stateRow.removeClass('crf-visible').addClass('crf-hidden').hide();
                    }
                }

                // 3. Handle Invoicing note
                $('.crf-invoicing-note').remove();
                if (isProf && settings.req_pec_sdi) {
                    const $pecRow = $fields['pec'];
                    const $sdiRow = $fields['codice_destinatario'];

                    if (($pecRow && $pecRow.hasClass('crf-visible')) || ($sdiRow && $sdiRow.hasClass('crf-visible'))) {
                        const noteText = 'Almeno uno tra PEC e Codice Destinatario є obbligatorio.';
                        $pecRow.before('<div class="crf-invoicing-note">' + noteText + '</div>');
                    }
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

    // --- Google Places Autocomplete Logic ---
    let autocomplete;
    function initAutocomplete() {
        if (typeof google === 'undefined' || !crf_google_maps_key) return;

        const cityInput = document.querySelector('input[name="city"]');
        if (!cityInput) return;

        autocomplete = new google.maps.places.Autocomplete(cityInput, {
            types: ['(cities)'],
        });

        autocomplete.addListener('place_changed', fillInAddress);
        updateAutocompleteCountry();
    }

    function updateAutocompleteCountry() {
        if (!autocomplete) return;
        const idCountry = $('select[name="id_country"]').val() || $('input[name="id_country"]').val();
        let isoCode = 'it';
        const countryText = $('select[name="id_country"] option:selected').text().toLowerCase();
        const isoMapping = { 'italy': 'it', 'italia': 'it', 'france': 'fr', 'francia': 'fr', 'spain': 'es', 'spagna': 'es', 'germany': 'de', 'germania': 'de' };
        for (let key in isoMapping) {
            if (countryText.includes(key)) {
                isoCode = isoMapping[key];
                break;
            }
        }
        autocomplete.setComponentRestrictions({ country: isoCode });
    }

    function fillInAddress() {
        const place = autocomplete.getPlace();
        if (!place.address_components) return;

        let city = '';
        let postcode = '';
        let stateName = '';

        for (const component of place.address_components) {
            const componentType = component.types[0];
            switch (componentType) {
                case "locality": city = component.long_name; break;
                case "postal_code": postcode = component.long_name; break;
                case "administrative_area_level_2": stateName = component.long_name; break;
            }
        }

        if (city) $('input[name="city"]').val(city);
        if (postcode) $('input[name="postcode"]').val(postcode).trigger('change');

        if (stateName) {
            const $stateSelect = $('select[name="id_state"]');
            $stateSelect.find('option').each(function () {
                if ($(this).text().toLowerCase().includes(stateName.toLowerCase()) || stateName.toLowerCase().includes($(this).text().toLowerCase())) {
                    $stateSelect.val($(this).val()).trigger('change');
                    return false;
                }
            });
        }
    }

    $(document).on('change', 'select[name="id_country"]', function () {
        updateFields();
        updateAutocompleteCountry();
    });

    $(document).on('change', 'input[name="is_professional"]', updateFields);

    updateFields();

    if (crf_google_maps_key) {
        setTimeout(initAutocomplete, 1000);
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('complete_profile')) {
        const msgText = 'Per favore, completa il tuo profilo con i dati mancanti per procedere.';
        const $msg = $('<div class="alert alert-info">' + msgText + '</div>');
        if (!$form.find('.alert-info').length) {
            $form.prepend($msg);
        }
    }
});
