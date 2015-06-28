<?php
/***************************************************************************
*                                                                          *
*   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************
* PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
* "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
****************************************************************************/

namespace Tygh\StoreImport\Ult;
use Tygh\StoreImport\General;
use Tygh\Registry;
use Tygh\Addons\SchemesManager as AddonSchemesManager;

class F424T431
{
    protected $store_data = array();
    protected $main_sql_filename = 'ult_F424T431.sql';

    public function __construct($store_data)
    {
        $store_data['product_edition'] = 'ULTIMATE';
        $this->store_data = $store_data;
    }

    public function import($db_already_cloned)
    {
        General::setProgressTitle(__CLASS__);
        if (!$db_already_cloned) {
            if (!General::cloneImportedDB($this->store_data)) {
                return false;
            }
        } else {
            General::setEmptyProgressBar(__('importing_data'));
            General::setEmptyProgressBar(__('importing_data'));
        }

        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        General::processAddons($this->store_data, __CLASS__);

        $main_sql = Registry::get('config.dir.addons') . 'store_import/database/' . $this->main_sql_filename;
        if (is_file($main_sql)) {
            //Process main sql
            if (!db_import_sql_file($main_sql)) {
                return false;
            }
        }

        $this->_edpProducts();
        $this->_bmNewLinkField();
        $this->_removeShippingSettings();
        $this->_convertImageVerification();
//        $this->_addNewSettingTimeframe();
        $this->_newFieldInProcessor();
        $this->_renameCategoryTableFields();
        $this->_updatePayPalPayments();
        $this->_addNewFieldToMenu();
        General::setCustomCssClassForResponsive();
        General::addCheckoutSection();
        General::coreCheckoutSettings();
        General::removeNullTextFields();
        $this->_removeUltNullTextFields();
        General::filtersRefactoring();
        General::removeNewsLogger();
        General::convertNewsToBlog();
        General::pagesRemoveShowInPopup();
        General::removeTplDebugSetting();
        $this->_removeCheckoutStyleSetting();
        General::revertSearchFieldBlock();
        General::addFullHttps();
        General::removeUseEmailAsLoginSetting();
        General::removeUserLoginEximField();
        $this->coreLogosLinkedToStyles();

        db_query("UPDATE ?:usergroup_privileges SET privilege = 'backup_restore' WHERE privilege = 'database_maintenance'");
        db_query("UPDATE ?:privileges SET privilege = 'backup_restore' WHERE privilege = 'database_maintenance'");

//        General::restoreSettings();
        if (db_get_field("SELECT status FROM ?:addons WHERE addon = 'searchanise'") != 'D') {
            db_query("UPDATE ?:addons SET status = 'D' WHERE addon = 'searchanise'");
            fn_set_notification('W', __('warning'), General::getUnavailableLangVar('uc_searchanise_disabled'));
        }

        General::setActualLangValues();
        General::updateAltLanguages('language_values', 'name');
        General::updateAltLanguages('ult_language_values', array('name', 'company_id'));
        General::updateAltLanguages('settings_descriptions', array('object_id', 'object_type'));

        General::setEmptyProgressBar();
        General::setEmptyProgressBar();
        General::setEmptyProgressBar();
        General::setEmptyProgressBar();
        return true;
    }

    protected function coreLogosLinkedToStyles()
    {
        db_query("ALTER TABLE ?:logos ADD `style_id` varchar(50) NOT NULL DEFAULT ''");

        $logos = db_get_array("SELECT * FROM ?:logos");
        foreach ($logos as $logo) {
            if ($logo['layout_id'] == 0) {
                continue;
            }
            $style = db_get_row("SELECT style_id FROM ?:bm_layouts WHERE layout_id = " . $logo['layout_id'] . ' AND company_id = ' . $logo['company_id']);
            db_query("UPDATE ?:logos SET style_id = '" . $style['style_id'] . "' WHERE logo_id = " . $logo['logo_id']);
        }
    }

    protected function _removeCheckoutStyleSetting()
    {
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'V' AND object_id IN (SELECT variant_id FROM ?:settings_variants WHERE object_id IN (SELECT object_id FROM ?:settings_objects WHERE name = 'checkout_style'))");
        db_query("DELETE FROM ?:settings_variants WHERE object_id = (SELECT object_id FROM ?:settings_objects WHERE name = 'checkout_style')");
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = (SELECT object_id FROM ?:settings_objects WHERE name = 'checkout_style')");
        db_query("DELETE FROM ?:settings_vendor_values WHERE object_id = (SELECT object_id FROM ?:settings_objects WHERE name = 'checkout_style')");
        db_query("DELETE FROM ?:settings_objects WHERE name = 'checkout_style'");
    }

    protected function _removeUltNullTextFields()
    {
        db_query("ALTER TABLE ?:ult_status_descriptions CHANGE `email_header` `email_header` text");
    }

    protected function _addNewFieldToMenu()
    {
        db_query("ALTER TABLE ?:static_data ADD `class` varchar(128) NOT NULL default ''");
    }

    protected function _updatePayPalPayments()
    {
        $pp_advanced_payments = db_get_array("SELECT payment_id,processor_params FROM ?:payments WHERE processor_id IN (SELECT processor_id FROM ?:payment_processors WHERE processor_script = 'paypal_advanced.php')");
        if (!empty($pp_advanced_payments)) {
            foreach ($pp_advanced_payments as $pp_advanced_payment) {
                $pp_advanced_payment_id = $pp_advanced_payment['payment_id'];
                $pp_advanced_payment_data = unserialize($pp_advanced_payment['processor_params']);
                $pp_advanced_payment_data['currency'] = 'USD';
                $pp_advanced_payment_data = serialize($pp_advanced_payment_data);
                db_query("UPDATE ?:payments SET processor_params = '" . addslashes($pp_advanced_payment_data) . "' WHERE payment_id = $pp_advanced_payment_id");
            }
        }

        db_query("UPDATE ?:payment_processors SET `addon` = 'paypal' WHERE `processor_script` IN ('paypal_pro.php', 'paypal.php', 'payflow_pro.php', 'paypal_express.php', 'paypal_advanced.php')");
    }

    protected function _renameCategoryTableFields()
    {
        $sql = array(
            "ALTER TABLE ?:categories CHANGE `selected_layouts` `selected_views` TEXT  CHARACTER SET utf8  COLLATE utf8_general_ci  NOT NULL;",
            "ALTER TABLE ?:categories CHANGE `default_layout` `default_view` VARCHAR(50)  CHARACTER SET utf8  COLLATE utf8_general_ci NOT NULL  DEFAULT '';",
            "ALTER TABLE ?:categories CHANGE `product_details_layout` `product_details_view` VARCHAR(50)  CHARACTER SET utf8  COLLATE utf8_general_ci  NOT NULL  DEFAULT '';"
        );

        foreach ($sql as $query) {
            db_query($query);
        }
    }

    protected function _newFieldInProcessor()
    {
        db_query("ALTER TABLE ?:payment_processors ADD `addon` varchar(32) NULL default NULL");
    }
/*
    protected function _addNewSettingTimeframe()
    {
        db_query("INSERT INTO ?:settings_objects (`edition_type`, `name`, `section_id`, `section_tab_id`, `type`, `value`, `position`, `is_global`) VALUES ('ROOT,ULT:VENDOR', 'dashboard_timeframe', 0, 0, 'I', '', 0, 'Y')");
    }
 */
    protected function _convertImageVerification()
    {
        $setting = array(
            'object_id' => 138,
            'name' => 'use_for',
            'type' => 'N',
            'description' => 'Use for',
            'edition_type' => 'ROOT,ULT:VENDOR',
            'position' => 5,
        );

        $old_settings = array(
            array(
                'object_id' => 215,
                'name' => 'header_8048',
                'description' => 'Use for',
                'position' => 90,
                'type' => 'H',
                'is_global' => 'N',
                'edition_type' => 'ROOT,VENDOR',
            ),
            array(
                'object_id' => 127,
                'name' => 'use_for_login',
                'description' => 'Login form',
                'position' => 100,
            ),
            array(
                'object_id' => 128,
                'name' => 'use_for_register',
                'description' => 'Create and edit profile form',
                'position' => 110,
            ),
            array(
                'object_id' => 129,
                'name' => 'use_for_form_builder',
                'description' => 'Custom forms',
                'position' => 120,
            ),
            array(
                'object_id' => 130,
                'name' => 'use_for_email_share',
                'description' => 'Send to friend form',
                'position' => 130,
            ),
            array(
                'object_id' => 131,
                'name' => 'use_for_discussion',
                'description' => 'Comments and reviews forms',
                'position' => 140,
            ),
            array(
                'object_id' => 132,
                'name' => 'use_for_checkout',
                'description' => 'Checkout (user information) form',
                'position' => 150,
            ),
            array(
                'object_id' => 135,
                'name' => 'use_for_polls',
                'description' => 'Polls',
                'position' => 160,
            ),
            array(
                'object_id' => 136,
                'name' => 'use_for_track_orders',
                'description' => 'Track my order form',
                'position' => 170,
            ),
        );

        $section_id = $this->getSectionId();

        $use_for_settings_names = array();
        foreach ($old_settings as $_setting) {
            $use_for_settings_names[] = $_setting['name'];
        }
        $use_for_settings = db_get_array(
            "SELECT * FROM ?:settings_objects"
            . " WHERE section_id = '$section_id' AND name IN('" . implode("','", $use_for_settings_names) . "')"
        );
        $use_for_values = array();
        $use_for_ids = array();
        foreach ($use_for_settings as &$_setting) {
            $use_for_ids[] = $_setting['object_id'];
            if (!empty($_setting['type']) && $_setting['type'] == 'H') { // is header
                continue;
            }
            $_setting['new_name'] = substr($_setting['name'], 8);
            $use_for_values[] = $_setting['new_name'] . '=' . $_setting['value'];
        }
        $use_for_vendor_values_data = db_get_array("SELECT * FROM ?:settings_vendor_values WHERE object_id IN(" . implode(',', $use_for_ids) . ")");
        $use_for_vendor_values = array();
        $use_for_vendor_values_temp = array();
        foreach ($use_for_vendor_values_data as $_value) {
            $use_for_vendor_values_temp[$_value['company_id']][$_value['object_id']] = $_value['value'];
        }
        foreach ($use_for_vendor_values_temp as $company_id => $values) {
            foreach ($values as $_object_id => $_object_value) {
                foreach ($use_for_settings as &$_setting) {
                    if ($_object_id == $_setting['object_id']) {
                        $use_for_vendor_values[$company_id][$_object_id] = $_setting['new_name'] . '=' . $_object_value;
                    }
                }
            }
        }
        $setting_value[0] = '#M#' . implode('&', $use_for_values);
        foreach ($use_for_vendor_values as $company_id => $company_values) {
            $setting_value[$company_id] = '#M#' . implode('&', $company_values);
        }

        $setting_exists = db_get_row("SELECT * FROM ?:settings_objects WHERE object_id = " . $setting['object_id']);
        $object_id = $setting_exists ? 'null' : $setting['object_id'];

        db_query(
            "INSERT INTO ?:settings_objects (object_id, name, edition_type, section_id, type, value, position, is_global) VALUES ($object_id, '"
            . implode("', '", array($setting['name'], $setting['edition_type'], $section_id, $setting['type'], $setting_value[0], $setting['position'], 'Y'))
            . "')"
        );
        $_setting = db_get_row("SELECT * FROM ?:settings_objects WHERE name = '" . $setting['name'] . "' AND section_id = " . $section_id);
        $object_id = $_setting['object_id'];

        foreach ($setting_value as $company_id => $values) {
            if (!empty($company_id)) {
                db_query(
                    "INSERT INTO ?:settings_vendor_values (object_id, company_id, value) VALUES ('"
                    . implode("', '", array($object_id, $company_id, $values))
                    . "')"
                );
            }
        }

        foreach ($this->getLangCodes() as $lang_code) {
            db_query("
                REPLACE INTO ?:settings_descriptions (object_id, object_type, lang_code, value, tooltip)
                VALUES (" . $object_id . ", 'O', '" . $lang_code . "', '" . $setting['description'] . "', '');
            ");
        }

        $use_for_ids_str = implode(',', $use_for_ids);
        db_query("DELETE FROM ?:settings_objects WHERE object_id IN(" . $use_for_ids_str . ")");
        db_query("DELETE FROM ?:settings_descriptions WHERE object_id IN(" . $use_for_ids_str . ") AND object_type = 'O'");
        db_query("DELETE FROM ?:settings_vendor_values WHERE object_id IN(" . $use_for_ids_str . ")");
    }

    protected function getSectionId()
    {
        $section = db_get_row("SELECT * FROM ?:settings_sections WHERE name = 'Image_verification' AND type = 'CORE'");

        if ($section) {
            return $section['section_id'];
        }

        return false;
    }

    protected function getLangCodes()
    {
        $languages = db_get_array("SELECT lang_code FROM ?:languages");
        $codes = array();
        foreach ($languages as $lang) {
            $codes[] = $lang['lang_code'];
        }

        return $codes;
    }

    protected function _removeShippingSettings()
    {
        $section_shippings = db_get_row("SELECT section_id FROM ?:settings_sections WHERE name = 'Shippings' and type = 'CORE'");
        $section_general = db_get_row("SELECT section_id FROM ?:settings_sections WHERE name = 'General' and type = 'CORE'");

        if (!empty($section_shippings)) {
            db_query("UPDATE ?:settings_objects SET section_id = " . $section_general['section_id'] . ", position = 305 WHERE name = 'disable_shipping'");

            $options = db_get_array("SELECT object_id FROM ?:settings_objects WHERE section_id = " . $section_shippings['section_id']);
            $option_ids = array();
            foreach ($options as $option) {
                $option_ids[] = $option['object_id'];
            }
            db_query("DELETE FROM ?:settings_objects WHERE object_id IN (" . implode(',', $option_ids) . ")");
            db_query("DELETE FROM ?:settings_vendor_values WHERE object_id IN (" . implode(',', $option_ids) . ")");
            db_query("DELETE FROM ?:settings_descriptions WHERE object_id IN (" . implode(',', $option_ids) . ") AND object_type = 'O'");

            db_query("DELETE FROM ?:settings_sections WHERE section_id = " . $section_shippings['section_id']);
            db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'S' AND object_id = " . $section_shippings['section_id']);
        }
    }

    protected function _bmNewLinkField()
    {
        db_query("ALTER TABLE ?:bm_containers ADD `linked_to_default` varchar(1) NOT NULL DEFAULT 'Y' AFTER `user_class`");
    }

    protected function _edpProducts()
    {
        $value = 'N';
        $files = db_get_field("SELECT COUNT(*) as total FROM ?:product_files");
        if (!empty($files['total'])) {
            $value = 'Y';
        }

        db_query("INSERT INTO ?:settings_objects (edition_type, name, section_id, type, value, position, is_global) VALUES ('ROOT,ULT:VENDOR', 'enable_edp', 2, 'C', '$value', 135, 'Y')");
    }
}
