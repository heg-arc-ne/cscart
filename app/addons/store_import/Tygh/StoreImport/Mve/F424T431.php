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

namespace Tygh\StoreImport\Mve;
use Tygh\StoreImport\General;
use Tygh\Registry;
use Tygh\Addons\SchemesManager as AddonSchemesManager;

class F424T431
{
    protected $store_data = array();
    protected $main_sql_filename = 'mve_F424T431.sql';

    public function __construct($store_data)
    {
        $store_data['product_edition'] = 'MULTIVENDOR';
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
        $this->_addNewLocation();
        $this->_removeShippingSettings();
        $this->_convertImageVerification();
        $this->_mveConvertImageVerification();
//        $this->_addNewSettingTimeframe();
        $this->_newFieldInProcessor();
        $this->_renameCategoryTableFields();
        $this->_updatePayPalPayments();
        $this->_addNewFieldToMenu();
        General::setCustomCssClassForResponsive();
        General::addCheckoutSection();
        General::coreCheckoutSettings();
        General::removeNullTextFields();
        General::filtersRefactoring();
        $this->_mveFiltersRefactoring();
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

        db_query("ALTER TABLE ?:vendor_payouts CHANGE `comments` `comments` text");
        db_query("UPDATE ?:usergroup_privileges SET privilege = 'backup_restore' WHERE privilege = 'database_maintenance'");
        db_query("UPDATE ?:privileges SET privilege = 'backup_restore' WHERE privilege = 'database_maintenance'");

//        General::restoreSettings();
        if (db_get_field("SELECT status FROM ?:addons WHERE addon = 'searchanise'") != 'D') {
            db_query("UPDATE ?:addons SET status = 'D' WHERE addon = 'searchanise'");
            fn_set_notification('W', __('warning'), General::getUnavailableLangVar('uc_searchanise_disabled'));
        }
        General::setActualLangValues();
        General::updateAltLanguages('language_values', 'name');
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
            $style = db_get_row("SELECT style_id FROM ?:bm_layouts WHERE layout_id = " . $logo['layout_id']);
            db_query("UPDATE ?:logos SET style_id = '" . $style['style_id'] . "' WHERE logo_id = " . $logo['logo_id']);
        }
    }

    protected function _removeCheckoutStyleSetting()
    {
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'V' AND object_id IN (SELECT variant_id FROM ?:settings_variants WHERE object_id = (SELECT object_id FROM ?:settings_objects WHERE name = 'checkout_style'))");
        db_query("DELETE FROM ?:settings_variants WHERE object_id = (SELECT object_id FROM ?:settings_objects WHERE name = 'checkout_style')");
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = (SELECT object_id FROM ?:settings_objects WHERE name = 'checkout_style')");
        db_query("DELETE FROM ?:settings_objects WHERE name = 'checkout_style'");
    }

    protected function _mveFiltersRefactoring()
    {
        db_query("UPDATE ?:bm_blocks SET type = 'product_filters' WHERE type = 'vendor_filters'");
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

    protected function _addNewLocation()
    {
        $languages = db_get_array("SELECT * FROM ?:languages");
        $layouts = db_get_array("SELECT * FROM ?:bm_layouts");
        foreach ($layouts as $layout) {
            $layout_id = $layout['layout_id'];

            $is_exists = db_get_row("SELECT * FROM ?:bm_locations WHERE dispatch = 'companies.products' AND layout_id = " . $layout_id);

            if (!empty($is_exists)) {
                continue;
            }

            db_query("INSERT INTO ?:bm_locations (`dispatch`, `layout_id`, `position`) VALUES ('companies.products', " . $layout_id . ", 130)");
            $location = db_get_row("SELECT * FROM ?:bm_locations WHERE dispatch = 'companies.products' AND layout_id = " . $layout_id);

            foreach ($languages as $language) {
                db_query("INSERT INTO ?:bm_locations_descriptions (`lang_code`, `name`, `title`, `meta_description`, `meta_keywords`, `location_id`) VALUES ('" . $language['lang_code'] . "', 'Vendor store', '', '', '', " . $location['location_id'] . ")");
            }

            foreach (array('TOP_PANEL', 'HEADER', 'CONTENT', 'FOOTER') as $position) {
                $use_default = $position == 'FOOTER' ? 'Y' : 'N';
                db_query("INSERT INTO ?:bm_containers (`location_id`, `position`, `width`, `linked_to_default`) VALUES (" . $location['location_id'] . ", '" . $position . "', 16, '" . $use_default . "')");
            }

            $_containers = db_get_array("SELECT * FROM ?:bm_containers WHERE location_id = " . $location['location_id']);
            foreach ($_containers as $container) {
                $containers[$container['position']] = $container;
            }
            unset($_containers);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (8, 'top-links-grid', 'A', 0, 0, 1, '', 'LEFT', 'div', 0, " . $containers['TOP_PANEL']['container_id'] . ", 0)");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('languages', 'a:4:{s:8:\"template\";s:20:\"blocks/languages.tpl\";s:4:\"text\";s:0:\"\";s:6:\"format\";s:4:\"name\";s:14:\"dropdown_limit\";s:1:\"0\";}', 'Languages', '', $grid_id, '', 'top-languages', 2);

            $block_id = $this->createBlock('currencies', 'a:4:{s:8:\"template\";s:21:\"blocks/currencies.tpl\";s:4:\"text\";s:0:\"\";s:6:\"format\";s:6:\"symbol\";s:14:\"dropdown_limit\";s:1:\"3\";}', 'Currencies', '', $grid_id, '', 'top-currencies', 3);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (8, 'top-links-grid', 'A', 0, 1, 0, '', 'RIGHT', 'div', 1, " . $containers['TOP_PANEL']['container_id'] .", 0)");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('my_account', 'a:1:{s:8:\"template\";s:21:\"blocks/my_account.tpl\";}', 'My Account', '', $grid_id, 'blocks/wrappers/onclick_dropdown.tpl', 'top-my-account', 2);

            $block_id = $this->createBlock('menu', 'a:2:{s:8:\"template\";s:26:\"blocks/menu/text_links.tpl\";s:18:\"show_items_in_line\";s:1:\"Y\";}', 'Quick links', 'a:1:{s:4:\"menu\";s:1:\"1\";}', $grid_id, '', 'top-quick-links hidden-phone hidden-tablet', 3);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (5, 'top-logo-grid', 'A', 0, 0, 1, '', 'FULL_WIDTH', 'div', 0, " . $containers['HEADER']['container_id'] . ", 0)");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('template', 'a:1:{s:8:\"template\";s:32:\"blocks/static_templates/logo.tpl\";}', 'Logo', '', $grid_id, '', 'top-logo', 2);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (6, 'search-block-grid', 'A', 0, 0, 0, '', 'FULL_WIDTH', 'div', 0, " . $containers['HEADER']['container_id'] . ", 0)");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('smarty_block', 'a:2:{s:8:\"template\";s:23:\"blocks/smarty_block.tpl\";s:1:\"u\";i:1;}', 'Stores', 'a:1:{s:7:\"content\";s:24:\"<h2> {__(\"stores\")}</h2>\";}', $grid_id, '', '', 0);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (5, 'cart-content-grid', 'A', 0, 1, 0, '', 'RIGHT', 'div', 1, " . $containers['HEADER']['container_id'] . ", 0)");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('cart_content', 'a:5:{s:8:\"template\";s:23:\"blocks/cart_content.tpl\";s:22:\"display_bottom_buttons\";s:1:\"Y\";s:20:\"display_delete_icons\";s:1:\"Y\";s:19:\"products_links_type\";s:5:\"thumb\";s:20:\"generate_block_title\";s:1:\"Y\";}', 'Cart content', '', $grid_id, '', 'top-cart-content', 2);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (16, 'vendor-info-grid', 'A', 0, 1, 1, '', 'FULL_WIDTH', 'div', 1, " . $containers['HEADER']['container_id'] . ", 0)");
            $parent_id = $this->getLastId();

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (4, 'vendor-logo-grid', 'A', 0, 0, 1, '', 'FULL_WIDTH', 'div', 0, " . $containers['HEADER']['container_id'] . ", {$parent_id})");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('vendor_logo', 'a:1:{s:8:\"template\";s:30:\"blocks/vendors/vendor_logo.tpl\";}', 'Vendor logo', '', $grid_id, '', '', 0);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (6, 'vendor-search-grid', 'A', 0, 0, 0, '', 'FULL_WIDTH', 'div', 0, " . $containers['HEADER']['container_id'] . ", {$parent_id})");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('vendor_search', 'a:1:{s:8:\"template\";s:32:\"blocks/vendors/vendor_search.tpl\";}', 'Vendor search', '', $grid_id, '', '', 2);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (6, 'vendor-information-grid', 'A', 0, 1, 0, '', 'FULL_WIDTH', 'div', 1, " . $containers['HEADER']['container_id'] . ", {$parent_id})");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('vendor_information', 'a:1:{s:8:\"template\";s:37:\"blocks/vendors/vendor_information.tpl\";}', 'Vendor information', '', $grid_id, '', '', 2);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (16, 'breadcrumbs-grid', 'A', 0, 1, 1, '', 'FULL_WIDTH', 'div', 1, " . $containers['CONTENT']['container_id'] . ", 0)");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('breadcrumbs', 'a:1:{s:8:\"template\";s:22:\"common/breadcrumbs.tpl\";}', 'Breadcrumbs', '', $grid_id, '', '', 2);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (4, 'side-grid', 'A', 0, 0, 1, '', 'FULL_WIDTH', 'div', 0, " . $containers['CONTENT']['container_id'] . ", 0)");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('vendor_categories', 'a:2:{s:8:\"template\";s:50:\"blocks/categories/categories_dropdown_vertical.tpl\";s:25:\"right_to_left_orientation\";s:1:\"N\";}', 'Vendor categories', 'a:1:{s:5:\"items\";a:3:{s:7:\"filling\";s:13:\"full_tree_cat\";s:18:\"parent_category_id\";s:0:\"\";s:7:\"sort_by\";s:8:\"position\";}}', $grid_id, 'blocks/wrappers/sidebox_general.tpl', '', 0);

            $block_id = $this->createBlock('vendor_filters', 'a:1:{s:8:\"template\";s:35:\"blocks/product_filters/original.tpl\";}', 'Vendor filters', 'a:1:{s:5:\"items\";a:1:{s:7:\"filling\";s:8:\"manually\";}}', $grid_id, 'blocks/wrappers/sidebox_general.tpl', '', 2);

            db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (12, 'main-content-grid', 'A', 0, 1, 0, '', 'FULL_WIDTH', 'div', 1, " . $containers['CONTENT']['container_id'] . ", 0)");
            $grid_id = $this->getLastId();

            $block_id = $this->createBlock('main', '', 'Main Content', '', $grid_id, 'blocks/wrappers/mainbox_general.tpl', '', 0);
        }
    }

    protected function isBlockExists($type, $properties)
    {
        $block = db_get_row("SELECT * FROM ?:bm_blocks WHERE type = '" . $type . "' AND properties = '" . $properties . "'");

        if (!empty($block['block_id'])) {
            return $block['block_id'];
        } else {
            return false;
        }
    }

    protected function getLastId()
    {
        $id = db_get_row('SELECT LAST_INSERT_ID() AS IID');
        $id = $id['IID'];

        return $id;
    }

    protected function createBlock($type, $properties, $name, $content, $grid_id, $wrapper, $user_class, $order)
    {
        $languages = db_get_array("SELECT * FROM ?:languages");

        $block_id = $this->isBlockExists($type, $properties);
        if (empty($block_id)) {
            db_query("INSERT INTO ?:bm_blocks (`type`, `properties`) VALUES ('{$type}', '{$properties}')");
            $block_id = $this->getLastId();

            foreach ($languages as $language) {
                db_query("INSERT INTO ?:bm_blocks_descriptions (`lang_code`, `name`, `block_id`) VALUES ('" . $language['lang_code'] . "', '{$name}', {$block_id})");
                db_query("INSERT INTO ?:bm_blocks_content (`content`, `lang_code`, `block_id`) VALUES ('{$content}', '" . $language['lang_code'] . "', {$block_id})");
            }
        }
        db_query("INSERT INTO ?:bm_snapping (`block_id`, `grid_id`, `wrapper`, `user_class`, `order`, `status`) VALUES ({$block_id}, {$grid_id}, '{$wrapper}', '{$user_class}', {$order}, 'A')");

        return $block_id;
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

        $setting = $setting;

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

    protected function _mveConvertImageVerification()
    {
        $setting = array(
            'object_id' => 1003,
            'edition_type' => 'ROOT',
            'name' => 'use_for_apply_for_vendor_account',
            'description' => 'Apply for a vendor account form',
            'position' => 180,
        );

        $section_id = $this->getSectionId();

        $multi_setting = db_get_row(sprintf(
            "SELECT * FROM ?:settings_objects WHERE name = '%s' AND section_id = %s",
            'use_for', $section_id
        ));

        $_setting = db_get_row(sprintf(
            "SELECT * FROM ?:settings_objects WHERE name = '%s' AND section_id = %s",
            $setting['name'], $section_id
        ));

        if ($_setting['value'] == 'Y') {
            parse_str(str_replace('#M#', '', $multi_setting['value']), $mvalues);
            $key = substr($setting['name'], 8);
            $mvalues[$key] = $_setting['value'];
            $mvalues_str = array();
            foreach ($mvalues as $_mkey => $_mvalue) {
                $mvalues_str[] = $_mkey . '=' . $_mvalue;
            }
            $mvalue = '#M#' . implode('&', $mvalues_str);
            db_query(sprintf("UPDATE ?:settings_objects SET value = '%s' WHERE object_id = %s",
                $mvalue, $multi_setting['object_id']
            ));
        }

        db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $_setting['object_id']);
        db_query("DELETE FROM ?:settings_descriptions WHERE object_id = " . $_setting['object_id'] . " AND object_type = 'O'");
        db_query("DELETE FROM ?:settings_vendor_values WHERE object_id = " . $_setting['object_id']);
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
