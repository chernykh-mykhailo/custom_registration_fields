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
 */

class Custom_registration_fieldsAjaxModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $action = Tools::getValue('action');
        $id_country = (int)Tools::getValue('id_country');
        
        if ($action === 'getCountrySettings') {
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

            header('Content-Type: application/json');
            die(json_encode([
                'enabled' => $enabled,
                'required' => $required,
                'enabled_private' => $enabled_private,
                'required_private' => $required_private,
                'req_pec_sdi' => (int)Configuration::get('CRF_REQ_PEC_OR_SDI'),
            ]));
        }

        // Fetch states for this country (used in registration form)
        $states = State::getStatesByIdCountry($id_country);
        $states_list = [];
        foreach ($states as $state) {
            $states_list[] = [
                'id' => $state['id_state'],
                'name' => $state['name']
            ];
        }

        header('Content-Type: application/json');
        die(json_encode([
            'states' => $states_list
        ]));
    }
}
