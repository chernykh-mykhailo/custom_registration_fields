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
            $this->registerHook('header') &&
            $this->registerHook('displayAdminCustomers') &&
            $this->registerHook('actionCustomerFormBuilderModifier') &&
            $this->registerHook('actionAfterCreateCustomerFormHandler') &&
            $this->registerHook('actionAfterUpdateCustomerFormHandler') &&
            $this->alterCustomerTable();
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
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $id_country = (int)Tools::getValue('id_country');
            $fields_list = ['codice_fiscale', 'pec', 'codice_destinatario', 'ragione_sociale', 'piva', 'phone', 'phone_mobile', 'address1', 'city', 'postcode'];
            
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
            Configuration::updateValue('CRF_GROUP_PRIVATE', (int)Tools::getValue('CRF_GROUP_PRIVATE'));
            Configuration::updateValue('CRF_GROUP_PROFESSIONAL', (int)Tools::getValue('CRF_GROUP_PROFESSIONAL'));
            Configuration::updateValue('CRF_HIDE_GENDER', (int)Tools::getValue('CRF_HIDE_GENDER'));

            $output .= $this->displayConfirmation($this->l('Settings updated successfully for ') . Country::getNameById($this->context->language->id, $id_country));
        }

        return $output . $this->renderAdminJs() . $this->renderForm();
    }

    protected function renderAdminJs()
    {
        return '<script>
            $(document).ready(function() {
                $("#id_country").change(function() {
                    var id_country = $(this).val();
                    $.ajax({
                        type: "POST",
                        url: crf_ajax_url,
                        data: {
                            action: "getCountrySettings",
                            id_country: id_country,
                            ajax: 1
                        },
                        success: function(response) {
                            var data = JSON.parse(response);
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
                        }
                    });
                });
            });
        </script>';
    }

    protected function renderForm()
    {
        $countries = Country::getCountries($this->context->language->id, false);
        $groups = Group::getGroups($this->context->language->id);

        $fields_options = [
            ['id' => 'codice_fiscale', 'name' => $this->l('Codice Fiscale')],
            ['id' => 'pec', 'name' => $this->l('PEC')],
            ['id' => 'codice_destinatario', 'name' => $this->l('Codice Destinatario')],
            ['id' => 'ragione_sociale', 'name' => $this->l('Ragione Sociale')],
            ['id' => 'piva', 'name' => $this->l('P.IVA')],
            ['id' => 'phone', 'name' => $this->l('Telefono principale')],
            ['id' => 'phone_mobile', 'name' => $this->l('Telefono secondario')],
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
                        'type' => 'checkbox',
                        'label' => $this->l('Fields visible for PRIVATO'),
                        'name' => 'enabled_fields_private',
                        'values' => [
                            'query' => $fields_options,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Fields REQUIRED for PRIVATO'),
                        'name' => 'required_fields_private',
                        'values' => [
                            'query' => $fields_options,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Fields visible for PROFESSIONISTA'),
                        'name' => 'enabled_fields',
                        'values' => [
                            'query' => $fields_options,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Fields REQUIRED for PROFESSIONISTA'),
                        'name' => 'required_fields',
                        'values' => [
                            'query' => $fields_options,
                            'id' => 'id',
                            'name' => 'name',
                        ],
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
        $helper->fields_value['GOOGLE_CLIENT_ID'] = Configuration::get('GOOGLE_CLIENT_ID');
        $helper->fields_value['GOOGLE_CLIENT_SECRET'] = Configuration::get('GOOGLE_CLIENT_SECRET');
        $helper->fields_value['CRF_GROUP_PROFESSIONAL'] = Configuration::get('CRF_GROUP_PROFESSIONAL');
        $helper->fields_value['CRF_HIDE_GENDER'] = Configuration::get('CRF_HIDE_GENDER');

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
        
        Media::addJsDef([
            'crf_ajax_url' => $this->context->link->getModuleLink($this->name, 'ajax'),
            'crf_default_country' => (int)Configuration::get('PS_COUNTRY_DEFAULT'),
        ]);
    }

    public function hookAdditionalCustomerFormFields($params)
    {
        $fields = [];
        $id_lang = $this->context->language->id;

        // Hide unwanted standard PrestaShop fields if configured
        if (Configuration::get('CRF_HIDE_GENDER')) {
            foreach ($params['fields'] as $f) {
                if ($f->getName() == 'id_gender') {
                    $f->setType('hidden');
                }
            }
        }
        $id_country = (int)Configuration::get('PS_COUNTRY_DEFAULT');
        
        // Load settings for the default country to set initial "required" states
        $settings = Db::getInstance()->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country`
            WHERE `id_country` = ' . (int)$id_country
        );
        $req_priv = $settings ? json_decode($settings['required_fields_private'] ?? '[]', true) : [];
        if (!is_array($req_priv)) $req_priv = [];

        // 1. Account Type (Privato / Professionista)
        $fields[] = (new FormField())
            ->setName('is_professional')
            ->setType('radio-buttons')
            ->setLabel($this->l('Account Type'))
            ->addAvailableValue(0, $this->l('Privato'))
            ->addAvailableValue(1, $this->l('Professionista'))
            ->setValue(0);

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
            ->setLabel($this->l('Ragione Sociale'));

        $fields[] = (new FormField())
            ->setName('codice_fiscale')
            ->setType('text')
            ->setRequired(in_array('codice_fiscale', $req_priv))
            ->setLabel($this->l('Codice Fiscale dell\'azienda'));

        $fields[] = (new FormField())
            ->setName('piva')
            ->setType('text')
            ->setRequired(in_array('piva', $req_priv))
            ->setLabel($this->l('P.IVA'));

        $fields[] = (new FormField())
            ->setName('pec')
            ->setType('text')
            ->setRequired(in_array('pec', $req_priv))
            ->setLabel($this->l('PEC (opzionale)'));

        $fields[] = (new FormField())
            ->setName('codice_destinatario')
            ->setType('text')
            ->setRequired(in_array('codice_destinatario', $req_priv))
            ->setLabel($this->l('Codice destinatario (opzionale)'));

        $fields[] = (new FormField())
            ->setName('phone_mobile')
            ->setType('text')
            ->setRequired(in_array('phone_mobile', $req_priv))
            ->setLabel($this->l('Telefono secondario'));

        $fields[] = (new FormField())
            ->setName('phone')
            ->setType('text')
            ->setRequired(in_array('phone', $req_priv))
            ->setLabel($this->l('Telefono principale'));

        $fields[] = (new FormField())
            ->setName('address1')
            ->setType('text')
            ->setRequired(in_array('address1', $req_priv))
            ->setLabel($this->l('Address'));

        $fields[] = (new FormField())
            ->setName('city')
            ->setType('text')
            ->setRequired(in_array('city', $req_priv))
            ->setLabel($this->l('City'));

        $fields[] = (new FormField())
            ->setName('postcode')
            ->setType('text')
            ->setRequired(in_array('postcode', $req_priv))
            ->setLabel($this->l('Postcode'));

        return $fields;
    }

    public function hookValidateCustomerFormFields($params)
    {
        $fields = $params['fields'];
        $id_country = (int)Tools::getValue('id_country');
        $is_professional = (int)Tools::getValue('is_professional');

        $settings = Db::getInstance()->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country`
            WHERE `id_country` = ' . (int)$id_country
        );

        if ($settings) {
            $required_prof = json_decode($settings['required_fields'], true);
            $required_priv = json_decode($settings['required_fields_private'] ?? '[]', true);
            $required_fields = $is_professional ? $required_prof : $required_priv;
        } else {
            $required_fields = [];
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
        $customer = $params['newCustomer'];
        if (!($customer instanceof Customer)) {
            return;
        }

        $is_professional = (int)Tools::getValue('is_professional');
        
        $customer->is_professional = $is_professional;
        $customer->ragione_sociale = Tools::getValue('ragione_sociale');
        $customer->codice_fiscale = Tools::getValue('codice_fiscale');
        $customer->pec = Tools::getValue('pec');
        $customer->codice_destinatario = Tools::getValue('codice_destinatario');
        $customer->piva = Tools::getValue('piva');
        $customer->phone = Tools::getValue('phone');
        
        // Handle Group Assignment
        $id_group = 1; // Default
        if ($is_professional) {
            $id_group = (int)Configuration::get('CRF_GROUP_PROFESSIONAL');
        } else {
            $id_group = (int)Configuration::get('CRF_GROUP_PRIVATE');
        }

        if ($id_group) {
            $customer->id_default_group = $id_group;
            $customer->update(); // Save standard fields first
            
            // Sync with ps_customer_group table
            $customer->cleanGroups();
            $customer->addGroups([$id_group]);
        } else {
            $customer->update();
        }

        // Create Address if address fields are provided
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
        $customer = new Customer($id_customer);

        if (!Validate::isLoadedObject($customer)) {
            return '';
        }

        $this->context->smarty->assign([
            'customer_crf' => [
                'is_professional' => $customer->is_professional,
                'ragione_sociale' => $customer->ragione_sociale,
                'codice_fiscale' => $customer->codice_fiscale,
                'piva' => $customer->piva,
                'pec' => $customer->pec,
                'codice_destinatario' => $customer->codice_destinatario,
                'phone' => $customer->phone,
            ],
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
        $customer = new Customer($id_customer);

        $formBuilder->add('is_professional', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
            'label' => $this->l('Account Type'),
            'choices' => [
                $this->l('Privato') => 0,
                $this->l('Professionista') => 1,
            ],
            'required' => true,
            'data' => (int)$customer->is_professional,
        ]);

        $formBuilder->add('ragione_sociale', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Ragione Sociale'),
            'required' => false,
            'data' => $customer->ragione_sociale,
        ]);

        $formBuilder->add('codice_fiscale', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Codice Fiscale'),
            'required' => false,
            'data' => $customer->codice_fiscale,
        ]);

        $formBuilder->add('piva', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('P.IVA'),
            'required' => false,
            'data' => $customer->piva,
        ]);

        $formBuilder->add('pec', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('PEC'),
            'required' => false,
            'data' => $customer->pec,
        ]);

        $formBuilder->add('codice_destinatario', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Codice Destinatario'),
            'required' => false,
            'data' => $customer->codice_destinatario,
        ]);

        $formBuilder->add('phone', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
            'label' => $this->l('Telephone'),
            'required' => false,
            'data' => $customer->phone,
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
        $customer = new Customer($id_customer);
        $formData = $params['form_data'];

        if (Validate::isLoadedObject($customer)) {
            $customer->is_professional = (int)$formData['is_professional'];
            $customer->ragione_sociale = $formData['ragione_sociale'];
            $customer->codice_fiscale = $formData['codice_fiscale'];
            $customer->piva = $formData['piva'];
            $customer->pec = $formData['pec'];
            $customer->codice_destinatario = $formData['codice_destinatario'];
            $customer->phone = $formData['phone'];
            $customer->update();
        }
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
