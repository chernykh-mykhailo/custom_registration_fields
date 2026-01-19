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

        $id_country = (int)Tools::getValue('id_country');
        
        $settings = Db::getInstance()->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'custom_registration_fields_country`
            WHERE `id_country` = ' . (int)$id_country
        );

        if ($settings) {
            $settings['enabled_fields'] = json_decode($settings['enabled_fields'], true);
            $settings['required_fields'] = json_decode($settings['required_fields'], true);
            $settings['enabled_fields_private'] = json_decode($settings['enabled_fields_private'] ?? '[]', true);
            $settings['required_fields_private'] = json_decode($settings['required_fields_private'] ?? '[]', true);
        } else {
            $settings = [
                'enabled_fields' => [],
                'required_fields' => [],
                'enabled_fields_private' => [],
                'required_fields_private' => [],
            ];
        }

        header('Content-Type: application/json');
        die(json_encode([
            'enabled' => $settings['enabled_fields'],
            'required' => $settings['required_fields'],
            'enabled_private' => $settings['enabled_fields_private'],
            'required_private' => $settings['required_fields_private'],
        ]));
    }
}
