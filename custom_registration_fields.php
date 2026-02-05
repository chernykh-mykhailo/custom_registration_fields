<?php
/**
 * 2007-2026 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2026 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Custom_registration_fields extends Module
{
    public function __construct()
    {
        $this->name = 'custom_registration_fields';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Mykhailo Chernykh';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Custom Registration Fields');
        $this->description = $this->l('Adds professional registration fields with per-country settings.');

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install() &&
            $this->installSql() &&
            $this->registerHook('additionalCustomerFormFields') &&
            $this->registerHook('validateCustomerFormFields') &&
            $this->registerHook('actionCustomerAccountAdd') &&
            $this->registerHook('actionCustomerAccountUpdate') &&
            $this->registerHook('header') &&
            $this->registerHook('displayAdminCustomers') &&
            $this->registerHook('displayCustomerLoginFormAfter') &&
            $this->registerHook('displayCustomerAccountForm') &&
            $this->registerHook('actionCustomerFormBuilderModifier') &&
            $this->registerHook('actionAfterCreateCustomerFormHandler') &&
            $this->registerHook('actionAfterUpdateCustomerFormHandler') &&
            $this->alterCustomerTable() &&
            Configuration::updateValue('CRF_DB_V1', 1);
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->uninstallSql();
    }

    protected function installSql()
    {
        $sql = [
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'custom_registration_fields_country` (
                `id_country` INT(11) UNSIGNED NOT NULL,
                `enabled_fields` TEXT,
                `required_fields` TEXT,
                `enabled_fields_private` TEXT,
                `required_fields_private` TEXT,
                PRIMARY KEY (`id_country`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;',
        ];

        foreach ($sql as $query) {
            if (Db::getInstance()->execute($query) == false) {
                return false;
            }
        }

        return true;
    }

    protected function uninstallSql()
    {
        // We might want to keep data, but for now, standard uninstall
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'custom_registration_fields_country`');
    }

    protected function alterCustomerTable()
    {
        $columns = [
            'codice_fiscale' => 'VARCHAR(255) DEFAULT NULL',
            'pec' => 'VARCHAR(255) DEFAULT NULL',
            'codice_destinatario' => 'VARCHAR(255) DEFAULT NULL',
            'is_professional' => 'TINYINT(1) DEFAULT 0',
            'ragione_sociale' => 'VARCHAR(255) DEFAULT NULL',
            'piva' => 'VARCHAR(255) DEFAULT NULL',
            'phone' => 'VARCHAR(32) DEFAULT NULL',
            'phone_mobile' => 'VARCHAR(32) DEFAULT NULL',
            'google_id' => 'VARCHAR(255) DEFAULT NULL',
        ];

        foreach ($columns as $column => $definition) {
            $checkColumn = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'customer` LIKE "' . pSQL($column) . '"');
            if (empty($checkColumn)) {
                Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'customer` ADD `' . pSQL($column) . '` ' . $definition);
            }
        }

        // Also check settings table for new columns
        $checkPrivate = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country` LIKE "enabled_fields_private"');
        if (empty($checkPrivate)) {
            Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'custom_registration_fields_country` 
                ADD `enabled_fields_private` TEXT,
                ADD `required_fields_private` TEXT');
        }

        return true;
    }

    public function getContent()
    {
        $this->alterCustomerTable();
        // Register hooks if missing (for existing installations)
        $this->registerHook('actionCustomerAccountUpdate');
        $this->registerHook('displayCustomerIdentityHeader');
        $this->registerHook('displayCustomerLoginFormAfter');
        $this->registerHook('displayCustomerAccountForm');

        if (Tools::getValue('ajax') && Tools::getValue('action') == 'getCountrySettings') {
            $this->ajaxProcessGetCountrySettings();
        }

        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $id_country = (int)Tools::getValue('id_country');
            $fields_list = ['codice_fiscale', 'pec', 'codice_destinatario', 'ragione_sociale', 'piva', 'phone', 'phone_mobile', 'id_state', 'address1', 'city', 'postcode'];
            
            $enabled_prof = [];
            $required_prof = [];
            $enabled_priv = [];
            $required_priv = [];
            
            foreach ($fields_list as $f) {
                if (Tools::getValue('enabled_fields_' . $f)) $enabled_prof[] = $f;
                if (Tools::getValue('required_fields_' . $f)) $required_prof[] = $f;
                if (Tools::getValue('enabled_fields_private_' . $f)) $enabled_priv[] = $f;
                if (Tools::getValue('required_fields_private_' . $f)) $required_priv[] = $f;
            }

            Db::getInstance()->execute('
                REPLACE INTO `' . _DB_PREFIX_ . 'custom_registration_fields_country`
                (`id_country`, `enabled_fields`, `required_fields`, `enabled_fields_private`, `required_fields_private`)
                VALUES (
                    ' . (int)$id_country . ',
                    "' . pSQL(json_encode($enabled_prof)) . '",
                    "' . pSQL(json_encode($required_prof)) . '",
                    "' . pSQL(json_encode($enabled_priv)) . '",
                    "' . pSQL(json_encode($required_priv)) . '"
                )
            ');

            Configuration::updateValue('GOOGLE_CLIENT_ID', Tools::getValue('GOOGLE_CLIENT_ID'));
            Configuration::updateValue('GOOGLE_CLIENT_SECRET', Tools::getValue('GOOGLE_CLIENT_SECRET'));
            Configuration::updateValue('GOOGLE_MAPS_API_KEY', Tools::getValue('GOOGLE_MAPS_API_KEY'));
            Configuration::updateValue('CRF_GROUP_PRIVATE', (int)Tools::getValue('CRF_GROUP_PRIVATE'));
            Configuration::updateValue('CRF_GROUP_PROFESSIONAL', (int)Tools::getValue('CRF_GROUP_PROFESSIONAL'));
            Configuration::updateValue('CRF_HIDE_GENDER', (int)Tools::getValue('CRF_HIDE_GENDER'));
            Configuration::updateValue('CRF_REQ_PEC_OR_SDI', (int)Tools::getValue('CRF_REQ_PEC_OR_SDI'));

            $output .= $this->displayConfirmation($this->l('Settings updated successfully for ') . ($id_country == 0 ? $this->l('Default Settings') : Country::getNameById($this->context->language->id, $id_country)));
        }

        return $output . $this->renderAdminJs() . $this->renderForm();
    }

    protected function ajaxProcessGetCountrySettings()
    {
        $id_country = (int)Tools::getValue('id_country');
        
        $settings = Db::getInstance()->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country`
            WHERE `id_country` = ' . (int)$id_country
        );

        if ($settings) {
            $enabled = json_decode($settings['enabled_fields'], true) ?: [];
            $required = json_decode($settings['required_fields'], true) ?: [];
            $enabled_private = json_decode($settings['enabled_fields_private'] ?? '[]', true) ?: [];
            $required_private = json_decode($settings['required_fields_private'] ?? '[]', true) ?: [];
        } else {
            $enabled = $required = $enabled_private = $required_private = [];
        }

        die(json_encode([
            'enabled' => $enabled,
            'required' => $required,
            'enabled_private' => $enabled_private,
            'required_private' => $required_private,
        ]));
    }

    protected function renderAdminJs()
    {
        $ajax_url = $this->context->link->getModuleLink($this->name, 'ajax', ['action' => 'getCountrySettings', 'ajax' => 1]);
        
        return '<script>
            $(document).ready(function() {
                $("#id_country").change(function() {
                    var id_country = $(this).val();
                    var crf_ajax_url = "' . $ajax_url . '";
                    
                    // Show a simple loading state (optional)
                    $("#fields_config_html").css("opacity", "0.5");

                    $.ajax({
                        type: "POST",
                        url: crf_ajax_url,
                        data: {
                            id_country: id_country
                        },
                        dataType: "json",
                        success: function(data) {
                            // Clear ALL checkboxes in the config tables
                            $("input[name^=\'enabled_fields_\']").prop("checked", false);
                            $("input[name^=\'required_fields_\']").prop("checked", false);
                            
                            if (data.enabled) {
                                data.enabled.forEach(function(f) {
                                    $("input[name=\'enabled_fields_" + f + "\']").prop("checked", true);
                                });
                            }
                            if (data.required) {
                                data.required.forEach(function(f) {
                                    $("input[name=\'required_fields_" + f + "\']").prop("checked", true);
                                });
                            }
                            if (data.enabled_private) {
                                data.enabled_private.forEach(function(f) {
                                    $("input[name=\'enabled_fields_private_" + f + "\']").prop("checked", true);
                                });
                            }
                            if (data.required_private) {
                                data.required_private.forEach(function(f) {
                                    $("input[name=\'required_fields_private_" + f + "\']").prop("checked", true);
                                });
                            }
                            
                            $("#fields_config_html").css("opacity", "1");
                        },
                        error: function() {
                            alert("Error loading settings. Please refresh the page.");
                            $("#fields_config_html").css("opacity", "1");
                        }
                    });
                });
            });
        </script>';
    }

    protected function renderFieldsConfigTable($fields_options)
    {
        // Get values for current country from DB or defaults
        $id_country = (int)Tools::getValue('id_country', Configuration::get('PS_COUNTRY_DEFAULT'));
        $settings = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country` WHERE `id_country` = ' . (int)$id_country);
        
        $current_values = [];
        if ($settings) {
            $current_values['enabled_fields'] = json_decode($settings['enabled_fields'], true) ?: [];
            $current_values['required_fields'] = json_decode($settings['required_fields'], true) ?: [];
            $current_values['enabled_fields_private'] = json_decode($settings['enabled_fields_private'], true) ?: [];
            $current_values['required_fields_private'] = json_decode($settings['required_fields_private'], true) ?: [];
        }

        $html = '<div class="row">
            <div class="col-lg-6">
                <div class="panel">
                    <div class="panel-heading"><i class="icon-user"></i> ' . $this->l('PRIVATO') . '</div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>' . $this->l('Field') . '</th>
                                <th class="text-center">' . $this->l('Visible') . '</th>
                                <th class="text-center">' . $this->l('Required') . '</th>
                            </tr>
                        </thead>
                        <tbody>';
        foreach ($fields_options as $option) {
            $is_enabled = in_array($option['id'], $current_values['enabled_fields_private'] ?? []);
            $is_required = in_array($option['id'], $current_values['required_fields_private'] ?? []);
            $html .= '<tr>
                <td>' . $option['name'] . '</td>
                <td class="text-center">
                    <input type="checkbox" name="enabled_fields_private_' . $option['id'] . '" value="1" ' . ($is_enabled ? 'checked' : '') . '>
                </td>
                <td class="text-center">
                    <input type="checkbox" name="required_fields_private_' . $option['id'] . '" value="1" ' . ($is_required ? 'checked' : '') . '>
                </td>
            </tr>';
        }
        $html .= '</tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel">
                    <div class="panel-heading"><i class="icon-briefcase"></i> ' . $this->l('PROFESSIONISTA') . '</div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>' . $this->l('Field') . '</th>
                                <th class="text-center">' . $this->l('Visible') . '</th>
                                <th class="text-center">' . $this->l('Required') . '</th>
                            </tr>
                        </thead>
                        <tbody>';
        foreach ($fields_options as $option) {
            $is_enabled = in_array($option['id'], $current_values['enabled_fields'] ?? []);
            $is_required = in_array($option['id'], $current_values['required_fields'] ?? []);
            $html .= '<tr>
                <td>' . $option['name'] . '</td>
                <td class="text-center">
                    <input type="checkbox" name="enabled_fields_' . $option['id'] . '" value="1" ' . ($is_enabled ? 'checked' : '') . '>
                </td>
                <td class="text-center">
                    <input type="checkbox" name="required_fields_' . $option['id'] . '" value="1" ' . ($is_required ? 'checked' : '') . '>
                </td>
            </tr>';
        }
        $html .= '</tbody>
                    </table>
                </div>
            </div>
        </div>';

        return $html;
    }

    protected function renderForm()
    {
        $countries = Country::getCountries($this->context->language->id, false);
        // Add "Default/Other Countries" option at the beginning
        array_unshift($countries, [
            'id_country' => 0,
            'name' => $this->l('Default Settings (Other Countries)')
        ]);

        $groups = Group::getGroups($this->context->language->id);

        $fields_options = [
            ['id' => 'codice_fiscale', 'name' => $this->l('Codice Fiscale')],
            ['id' => 'pec', 'name' => $this->l('PEC')],
            ['id' => 'codice_destinatario', 'name' => $this->l('Codice Destinatario')],
            ['id' => 'ragione_sociale', 'name' => $this->l('Ragione Sociale')],
            ['id' => 'piva', 'name' => $this->l('P.IVA')],
            ['id' => 'phone', 'name' => $this->l('Telefono principale')],
            ['id' => 'phone_mobile', 'name' => $this->l('Telefono secondario')],
            ['id' => 'id_state', 'name' => $this->l('Province')],
            ['id' => 'address1', 'name' => $this->l('Address')],
            ['id' => 'city', 'name' => $this->l('City')],
            ['id' => 'postcode', 'name' => $this->l('Postcode')],
        ];

        $fields_form_general = [
            'form' => [
                'legend' => [
                    'title' => $this->l('General Settings & Groups'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Group for "Privato"'),
                        'name' => 'CRF_GROUP_PRIVATE',
                        'options' => [
                            'query' => $groups,
                            'id' => 'id_group',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Group for "Professionista"'),
                        'name' => 'CRF_GROUP_PROFESSIONAL',
                        'options' => [
                            'query' => $groups,
                            'id' => 'id_group',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Google Login'),
                        'name' => 'GOOGLE_LOGIN_ENABLED',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Google Client ID'),
                        'name' => 'GOOGLE_CLIENT_ID',
                        'desc' => $this->l('Get this from Google Cloud Console'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Google Client Secret'),
                        'name' => 'GOOGLE_CLIENT_SECRET',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Google Maps API Key'),
                        'name' => 'GOOGLE_MAPS_API_KEY',
                        'desc' => $this->l('Required for City/Address Autocomplete. Enable "Places API" in Google Cloud Console.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Hide Social Title (Gender)'),
                        'name' => 'CRF_HIDE_GENDER',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('Hide Mr./Mrs. selection from registration form'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Require PEC or Codice Destinatario'),
                        'name' => 'CRF_REQ_PEC_OR_SDI',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                        'desc' => $this->l('For Professionals, require at least one electronic invoicing field.'),
                    ],
                ],
                'submit' => ['title' => $this->l('Save General Settings')],
            ],
        ];

        $fields_form_country = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Fields Per Country Configuration'),
                    'icon' => 'icon-globe',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Select Country to Configure'),
                        'name' => 'id_country',
                        'options' => [
                            'query' => $countries,
                            'id' => 'id_country',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'fields_config_html',
                        'html_content' => $this->renderFieldsConfigTable($fields_options),
                    ],
                ],
                'submit' => ['title' => $this->l('Save Country Settings')],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_callbacks = true;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $default_country = Configuration::get('PS_COUNTRY_DEFAULT');
        $helper->fields_value['id_country'] = $default_country;
        $helper->fields_value['GOOGLE_LOGIN_ENABLED'] = Configuration::get('GOOGLE_LOGIN_ENABLED');
        $helper->fields_value['GOOGLE_CLIENT_ID'] = Configuration::get('GOOGLE_CLIENT_ID');
        $helper->fields_value['GOOGLE_CLIENT_SECRET'] = Configuration::get('GOOGLE_CLIENT_SECRET');
        $helper->fields_value['GOOGLE_MAPS_API_KEY'] = Configuration::get('GOOGLE_MAPS_API_KEY');
        $helper->fields_value['CRF_GROUP_PRIVATE'] = Configuration::get('CRF_GROUP_PRIVATE');
        $helper->fields_value['CRF_GROUP_PROFESSIONAL'] = Configuration::get('CRF_GROUP_PROFESSIONAL');
        $helper->fields_value['CRF_HIDE_GENDER'] = Configuration::get('CRF_HIDE_GENDER');
        $helper->fields_value['CRF_REQ_PEC_OR_SDI'] = Configuration::get('CRF_REQ_PEC_OR_SDI');

        // Load values for the default country
        $settings = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country` WHERE `id_country` = ' . (int)$default_country);
        if ($settings) {
            $map = [
                'enabled_fields' => 'enabled_fields',
                'required_fields' => 'required_fields',
                'enabled_fields_private' => 'enabled_fields_private',
                'required_fields_private' => 'required_fields_private'
            ];
            foreach ($map as $dbKey => $fieldKey) {
                if (isset($settings[$dbKey]) && !empty($settings[$dbKey])) {
                    $decoded = json_decode($settings[$dbKey], true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $f) $helper->fields_value[$fieldKey . '_' . $f] = true;
                    }
                }
            }
        }

        return $helper->generateForm([$fields_form_general, $fields_form_country]);
    }

    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . 'views/js/custom_registration_fields.js');
        $this->context->controller->addCSS($this->_path . 'views/css/custom_registration_fields.css');
        
        // Add Select2 for searchable dropdowns
        $this->context->controller->addJS('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', false);
        $this->context->controller->addCSS('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', 'all', null, false);

        Media::addJsDef([
            'crf_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax'),
            'crf_default_country' => (int)Configuration::get('PS_COUNTRY_DEFAULT'),
            'crf_google_maps_key' => Configuration::get('GOOGLE_MAPS_API_KEY'),
            'crf_labels' => [
                'cf_private' => $this->l('Codice Fiscale'),
                'cf_prof' => $this->l('Codice Fiscale dell\'azienda')
            ]
        ]);

        if (Configuration::get('GOOGLE_MAPS_API_KEY')) {
            $this->context->controller->addJS('https://maps.googleapis.com/maps/api/js?key=' . Configuration::get('GOOGLE_MAPS_API_KEY') . '&libraries=places');
        }
    }

    public function hookAdditionalCustomerFormFields($params)
    {
        $fields = [];
        $id_lang = $this->context->language->id;
        $customer = $this->context->customer;

        // Hide unwanted standard PrestaShop fields if configured
        if (Configuration::get('CRF_HIDE_GENDER')) {
            foreach ($params['fields'] as $f) {
                if ($f->getName() == 'id_gender') {
                    $f->setType('hidden');
                }
            }
        }
        $id_country = (int)Configuration::get('PS_COUNTRY_DEFAULT');
        
        // Load custom data if customer is logged in
        $custom_data = [];
        if (Validate::isLoadedObject($customer)) {
            $custom_data = Db::getInstance()->getRow('
                SELECT * FROM `' . _DB_PREFIX_ . 'customer`
                WHERE `id_customer` = ' . (int)$customer->id
            ) ?: [];
        }

        // Load settings: Try specific country, then fallback to ID 0 (Global Default)
        $id_country_to_load = (int)Tools::getValue('id_country', $id_country);
        $settings = Db::getInstance()->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country`
            WHERE `id_country` = ' . (int)$id_country_to_load
        );

        if (!$settings && $id_country_to_load != 0) {
            $settings = Db::getInstance()->getRow('
                SELECT * FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country`
                WHERE `id_country` = 0
            ');
        }

        $req_priv = $settings ? json_decode($settings['required_fields_private'] ?? '[]', true) : [];
        if (!is_array($req_priv)) $req_priv = [];

        // 1. Account Type (Privato / Professionista)
        $fields[] = (new FormField())
            ->setName('is_professional')
            ->setType('radio-buttons')
            ->setLabel($this->l('Account Type'))
            ->addAvailableValue(0, $this->l('Privato'))
            ->addAvailableValue(1, $this->l('Professionista'))
            ->setValue((int)($custom_data['is_professional'] ?? 0));

        // 2. Country Selector (Restored because the shop works with multiple countries)
        $countries = Country::getCountries($id_lang, true);
        $countryField = (new FormField())
            ->setName('id_country')
            ->setType('select')
            ->setLabel($this->l('Country'))
            ->setRequired(true)
            ->setValue(Configuration::get('PS_COUNTRY_DEFAULT'));
        
        foreach ($countries as $c) {
            $countryField->addAvailableValue($c['id_country'], $c['name']);
        }
        $fields[] = $countryField;

        // 3. Professional/Custom Fields
        $fields[] = (new FormField())
            ->setName('ragione_sociale')
            ->setType('text')
            ->setRequired(in_array('ragione_sociale', $req_priv))
            ->setLabel($this->l('Ragione Sociale'))
            ->setValue($custom_data['ragione_sociale'] ?? '');

        $fields[] = (new FormField())
            ->setName('codice_fiscale')
            ->setType('text')
            ->setRequired(in_array('codice_fiscale', $req_priv))
            ->setLabel($this->l('Codice Fiscale dell\'azienda'))
            ->setValue($custom_data['codice_fiscale'] ?? '');

        $fields[] = (new FormField())
            ->setName('piva')
            ->setType('text')
            ->setRequired(in_array('piva', $req_priv))
            ->setLabel($this->l('P.IVA'))
            ->setValue($custom_data['piva'] ?? '');

        // Wrap these fields in a container for grid layout
        $fields[] = (new FormField())
            ->setName('crf_container_start')
            ->setType('hidden');

        $fields[] = (new FormField())
            ->setName('pec')
            ->setType('text')
            ->setRequired(in_array('pec', $req_priv))
            ->setLabel($this->l('PEC'))
            ->setValue($custom_data['pec'] ?? '');

        $fields[] = (new FormField())
            ->setName('codice_destinatario')
            ->setType('text')
            ->setRequired(in_array('codice_destinatario', $req_priv))
            ->setLabel($this->l('Codice destinatario'))
            ->setValue($custom_data['codice_destinatario'] ?? '');

        $fields[] = (new FormField())
            ->setName('phone')
            ->setType('text')
            ->setRequired(in_array('phone', $req_priv))
            ->setLabel($this->l('Telefono principale'))
            ->setValue($custom_data['phone'] ?? '');

        $fields[] = (new FormField())
            ->setName('phone_mobile')
            ->setType('text')
            ->setRequired(in_array('phone_mobile', $req_priv))
            ->setLabel($this->l('Telefono secondario'))
            ->setValue($custom_data['phone_mobile'] ?? '');

        $fields[] = (new FormField())
            ->setName('address1')
            ->setType('text')
            ->setRequired(in_array('address1', $req_priv))
            ->setLabel($this->l('Address'))
            ->setValue('');

        $fields[] = (new FormField())
            ->setName('city')
            ->setType('text')
            ->setRequired(in_array('city', $req_priv))
            ->setLabel($this->l('City'))
            ->setValue('');

        $fields[] = (new FormField())
            ->setName('id_state')
            ->setType('select')
            ->setRequired(in_array('id_state', $req_priv))
            ->setLabel($this->l('Province'))
            ->setValue(0);

        $fields[] = (new FormField())
            ->setName('postcode')
            ->setType('text')
            ->setRequired(in_array('postcode', $req_priv))
            ->setLabel($this->l('Postcode'))
            ->setValue('');

        $fields[] = (new FormField())
            ->setName('crf_container_end')
            ->setType('hidden');

        return $fields;
    }

    public function hookValidateCustomerFormFields($params)
    {
        $fields = $params['fields'];
        $id_country = (int)Tools::getValue('id_country');
        $is_professional = (int)Tools::getValue('is_professional');

        // Try specific country settings, fallback to global (ID 0)
        $settings = Db::getInstance()->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country`
            WHERE `id_country` = ' . (int)$id_country
        );

        if (!$settings && $id_country != 0) {
            $settings = Db::getInstance()->getRow('
                SELECT * FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country`
                WHERE `id_country` = 0
            ');
        }

        if ($settings) {
            $required_prof = json_decode($settings['required_fields'], true);
            $required_priv = json_decode($settings['required_fields_private'] ?? '[]', true);
            $required_fields = $is_professional ? $required_prof : $required_priv;
        } else {
            $required_fields = [];
        }

        // Special rule for Professionals: At least one of PEC or Codice Destinatario must be filled
        if ($is_professional && Configuration::get('CRF_REQ_PEC_OR_SDI')) {
            $pec = '';
            $sdi = '';
            foreach ($fields as $field) {
                if ($field->getName() == 'pec') $pec = $field->getValue();
                if ($field->getName() == 'codice_destinatario') $sdi = $field->getValue();
            }

            if (empty($pec) && empty($sdi)) {
                $error_msg = $this->l('You must provide either PEC or Codice Destinatario.');
                foreach ($fields as $field) {
                    if ($field->getName() == 'pec' || $field->getName() == 'codice_destinatario') {
                        $field->addError($error_msg);
                    }
                }
            }
        }

        foreach ($fields as $field) {
            if (is_array($required_fields) && in_array($field->getName(), $required_fields)) {
                if (empty($field->getValue())) {
                    $field->addError($this->l('This field is required.'));
                }
            }
        }
    }

    public function hookActionCustomerAccountAdd($params)
    {
        $this->saveCustomerData($params['newCustomer']);
    }

    public function hookActionCustomerAccountUpdate($params)
    {
        $this->saveCustomerData($params['customer']);
    }

    public function hookDisplayCustomerIdentityHeader()
    {
        if (isset($this->context->cookie->is_new_google_user) && $this->context->cookie->is_new_google_user) {
            unset($this->context->cookie->is_new_google_user);
            
            return '<div class="alert alert-info">
                <strong>' . $this->l('Welcome!') . '</strong> ' . 
                $this->l('We\'ve pre-filled some information from your Google account. Please check your data and complete the missing fields (tax info, address) to enjoy a seamless shopping experience.') . '
            </div>';
        }
    }

    public function hookDisplayCustomerLoginFormAfter()
    {
        return $this->renderGoogleButton();
    }

    public function hookDisplayCustomerAccountForm()
    {
        return $this->renderGoogleButton();
    }

    protected function renderGoogleButton()
    {
        if (!Configuration::get('GOOGLE_LOGIN_ENABLED')) {
            return '';
        }

        $clientId = Configuration::get('GOOGLE_CLIENT_ID');
        $clientSecret = Configuration::get('GOOGLE_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            return '';
        }

        $googleUrl = $this->context->link->getModuleLink($this->name, 'googlelogin');

        // Professional and modern styling for the button
        return '
        <div class="google-login-container" style="margin: 20px 0; text-align: center; border-top: 1px solid #eee; padding-top: 20px;">
            <p style="margin-bottom: 15px; color: #777; font-size: 14px;">' . $this->l('Or login with') . '</p>
            <a href="' . $googleUrl . '" class="btn google-login-btn" style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #fff;
                color: #444;
                border: 1px solid #dadce0;
                padding: 10px 24px;
                border-radius: 4px;
                text-decoration: none;
                font-family: \'Roboto\', arial, sans-serif;
                font-weight: 500;
                box-shadow: 0 1px 3px rgba(0,0,0,0.08);
                transition: background 0.2s, box-shadow 0.2s;
            ">
                <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_Logo.svg" alt="Google" style="width: 18px; height: 18px; margin-right: 12px;">
                <span>' . $this->l('Sign in with Google') . '</span>
            </a>
            <style>
                .google-login-btn:hover {
                    background: #f8f9fa !important;
                    box-shadow: 0 1px 8px rgba(0,0,0,0.12) !important;
                    color: #222 !important;
                }
            </style>
        </div>';
    }

    protected function saveCustomerData($customer)
    {
        if (!($customer instanceof Customer)) {
            return;
        }

        $is_professional = (int)Tools::getValue('is_professional');
        $phone_mobile = Tools::getValue('phone_mobile');
        
        // Ensure columns exist (handle case where module was updated but not reinstalled)
        if (!Configuration::get('CRF_DB_V1')) {
            $this->alterCustomerTable();
            Configuration::updateValue('CRF_DB_V1', 1);
        }

        // Manual SQL update because core Customer class doesn't know about our fields
        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'customer` 
            SET `is_professional` = ' . (int)$is_professional . ',
                `ragione_sociale` = "' . pSQL(Tools::getValue('ragione_sociale')) . '",
                `codice_fiscale` = "' . pSQL(Tools::getValue('codice_fiscale')) . '",
                `pec` = "' . pSQL(Tools::getValue('pec')) . '",
                `codice_destinatario` = "' . pSQL(Tools::getValue('codice_destinatario')) . '",
                `piva` = "' . pSQL(Tools::getValue('piva')) . '",
                `phone` = "' . pSQL(Tools::getValue('phone')) . '",
                `phone_mobile` = "' . pSQL($phone_mobile) . '"
            WHERE `id_customer` = ' . (int)$customer->id
        );
        
        // Handle Group Assignment
        $id_group = 0;
        if ($is_professional) {
            $id_group = (int)Configuration::get('CRF_GROUP_PROFESSIONAL');
        } else {
            $id_group = (int)Configuration::get('CRF_GROUP_PRIVATE');
        }

        if ($id_group) {
            $customer->id_default_group = $id_group;
            $customer->update(); 
            
            // Clean existing groups and add ONLY the selected one
            $customer->cleanGroups();
            $customer->addGroups([$id_group]);
        }

        // Create Address if address fields are provided (only during registration, address1 is usually empty in Identity update)
        $address1 = Tools::getValue('address1');
        if (!empty($address1)) {
            $id_country = (int)Tools::getValue('id_country');
            if (!$id_country) {
                $id_country = (int)Configuration::get('PS_COUNTRY_DEFAULT');
            }

            $address = new Address();
            $address->id_customer = (int)$customer->id;
            $address->id_country = $id_country;
            $address->alias = $this->l('My Address');
            $address->lastname = $customer->lastname;
            $address->firstname = $customer->firstname;
            $address->address1 = $address1;
            $address->city = Tools::getValue('city');
            $address->id_state = (int)Tools::getValue('id_state');
            $address->postcode = Tools::getValue('postcode');
            $address->phone = Tools::getValue('phone');
            $address->phone_mobile = Tools::getValue('phone_mobile');
            $address->company = Tools::getValue('ragione_sociale');
            $address->vat_number = Tools::getValue('piva');
            $address->dni = Tools::getValue('codice_fiscale');
            $address->add();
        }
    }

    public function hookDisplayAdminCustomers($params)
    {
        $id_customer = (int)$params['id_customer'];
        $custom_data = Db::getInstance()->getRow('
            SELECT is_professional, ragione_sociale, codice_fiscale, piva, pec, codice_destinatario, phone, phone_mobile 
            FROM `' . _DB_PREFIX_ . 'customer` 
            WHERE `id_customer` = ' . (int)$id_customer
        );

        if (!$custom_data) {
            return '';
        }

        $this->context->smarty->assign([
            'customer_crf' => $custom_data,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/customer_fields.tpl');
    }

    /**
     * For PrestaShop 8+ / 9 (Symfony-based forms)
     */
    public function hookActionCustomerFormBuilderModifier($params)
    {
        $formBuilder = $params['form_builder'];
        $id_customer = (int)$params['id'];
        
        $custom_data = Db::getInstance()->getRow('
            SELECT is_professional, ragione_sociale, codice_fiscale, piva, pec, codice_destinatario, phone, phone_mobile 
            FROM `' . _DB_PREFIX_ . 'customer` 
            WHERE `id_customer` = ' . (int)$id_customer
        ) ?: [];

        $formBuilder->add('is_professional', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
            'label' => $this->l('Account Type'),
            'choices' => [
                $this->l('Privato') => 0,
                $this->l('Professionista') => 1,
            ],
            'required' => true,
            'data' => (int)($custom_data['is_professional'] ?? 0),
        ]);

        $formBuilder->add('ragione_sociale', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Ragione Sociale'),
            'required' => false,
            'data' => $custom_data['ragione_sociale'] ?? '',
        ]);

        $formBuilder->add('codice_fiscale', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Codice Fiscale'),
            'required' => false,
            'data' => $custom_data['codice_fiscale'] ?? '',
        ]);

        $formBuilder->add('piva', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('P.IVA'),
            'required' => false,
            'data' => $custom_data['piva'] ?? '',
        ]);

        $formBuilder->add('pec', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('PEC'),
            'required' => false,
            'data' => $custom_data['pec'] ?? '',
        ]);

        $formBuilder->add('codice_destinatario', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Codice Destinatario'),
            'required' => false,
            'data' => $custom_data['codice_destinatario'] ?? '',
        ]);

        $formBuilder->add('phone', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Telephone'),
            'required' => false,
            'data' => $custom_data['phone'] ?? '',
        ]);

        $formBuilder->add('phone_mobile', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Alternative Phone'),
            'required' => false,
            'data' => $custom_data['phone_mobile'] ?? '',
        ]);

        $formBuilder->add('address1', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Address'),
            'required' => false,
            'data' => '', // Address is not directly on customer, will be handled by address form
        ]);

        $formBuilder->add('city', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('City'),
            'required' => false,
            'data' => '', // Address is not directly on customer, will be handled by address form
        ]);

        $formBuilder->add('postcode', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Postcode'),
            'required' => false,
            'data' => '', // Address is not directly on customer, will be handled by address form
        ]);
    }

    public function hookActionAfterCreateCustomerFormHandler($params)
    {
        $this->handleAdminCustomerSave($params);
    }

    public function hookActionAfterUpdateCustomerFormHandler($params)
    {
        $this->handleAdminCustomerSave($params);
    }

    protected function handleAdminCustomerSave($params)
    {
        $id_customer = (int)$params['id'];
        $formData = $params['form_data'];

        Db::getInstance()->execute('
            UPDATE `' . _DB_PREFIX_ . 'customer` 
            SET `is_professional` = ' . (int)($formData['is_professional'] ?? 0) . ',
                `ragione_sociale` = "' . pSQL($formData['ragione_sociale'] ?? '') . '",
                `codice_fiscale` = "' . pSQL($formData['codice_fiscale'] ?? '') . '",
                `piva` = "' . pSQL($formData['piva'] ?? '') . '",
                `pec` = "' . pSQL($formData['pec'] ?? '') . '",
                `codice_destinatario` = "' . pSQL($formData['codice_destinatario'] ?? '') . '",
                `phone` = "' . pSQL($formData['phone'] ?? '') . '",
                `phone_mobile` = "' . pSQL($formData['phone_mobile'] ?? '') . '"
            WHERE `id_customer` = ' . (int)$id_customer
        );
    }

    public function hookDisplayCustomerLoginForm()
    {
        return $this->displayGoogleButton();
    }

    public function hookDisplayAfterCustomerRegistrationForm()
    {
        return $this->displayGoogleButton();
    }

    protected function displayGoogleButton()
    {
        if (!Configuration::get('GOOGLE_CLIENT_ID')) {
            return '';
        }

        $this->context->smarty->assign([
            'google_login_url' => $this->context->link->getModuleLink($this->name, 'googlelogin'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/google_button.tpl');
    }
}
