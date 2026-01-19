/**
 * 2007-2026 PrestaShop
 * Custom Registration Fields Logic - Premium & Dynamic
 */

$(document).ready(function () {
    const $form = $('#customer-form');
    if (!$form.length) return;

    // Apply layout class
    if (typeof crf_two_col !== 'undefined' && crf_two_col) {
        $form.addClass('crf-two-columns');
    }

    const getFieldRow = (name) => {
        const $el = $('input[name="' + name + '"], select[name="' + name + '"]');
        const $row = $el.closest('.form-control-row, .form-group');
        if ($row.length) {
            // Save original comment text to restore it later
            const $comment = $row.find('.form-control-comment');
            if ($comment.length && !$row.data('original-comment')) {
                $row.data('original-comment', $comment.text());
            }
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
        postcode: getFieldRow('postcode')
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
                        }
                    });
                }

                // 2. Populate states (provinces)
                const $stateSelect = $('select[name="id_state"]');
                const $stateRow = $fields['id_state'];

                if ($stateSelect.length) {
                    $stateSelect.empty();

                    if (settings.states && settings.states.length > 0) {
                        $stateSelect.append('<option value="">' + (isProf ? 'Seleziona provincia' : 'Seleziona provincia') + '</option>');
                        settings.states.forEach(state => {
                            $stateSelect.append('<option value="' + state.id + '">' + state.name + '</option>');
                        });

                        if ($stateRow.hasClass('crf-visible')) {
                            $stateRow.show();
                        }

                        // Initialize Select2 for searching
                        if ($.fn.select2) {
                            $stateSelect.select2({
                                placeholder: "Seleziona provincia",
                                allowClear: true,
                                width: '100%'
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

        // Create the autocomplete object, restricting the search to cities and addresses
        autocomplete = new google.maps.places.Autocomplete(cityInput, {
            types: ['(cities)'],
        });

        // When the user selects an address from the dropdown, populate the address fields in the form.
        autocomplete.addListener('place_changed', fillInAddress);

        updateAutocompleteCountry();
    }

    function updateAutocompleteCountry() {
        if (!autocomplete) return;
        const idCountry = $('select[name="id_country"]').val() || $('input[name="id_country"]').val();

        // Get ISO code 
        let isoCode = 'it'; // default
        const countryText = $('select[name="id_country"] option:selected').text().toLowerCase();

        // Simple mapping for common countries, can be expanded or fetched from server if needed
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
                case "locality":
                    city = component.long_name;
                    break;
                case "postal_code":
                    postcode = component.long_name;
                    break;
                case "administrative_area_level_2": // Province for Italy
                    stateName = component.long_name;
                    break;
            }
        }

        if (city) $('input[name="city"]').val(city);
        if (postcode) $('input[name="postcode"]').val(postcode).trigger('change');

        // Try to match the province (state) from the dropdown
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

    // Initial run
    updateFields();

    // Wait for Google Maps to load if key exists
    if (crf_google_maps_key) {
        setTimeout(initAutocomplete, 1000);
    }

    // Profile completion message
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('complete_profile')) {
        const msgText = 'Per favore, completa il tuo profilo con i dati mancanti per procedere.';
        const $msg = $('<div class="alert alert-info">' + msgText + '</div>');
        if (!$form.find('.alert-info').length) {
            $form.prepend($msg);
        }
    }
});

