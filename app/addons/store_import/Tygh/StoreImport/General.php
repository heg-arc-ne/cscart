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
namespace Tygh\StoreImport;

use Tygh\Exceptions\StoreImportException;
use Tygh\Registry;
use Tygh\Settings;
use Tygh\Addons\SchemesManager as AddonSchemesManager;
use Tygh\Less;
use Tygh\BlockManager\ProductTabs;
use Tygh\Helpdesk;
use Tygh\BlockManager\Layout;
use Tygh\Themes\Styles;
use Tygh\Addons\SchemesManager;
use Tygh\Themes\Themes;
use Tygh\Languages\Languages;

class General
{
    const CONNECTION_NAME = 'exim_stores';
    const TABLE_PREFIX = 'store_import';
    const VERSION_FOR_LICENSE_CHECK = 4;

    private static $unavailable_lang_vars = array();
    private static $supplier_settings = array();
    public static $default_language = 'en';

    public static function initiateImportedDB($store_data)
    {
        $db_conn = db_initiate(
            $store_data['db_host'],
            $store_data['db_user'],
            $store_data['db_password'],
            $store_data['db_name'],
            array(
                'dbc_name' => General::CONNECTION_NAME,
                'table_prefix' => $store_data['table_prefix']
            )
        );

        return $db_conn;
    }

    /**
     * Connects to database of imported cart
     *
     * @static
     * @return bool True on success, false otherwise
     */
    public static function connectToImportedDB($store_data)
    {
        if (db_connect_to(array('dbc_name' => General::CONNECTION_NAME), $store_data['db_name'])) {
            return true;
        } else {
            //We should break the process because another way imported DB can be broken
            throw new StoreImportException(self::getUnavailableLangVar('cant_connect_to_imported'));
        }
    }

    /**
     * Connects to original database
     *
     * @static
     * @return bool True on success, false otherwise
     */
    public static function connectToOriginalDB($params = array())
    {
        if (db_connect_to($params, Registry::get('config.db_name'))) {
            return true;
        } else {
            throw new StoreImportException(self::getUnavailableLangVar('cant_connect_to_original'));
        }
    }

    /**
     * Checks database connection
     *
     * @static
     * @param  array $store_data Array of store data
     * @return bool  True on success, false otherwise
     */
    public static function testDatabaseConnection($store_data)
    {
        $status = false;

        if (!empty($store_data['db_host']) && !empty($store_data['db_user']) && !empty($store_data['db_name'])) {
            $new_db = @General::initiateImportedDB($store_data);

            if ($new_db != null) {
                $status = true;
            }
        }

        General::connectToOriginalDB();

        return $status;
    }

    /**
     * Checks that table prefix correct
     *
     * @static
     * @param  array $store_data Array of store data
     * @return bool  True on success, false otherwise
     */
    public static function testTablePrefix($store_data)
    {
        $status = false;
        General::connectToImportedDB($store_data);

        $tables = db_get_array("SHOW TABLES LIKE '" . $store_data['table_prefix'] . "sessions';");

        if (!empty($tables)) {
            $status = true;
        }

        General::connectToOriginalDB();

        return $status;
    }

    /**
     * Returns config of cart placed by $cart_path
     *
     * @static
     * @param  string     $cart_path
     * @return array|bool Array of store data on success, false otherwise.
     */
    public static function getConfig($cart_path)
    {
        $cart_path = rtrim($cart_path, '/');
        if (file_exists($cart_path . '/config.local.php') && file_exists($cart_path . '/config.php')) {

            // Read settings from config.local.php
            $config_local_php = file_get_contents($cart_path . '/config.local.php');
            $config_local_php =  self::_removePhpComments($config_local_php);

            $config['db_host'] = self::_getVariable('db_host', $config_local_php);
            $config['db_name'] = self::_getVariable('db_name', $config_local_php);
            $config['db_user'] = self::_getVariable('db_user', $config_local_php);
            $config['db_password'] = self::_getVariable('db_password', $config_local_php);
            $config['table_prefix'] = self::_getConstant('TABLE_PREFIX', $config_local_php);
            if (empty($config['table_prefix'])) {
                $config['table_prefix'] = self::_getVariable('table_prefix', $config_local_php);
            }
            $config['storefront'] = self::_getVariable('http_host', $config_local_php) . self::_getVariable('http_path', $config_local_php);
            $config['secure_storefront'] = self::_getVariable('https_host', $config_local_php) . self::_getVariable('https_path', $config_local_php);

            $config['crypt_key'] = self::_getVariable('crypt_key', $config_local_php);
            $config['admin_index'] = $config['storefront'] . '/' . self::_getVariable('admin_index', $config_local_php);

            //Read settings from config.php
            $config_php = file_get_contents($cart_path . '/config.php');
            $config_php =  self::_removePhpComments($config_php);
            $config['product_edition'] = self::_getConstant('PRODUCT_EDITION', $config_php);
            if (empty($config['product_edition'])) { // workaround for all versions, where edition was stored in PRODUCT_TYPE const
                $config['product_edition'] = self::_getConstant('PRODUCT_TYPE', $config_php);
            }
            $config['product_version'] = self::_getConstant('PRODUCT_VERSION', $config_php);
            $config['product_name'] = self::_getConstant('PRODUCT_NAME', $config_php);

            return $config;
        } else {
            return false;
        }
    }

    /**
     * Removes PHP comments
     *
     * @param  string $code PHP code
     * @return string PHP code without comments
     */
    private static function _removePhpComments($code)
    {
        return preg_replace("%//.*?\n%is", '', preg_replace("%/\*(.*?)\*/\s+%is", '', preg_replace("%#.*?\n%is", '', $code)));
    }

    /**
     * Returns value of some variable from config
     *
     * @param  string $var_name Variable name
     * @param  string $config   config contents
     * @return string Variable value
     */
    private static function _getVariable($var_name, $config)
    {
        preg_match("%config\s*?\[\s*?['\"]" . $var_name . "['\"]\s*?\]\s*?=\s*?['\"](.*?)['\"]\s*?;%is", $config, $value);

        return !empty($value[1]) ? $value[1] : "";
    }

    /**
     * Returns value of some defined constant from config
     *
     * @param  string $var_name Variable name
     * @param  string $config   config contents
     * @return string Variable value
     */
    private static function _getConstant($var_name, $config)
    {
        preg_match("%define\s*?\(\s*?['\"]" . $var_name . "['\"]\s*?,\s*?['\"](.*?)['\"]\s*?\)\s*?;%is", $config, $value);

        return !empty($value[1]) ? $value[1] : "";
    }

    private static function _getImportClassName($str, $edition)
    {
        return 'Tygh\\StoreImport\\' . str_replace('_', 'T', $str);
    }

    public static function getImportSchema($store_data)
    {
        $import_schema = fn_get_schema('store_import', 'store_import');
        $product_version = self::_getVersionName($store_data['product_version']);
        $product_edition = strtolower(fn_get_edition_acronym($store_data['product_edition']));
        $import_schema = $import_schema[$product_edition];

        if (!empty($import_schema)) {
            foreach ($import_schema as $key => $value) {
                if (stripos($value, $product_version) === 5) {
                    break;
                } else {
                    unset($import_schema[$key]);
                }
            }
        }

        return array_values($import_schema);
    }

    public static function getImportClassesCascade($store_data)
    {
        $import_schema = self::getImportSchema($store_data);

        if (!empty($import_schema)) {
            $return = array();
            $edition = fn_get_edition_acronym($store_data['product_edition']);
            foreach ($import_schema as $value) {
                $return[] = self::_getImportClassName($value, $edition);
            }

            return $return;
        }

        return null;
    }

    private static function _getVersionName($product_version)
    {
        return str_replace('.', '', $product_version);
    }

    public static function getCompanies($store_data)
    {
        General::connectToImportedDB($store_data);

        $companies = db_get_array("SELECT * FROM ?:companies");

        return $companies;
    }

    public static function cloneImportedDB($store_data)
    {
        fn_set_progress('title', __('store_import.cloning_database'));
        fn_define('DB_MAX_ROW_SIZE', 10000);
        fn_define('DB_ROWS_PER_PASS', 40);

        General::connectToImportedDB($store_data);
        $tables = General::getTables($store_data['db_name'], $store_data['table_prefix']);
        $excluded_tables = array(
            $store_data['table_prefix'] . 'logs',
            $store_data['table_prefix'] . 'sessions',
            $store_data['table_prefix'] . 'stored_sessions',
            $store_data['table_prefix'] . 'user_session_products',
            $store_data['table_prefix'] . 'stat_browsers',
            $store_data['table_prefix'] . 'stat_ips',
            $store_data['table_prefix'] . 'stat_languages',
            $store_data['table_prefix'] . 'stat_product_search',
            $store_data['table_prefix'] . 'stat_requests',
            $store_data['table_prefix'] . 'stat_search_engines',
            $store_data['table_prefix'] . 'stat_search_phrases',
            $store_data['table_prefix'] . 'stat_sessions',
            $store_data['table_prefix'] . 'stat_banners_log',
        );
        $tables = array_diff($tables, $excluded_tables);
        $change_table_prefixes = array(
            'from' => $store_data['table_prefix'],
            'to' => self::formatPrefix(),
        );
        db_export_to_file(Registry::get('config.dir.database') . 'export.sql', $tables, true, true, false, true, true, $change_table_prefixes);

        General::connectToOriginalDB();
        self::_createExcludedTables($change_table_prefixes['to']);

        return db_import_sql_file(Registry::get('config.dir.database') . 'export.sql', 16384, true, true, false, false, false, true);
    }

    private static function _createExcludedTables($prefix)
    {
        db_query("
            CREATE TABLE `" . $prefix . "logs` (
                `log_id` mediumint(8) unsigned NOT NULL auto_increment,
                `user_id` mediumint(8) unsigned NOT NULL default '0',
                `timestamp` int(11) unsigned NOT NULL default '0',
                `type` varchar(16) NOT NULL default '',
                `event_type` char(1) NOT NULL default 'N',
                `action` varchar(16) NOT NULL default '',
                `object` char(1) NOT NULL default '',
                `content` text NOT NULL,
                `backtrace` text NOT NULL,
                `company_id` int(11) unsigned NOT NULL default '0',
                PRIMARY KEY  (`log_id`),
                KEY `object` (`object`),
                KEY (`type`, `action`)
            ) ENGINE=MyISAM DEFAULT CHARSET UTF8;
        ");

        db_query("
            CREATE TABLE `" . $prefix . "sessions` (
                `session_id` varchar(255) NOT NULL default '',
                `expiry` int(11) unsigned NOT NULL default '0',
                `data` mediumtext,
                PRIMARY KEY  (`session_id`),
                KEY `src` (`session_id`,`expiry`),
                KEY (`expiry`)
            ) Engine=MyISAM DEFAULT CHARSET UTF8;
        ");

        db_query("
            CREATE TABLE `" . $prefix . "stored_sessions` (
               `session_id` varchar(34) NOT NULL,
               `expiry` int(11) unsigned NOT NULL,
               `data` text NOT NULL,
               PRIMARY KEY  (`session_id`),
               KEY (`expiry`)
            ) Engine=MyISAM DEFAULT CHARSET UTF8;
        ");

        db_query("
            CREATE TABLE `" . $prefix . "user_session_products` (
                `user_id` int(11) unsigned NOT NULL default '0',
                `timestamp` int(11) unsigned NOT NULL default '0',
                `type` char(1) NOT NULL default 'C',
                `user_type` char(1) NOT NULL default 'R',
                `item_id` int(11) unsigned NOT NULL default '0',
                `item_type` char(1) NOT NULL default 'P',
                `product_id` mediumint(8) unsigned NOT NULL default '0',
                `amount` mediumint(8) unsigned NOT NULL default '1',
                `price` decimal(12,2) NOT NULL default '0.00',
                `extra` text NOT NULL,
                `session_id` varchar(34) NOT NULL default '',
                `ip_address` varchar(15) NOT NULL default '',
            PRIMARY KEY  (`user_id`,`type`,`item_id`,`user_type`),
            KEY `timestamp` (`timestamp`,`user_type`),
            KEY `session_id` (`session_id`)
            ) Engine=MyISAM DEFAULT CHARSET UTF8;
        ");

        if (fn_allowed_for('ULTIMATE')) {
            db_query("ALTER TABLE " . $prefix . "user_session_products ADD `company_id` int(11) unsigned NOT NULL");
            db_query("ALTER TABLE " . $prefix . "user_session_products DROP PRIMARY KEY");
            db_query("ALTER TABLE " . $prefix . "user_session_products ADD PRIMARY KEY(`user_id`, `type`, `user_type`, `item_id`, `company_id`)");
        }

        return true;
    }

    public static function formatPrefix()
    {
        return General::TABLE_PREFIX . '_';
    }

    public static function getTables($db_name, $db_prefix)
    {
        $tables = db_get_fields(
            'SELECT TABLE_NAME AS stmt
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = "' . $db_name . '"
            AND TABLE_NAME LIKE "' . $db_prefix . '%"
        ');

        return $tables;
    }

    private static function _replacePrefix($str, $table_prefix)
    {
        $new_prefix = self::formatPrefix();

        return str_replace($table_prefix, $new_prefix, $str);
    }

    public static function getFileName($store_data, $class, $prefix = '', $postfix = '')
    {
        $edition = fn_get_edition_acronym($store_data['product_edition']);
        $class_name = substr(strrchr($class, "\\"), 1);

        return ($prefix ? $prefix . '_' : '') . $edition . '_' . strtoupper($class_name) . ($postfix ? '_' . $postfix : '');
    }

    public static function getLangCodes()
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));

        return db_get_fields("SELECT lang_code FROM ?:languages");
    }

    public static function updateAltLanguages($table, $keys, $show_process = false)
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        $langs = self::getLangCodes();

        if (!is_array($keys)) {
            $keys = array($keys);
        }

        $i = 0;
        $step = 50;
        while ($items = db_get_array("SELECT * FROM ?:$table WHERE lang_code = ?s LIMIT $i, $step", self::$default_language)) {
            $i += $step;
            foreach ($items as $v) {
                foreach ($langs as $lang) {
                    $condition = array();
                    foreach ($keys as $key) {
                        $lang_var = $v[$key];
                        $condition[] = db_quote("$key = ?s", $lang_var);
                    }
                    $condition = implode(' AND ', $condition);
                    $exists = db_get_field("SELECT COUNT(*) FROM ?:$table WHERE $condition AND lang_code = ?s", $lang);
                    if (empty($exists)) {
                        $v['lang_code'] = $lang;
                        db_query("REPLACE INTO ?:$table ?e", $v);
                        if ($show_process) {
                            fn_echo(' .');
                        }
                    }
                }
            }
        }

        return true;
    }

    public static function uninstallAddons($addons = array())
    {
        if (!empty($addons)) {
            if (!is_array($addons)) {
                $addons = (array) $addons;
            }
            General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));

            db_query("DELETE FROM ?:addons WHERE addon IN (?a)", $addons);
            db_query("DELETE FROM ?:addon_descriptions WHERE addon IN (?a)", $addons);
            self::_removeAddonsSettings($addons);
        }

        return true;
    }

    private static function _removeAddonsSettings($addons)
    {
        $table_fields = fn_get_table_fields('addons');
        if (!isset($table_fields['options'])) {
            $addons_objects = db_get_fields("SELECT object_id FROM ?:settings_objects WHERE section_id IN (SELECT section_id FROM ?:settings_sections WHERE name IN (?a))", $addons);
            if (!empty($addons_objects)) {
                db_query("DELETE FROM ?:settings_descriptions WHERE object_id IN (?a) AND object_type = 'O'", $addons_objects);
                db_query("DELETE FROM ?:settings_variants WHERE object_id IN (?a)", $addons_objects);
                if (db_get_array("SHOW TABLES LIKE '?:settings_vendor_values'")) {
                    db_query("DELETE FROM ?:settings_vendor_values WHERE object_id IN (?a)", $addons_objects);
                }
            }
        }

        return true;
    }

    public static function getInstalledAddons()
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        $addons = db_get_fields("SELECT addon FROM ?:addons");
        if (in_array('news_and_emails', $addons)) {
            unset($addons[array_search('news_and_emails', $addons)]);
            $addons[] = 'newsletters';
        }

        return $addons;
    }

    public static function backupSettings()
    {
        $was_settings_backed_up = db_get_field("SHOW TABLES LIKE '?:settings_objects_upg'");
        if (empty($was_settings_backed_up)) {
            if (fn_allowed_for('ULTIMATE')) {
                db_import_sql_file(Registry::get('config.dir.addons') . 'store_import/database/ult_backup_settings.sql');
            } else {
                db_import_sql_file(Registry::get('config.dir.addons') . 'store_import/database/mve_backup_settings.sql');
            }
        }
    }

    public static function restoreSettings()
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        $was_settings_backed_up = db_get_field("SHOW TABLES LIKE '?:settings_objects_upg'");
        //Should be here because we use default functions to restore addon settings to upgrade old versions.
        db_query("CREATE TABLE IF NOT EXISTS ?:original_values (`msgctxt` varchar(128) NOT NULL DEFAULT '',  `msgid` text,  PRIMARY KEY (`msgctxt`)) ENGINE=MyISAM DEFAULT CHARSET=utf8");

        if (!empty($was_settings_backed_up)) {
            $addons = self::getInstalledAddons();

            foreach ($addons as $addon) {
                $addon_scheme = AddonSchemesManager::getScheme($addon);
                if (!empty($addon_scheme)) {
                    fn_update_addon_settings($addon_scheme, false);
                }
            }

            $settings = db_get_array('SELECT * FROM ?:settings_objects_upg');
            foreach ($settings as $setting) {
                Settings::instance()->updateValue($setting['name'], $setting['value'], $setting['section_name'], false, null, false);
            }
            db_query('DROP TABLE ?:settings_objects_upg');

            $was_company_settings_backed_up = db_get_field("SHOW TABLES LIKE '?:settings_vendor_values_upg'");
            if (!empty($was_company_settings_backed_up)) {
                $company_settings = db_get_array('SELECT * FROM ?:settings_vendor_values_upg');
                foreach ($company_settings as $setting) {
                    Settings::instance($setting['company_id'])->updateValue($setting['name'], $setting['value'], $setting['section_name'], false, $setting['company_id'], false);
                }
                db_query('DROP TABLE ?:settings_vendor_values_upg');
            }
        }

        return true;
    }

    public static function processAddons($store_data, $class_name, $addons = array())
    {
        $addons = empty($addons) ? General::getInstalledAddons() : $addons;
        self::setEmptyProgressBar(self::getUnavailableLangVar('processing_addons'));
        if (empty($addons)) {
            return true;
        }

        foreach ($addons as $addon) {
            $sql_filename = Registry::get('config.dir.addons') . 'store_import/database/addons/' . General::getFileName($store_data, $class_name, $addon) . '.sql';
            $php_filename = Registry::get('config.dir.addons') . 'store_import/scripts/addons/' . General::getFileName($store_data, $class_name, $addon) . '.php';

            if (is_file($sql_filename)) {
                if (!db_import_sql_file($sql_filename, 16384, false, true, false, false, false, false)) {
                    return false;
                }
            }
            if (is_file($php_filename)) {
                include($php_filename);
            }
        }

        return true;
    }

    public static function processBlocks()
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        $blocks = db_get_array('SELECT block_id, properties FROM ?:bm_blocks WHERE type = ?s', 'product_filters');
        foreach ($blocks as $block) {
            if (!empty($block['properties'])) {
                $prop = unserialize($block['properties']);
                if ($prop['template'] == 'blocks/product_filters.tpl') {
                    $prop['template'] = 'blocks/product_filters/original.tpl';
                } elseif ($prop['template'] == 'blocks/product_filters_extended.tpl') {
                    $prop['template'] = 'blocks/product_filters/custom.tpl';
                }
                db_query("UPDATE ?:bm_blocks SET properties = ?s WHERE block_id = ?i", serialize($prop), $block['block_id']);
            }
        }

        return true;
    }

    public static function getDefaultCompany()
    {
        $company_data = db_get_hash_single_array("SELECT name, value FROM ?:settings_objects WHERE section_id = 5", array('name', 'value'));
        $new_company_data = array(
            'status' => 'A',
            'company' => $company_data['company_name'],
            'lang_code' => Registry::get('settings.Appearance.backend_default_language'),
            'address' => $company_data['company_address'],
            'city' => $company_data['company_city'],
            'state' => $company_data['company_state'],
            'country' => $company_data['company_country'],
            'zipcode' => $company_data['company_zipcode'],
            'email' => $company_data['company_site_administrator'],
            'phone' => $company_data['company_phone'],
            'fax' => $company_data['company_fax'],
            'url' => $company_data['company_website'],
            'timestamp' => time(),
        );

        return $new_company_data;
    }

    public static function createDefaultCompany($default_company)
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));

        return db_query("INSERT INTO ?:companies ?e", $default_company);
    }

    public static function getNotification($store_data)
    {
        $version_name = self::_getVersionName($store_data['product_version']);
        $langvar_name = 'store_import.' . $version_name . '_' . strtolower($store_data['product_edition']);

        return __($langvar_name, array(
            "[stores_section_link]" => fn_url('companies.manage'),
            "[manage_languages_link]" => fn_url('languages.manage'),
        ));
    }

    public static function replaceOriginalDB($store_data, $enable_non_imported_objects = false)
    {
        $exluded_tables = array(
            'stat_browsers',
            'stat_ips',
            'stat_languages',
            'stat_product_search',
            'stat_requests',
            'stat_search_engines',
            'stat_search_phrases',
            'stat_sessions',
            'stat_banners_log',
        );
        $obsolete_tables = fn_get_schema('store_import', 'obsolete_tables');
        if ($enable_non_imported_objects) {
            $exluded_tables = array_merge($exluded_tables, fn_get_schema('store_import', 'table_replacement'));
        }

        General::connectToOriginalDB();
        $orig_table_prefix = Registry::get('config.table_prefix');
        $db_name = Registry::get('config.db_name');
        $orig_tables = self::getTables($db_name, $orig_table_prefix);

        $new_table_prefix = General::formatPrefix();
        $imported_tables = self::getTables($db_name, $new_table_prefix);
        //Filter imported table. In case new database has not prefix the $orig_tables array will contain all tables include imported.
        $orig_tables = array_diff($orig_tables, $imported_tables);

        fn_set_progress('step_scale', count($orig_tables));
        foreach ($orig_tables as $table_name) {
            //Cant use lang vars because table can be droppep when we try to get lang var.
            fn_set_progress('echo', 'Dropping original tables', true);
            if (!in_array(str_replace($orig_table_prefix, '', $table_name), $exluded_tables)) {
                db_query("DROP TABLE $table_name");
            }
        }

        fn_set_progress('step_scale', count($imported_tables));
        foreach ($imported_tables as $table_name) {
            if (in_array(str_replace($new_table_prefix, '', $table_name), $obsolete_tables)) {
                db_query("DROP TABLE $table_name");
                continue;
            }
            fn_set_progress('echo', 'Renaming original tables', true);
            $new_table_name = str_replace($new_table_prefix, $orig_table_prefix, $table_name);
            if (!in_array(str_replace($new_table_prefix, '', $table_name), $exluded_tables)) {
                db_query("RENAME TABLE $table_name TO $new_table_name");
            }
        }

        return true;
    }

    public static function processBMBlocksTemplates()
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        $blocks = db_get_array("SELECT * FROM ?:bm_blocks WHERE type <> 'main'");

        foreach ($blocks as $key => $block) {
            $properties = unserialize($block['properties']);
            $properties['template'] = str_replace('common_templates/', 'common/', $properties['template']);
            db_query("UPDATE ?:bm_blocks SET properties = ?s WHERE block_id = ?i", serialize($properties), $block['block_id']);
        }

        return true;
    }

    public static function processBMProductFiltersBlockContent()
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        $block_contents = db_get_array("SELECT * FROM ?:bm_blocks_content WHERE block_id IN (SELECT block_id FROM ?:bm_blocks WHERE type = 'product_filters')");

        foreach ($block_contents as $key => $block_content) {
            $content = unserialize($block_content['content']);
            $content['items']['filling'] = 'manually';
            $block_content['content'] = serialize($content);
            db_query("REPLACE INTO ?:bm_blocks_content ?e", $block_content);
        }

        return true;
    }

    public static function supplierSettings($param)
    {
        return (!empty($param) && !empty(self::$supplier_settings[$param])) ? self::$supplier_settings[$param] : false;
    }

    public static function setSupplierSettings($params, $ignor = false)
    {
        if (empty(self::$supplier_settings) || $ignor) {
            self::$supplier_settings = $params;
            self::$supplier_settings['enabled'] = self::$supplier_settings['enabled'] == 'Y' ? true : false;
        }

        return true;
    }

    public static function createLogo($company_id = 0, $layout_id = 0, $filename = '', $img_path = '', $type = 'theme', $common_descr_object = 'Customer_logo')
    {
        $company_id = (int) $company_id;
        $layout_id = (int) $layout_id;
        $logo_id = db_query("INSERT INTO ?:logos ?e", array('layout_id' => $layout_id, 'company_id' => $company_id, 'type' => $type));
        $image_data = array(
            'name' => $filename,
            'path' => $img_path,
            'params' => array(
                'keep_origins' => true,
            ),
        );

        if (!file_exists($img_path) || filesize($img_path) === 0) {
            return false;
        }

        $img_id = fn_update_image($image_data, 0, 'logos');
        $image_link_data = array(
            'object_id' => $logo_id,
            'object_type' => 'logos',
            'image_id' => $img_id,
        );
        foreach (db_get_fields("SELECT lang_code FROM ?:languages") as $lang_code) {
            $descr = db_get_field("SELECT description FROM ?:common_descriptions WHERE object_id= ?i AND lang_code = ?s AND object_holder = ?s", $company_id, $lang_code, $common_descr_object);
            if (!empty($descr)) {
                db_query("REPLACE INTO ?:common_descriptions (object_id, description, lang_code, object_holder) VALUES (?i, ?s, ?s, 'images')", $img_id, $descr, $lang_code);
            }
        }

        return db_query("INSERT INTO ?:images_links ?e", $image_link_data);
    }

    public static function convertPresets403To411()
    {
        $themes_path = fn_get_theme_path('[themes]', 'C');
        $themes = fn_get_dir_contents($themes_path);

        foreach ($themes as $theme) {
            if (is_dir($themes_path . '/' . $theme . '/presets')) {
                rename($themes_path . '/' . $theme . '/presets', $themes_path . '/' . $theme . '/styles');

                $json = json_decode(fn_get_contents($themes_path . '/' . $theme . '/styles/manifest.json'), true);
                if (!empty($json)) {
                    $json['default_style'] = $json['default_preset'];
                    unset($json['default_preset']);

                    fn_put_contents($themes_path . '/' . $theme . '/styles/manifest.json', json_encode($json));
                }
            }
        }
    }

    public static function convertPresets401To402()
    {
        $theme_name = Registry::get('config.base_theme');
        $schema_path = fn_get_theme_path('[themes]/' . $theme_name . '/styles/schema.json', 'C');
        $schema = file_get_contents($schema_path);
        if (!empty($schema)) {
            $schema = json_decode($schema, true);
        }

        db_query('ALTER TABLE ?:bm_layouts CHANGE `preset_id` `preset_id` varchar(64) NOT NULL default ""');

        $presets = db_get_array('SELECT * FROM ?:theme_presets');

        foreach ($presets as $preset) {
            $preset['name'] = self::_formPresetName($preset['name']);
            //We should rename default preset to satori in order to prevent default preset creation.
            if ($preset['name'] == 'default') {
                $preset['name'] = 'satori';
            }

            db_query('UPDATE ?:bm_layouts SET preset_id = ?s WHERE preset_id = ?i', $preset['name'], $preset['preset_id']);

            $preset_path = fn_get_theme_path('[themes]/' . $theme_name . '/styles/data/', 'C');
            if (file_exists($preset_path . $preset['name'] . '.less')) {
                continue;
            }

            $preset_data = unserialize($preset['data']);

            $less = array();

            foreach ($preset_data as $section => $fields) {
                foreach ($fields as $field_id => $value) {
                    switch ($section) {
                    case 'general':
                        $less[$field_id] = empty($value) ? $schema[$section]['fields'][$field_id]['off'] : $schema[$section]['fields'][$field_id]['on'];
                        break;

                    case 'colors':
                        $less[$field_id] = $value;
                        break;

                    case 'fonts':
                        $less[$field_id] = $value['family'];

                        if (!empty($value['size'])) {
                            $field_name = $schema[$section]['fields'][$field_id]['properties']['size']['match'];
                            $field_value = $value['size'] . $schema[$section]['fields'][$field_id]['properties']['size']['unit'];

                            $less[$field_name] = $field_value;
                        }

                        if (!empty($value['style'])) {
                            foreach ($value['style'] as $style_type => $style_value) {
                                $field_name = $schema[$section]['fields'][$field_id]['properties']['style'][$style_type]['match'];
                                $field_value = $schema[$section]['fields'][$field_id]['properties']['style'][$style_type]['property'];

                                $less[$field_name] = $field_value;
                            }
                        }

                        break;

                    case 'backgrounds':
                        $value['transparent'] = isset($value['transparent']) ? $value['transparent'] : false;
                        $value['full_width'] = isset($value['full_width']) ? $value['full_width'] : false;

                        foreach ($value as $bg_name => $bg_value) {
                        switch ($bg_name) {
                            case 'color':
                                $field_name = $schema[$section]['fields'][$field_id]['properties']['color']['match'];
                                $less[$field_name] = $bg_value;
                                break;

                            case 'gradient':
                                $field_name = $schema[$section]['fields'][$field_id]['gradient']['match'];
                                $less[$field_name] = $bg_value;
                                break;

                            case 'image_data':
                                $less[$schema[$section]['fields'][$field_id]['properties']['pattern']] = !empty($bg_value) ? 'url("' . $bg_value . '")' : 'transparent';
                                break;

                            case 'repeat':
                                $field_name = $schema[$section]['fields'][$field_id]['properties']['repeat'];
                                if (!empty($field_name)) {
                                    $less[$field_name] = $bg_value;
                                }
                                break;

                            case 'attachment':
                                $field_name = $schema[$section]['fields'][$field_id]['properties']['attachment'];
                                if (!empty($field_name)) {
                                    $less[$field_name] = $bg_value;
                                }
                                break;

                            case 'full_width':
                                if (!isset($schema[$section]['fields'][$field_id]['copies'])) {
                                    break;
                                }
                                foreach ($schema[$section]['fields'][$field_id]['copies']['full_width'] as $copies) {
                                    if (!empty($value['full_width'])) {
                                        if (!empty($copies['inverse'])) {
                                            $less[$copies['match']] = $copies['default'];
                                        } elseif (isset($less[$copies['source']])) {
                                            $less[$copies['match']] = $less[$copies['source']];
                                        }
                                    } else {
                                        if (empty($copies['inverse'])) {
                                            $less[$copies['match']] = $copies['default'];
                                        }
                                    }
                                }
                                break;

                            case 'transparent':
                                if (!isset($schema[$section]['fields'][$field_id]['copies'])) {
                                    break;
                                }
                                foreach ($schema[$section]['fields'][$field_id]['copies']['transparent'] as $copies) {
                                    if (!empty($value['transparent'])) {
                                        if (!empty($copies['inverse'])) {
                                            $less[$copies['match']] = $copies['default'];
                                        } elseif (isset($less[$copies['source']])) {
                                            $less[$copies['match']] = $less[$copies['source']];
                                        }
                                    } else {
                                        if (empty($copies['inverse'])) {
                                            $less[$copies['match']] = $copies['default'];
                                        }
                                    }
                                }
                                break;

                            case 'image_name': break;

                            default:
                                fn_print_r('Unprocessed background property: ' . $bg_name);
                            }
                        }
                        break;

                    default:
                        fn_print_r('Error: Section ' . $section . ' was not processed');
                    }
                }
            }

            $less = Less::arrayToLessVars($less);

            file_put_contents(fn_get_theme_path('[themes]/' . $theme_name . '/styles/data/' . $preset['name'] . '.less', 'C'), $less);
        }

        db_query('DROP TABLE IF EXISTS ?:theme_presets');

        return true;
    }

    private static function _formPresetName($name)
    {
        $name = preg_replace('/\(.*?\)/', '', $name);
        $name = trim($name);
        $name = strtolower($name);
        $name = str_replace(' ', '_', $name);

        return $name;
    }

    public static function testStoreConfiguration($store_data)
    {
        $config_local_php = file_get_contents(Registry::get('config.dir.root') . '/config.local.php');

        if ($config_local_php) {
            $cache_backend = self::_getVariable('cache_backend', $config_local_php);
            if ($cache_backend != 'file') {
                return false;
            }
        }

        return true;
    }

    public static function checkEditionMapping($store_data)
    {
        if (in_array($store_data['product_version'], array('4.0.1', '4.0.2', '4.0.3', '4.1.1', '4.1.2', '4.1.3', '4.1.4', '4.1.5', '4.2.1', '4.2.2', '4.2.3'))) {
//            return false;
        }
        $mapping = array(
            '4' => array(
                'ult_ult',
                'mve_mve',
            ),
            '3' => array(
                'pro_ult',
                'ult_ult',
                'mve_mve',
            ),
            '2' => array(
                'pro_pro',
                'mve_mve',
                'pro_ult'
            ),
        );

        if (!empty($store_data)) {
            $orig_edition = fn_get_edition_acronym($store_data['product_edition']);
            $orig_version = substr($store_data['product_version'], 0, 1);
            $edition_str = $orig_edition . '_' . fn_get_edition_acronym(PRODUCT_EDITION);

            if (in_array(strtolower($edition_str), $mapping[$orig_version])) {
                return true;
            }
        }

        return false;
    }

    public static function addStatusColors()
    {
        $types = db_get_fields("SELECT type FROM ?:status_data GROUP BY type");
        $statuses = db_get_fields("SELECT status FROM ?:status_data GROUP BY status");
        $default_values = array(
            'B_O' => '#28abf6',
            'C_O' => '#97cf4d',
            'D_O' => '#ff5215',
            'F_O' => '#ff5215',
            'I_O' => '#c2c2c2',
            'O_O' => '#ff9522',
            'P_O' => '#97cf4d',
            'A_G' => '#97cf4d',
            'C_G' => '#c2c2c2',
            'P_G' => '#ff9522',
            'U_G' => '#28abf6',
        );

        foreach ($types as $type) {
            foreach ($statuses as $status) {
                $status_data = array(
                    'status' => $status,
                    'type' => $type,
                    'param' => 'color',
                    'value' => (isset($default_values[$status . '_' . $type]) ? $default_values[$status . '_' . $type] : '#23cfdb')
                );
                db_replace_into('status_data', $status_data);
            }
        }
    }

    public static function updateStatusColors()
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        $statuses = db_get_array("SELECT * FROM ?:status_data WHERE param = 'color'");
        foreach ($statuses as $status_data) {
            if (strpos($status_data['value'], '#') !== 0) {
                $status_data['value'] = '#' . ($status_data['value'] ? $status_data['value'] : 'ffffff');
                db_replace_into('status_data', $status_data);
            }
        }

    }

    public static function convertPrivileges()
    {
        db_query("ALTER TABLE ?:privileges ADD COLUMN `section_id` varchar(32) NOT NULL default ''");
        db_query("ALTER TABLE ?:privileges ADD KEY `section_id` (`section_id`)");

        $privilege_sections = db_query(
            "UPDATE ?:privileges, ?:privilege_descriptions"
            . " SET ?:privileges.section_id = ?:privilege_descriptions.section_id"
            . " WHERE ?:privileges.privilege = ?:privilege_descriptions.privilege"
        );

        $section_ids = array (
            1 => 'addons',
            2 => 'administration',
            3 => 'addons', // affiliate section is moved to addons
            4 => 'cart',
            5 => 'catalog',
            6 => 'cms',
            7 => 'design',
            8 => 'orders',
            9 => 'users',
            10 => 'vendors',
        );

        //Affiliate hack
        db_query(
            "REPLACE INTO ?:privilege_section_descriptions( section_id, description, lang_code )"
            . " SELECT '3', description, lang_code"
            . " FROM ?:privilege_section_descriptions"
            . " WHERE section_id = 1"
        );

        foreach ($section_ids as $prev_id => $new_id) {
            db_query("UPDATE ?:privileges SET section_id = ?s WHERE section_id = ?i", $new_id, $prev_id);

            // Update privilege sections titles
            db_query(
                "REPLACE INTO ?:language_values (`lang_code`, `name`, `value`)"
                . " SELECT lang_code, 'privilege_sections.$new_id', description"
                . " FROM ?:privilege_section_descriptions"
                . " WHERE ?:privilege_section_descriptions.section_id = ?i", $prev_id
            );

        }

        // Update privilege titles
        db_query(
            "REPLACE INTO ?:language_values (`lang_code`, `name`, `value`)"
            . " SELECT lang_code, CONCAT('privileges.', ?:privilege_descriptions.privilege) AS name, description"
            . " FROM ?:privilege_descriptions"
        );

        db_query("INSERT INTO `?:privileges` (privilege, is_default, section_id) VALUES"
            . " ('manage_translation', 'Y', 'administration'),"
            . " ('manage_storage', 'Y', 'administration'),"
            . " ('manage_design', 'Y', 'design'),"
            . " ('manage_themes', 'Y', 'design')"
        );

        db_query('DROP TABLE IF EXISTS ?:privilege_descriptions');
        db_query('DROP TABLE IF EXISTS ?:privilege_section_descriptions');

        return true;
    }

    public static function installAddonsTabs()
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        $addons = db_get_array("SELECT addon, status FROM ?:addons");
        if (fn_allowed_for('ULTIMATE')) {
            $companies = fn_get_all_companies_ids(true);
            foreach ($addons as $addon) {
                ProductTabs::instance()->deleteAddonTabs($addon['addon']);
                foreach ($companies as $company) {
                    ProductTabs::instance($company)->createAddonTabs($addon['addon']);
                }
                ProductTabs::instance()->updateAddonTabStatus($addon['addon'], $addon['status']);
            }
        } else {
            foreach ($addons as $addon) {
                ProductTabs::instance()->deleteAddonTabs($addon['addon']);
                ProductTabs::instance()->createAddonTabs($addon['addon']);
                ProductTabs::instance()->updateAddonTabStatus($addon['addon'], $addon['status']);
            }
        }

        return true;
    }

    public static function installAddons()
    {
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        $addons = db_get_fields("SELECT addon FROM ?:addons");
        //fill original values in new database
        foreach ($addons as $addon) {
            $addon_scheme = SchemesManager::getScheme($addon);
            if ($addon_scheme) {
                if ($original = $addon_scheme->getOriginals()) {
                    db_query("REPLACE INTO ?:original_values ?e", array(
                        'msgctxt' => 'Addon:' . $addon,
                        'msgid' => $original['name']
                    ));

                    db_query("REPLACE INTO ?:original_values ?e", array(
                        'msgctxt' => 'AddonDescription:' . $addon,
                        'msgid' => $original['description']
                    ));
                }

                $language_variables = $addon_scheme->getLanguageValues(true);
                if (!empty($language_variables)) {
                    db_query('REPLACE INTO ?:original_values ?m', $language_variables);
                }
            }
        }

        General::connectToOriginalDB();
        foreach ($addons as $addon) {
            if (!db_get_field("SELECT status FROM ?:addons WHERE addon = ?s", $addon)) {
                fn_install_addon($addon, false);
            }
        }

        return true;
    }

    public static function checkCompanyCount($store_data)
    {
        $result = false;
        if ($store_data['product_edition'] == 'ULTIMATE') {
            General::connectToImportedDB($store_data);
            $companies_old = db_get_fields("SELECT company_id FROM ?:companies ORDER BY company_id ASC");
            General::connectToOriginalDB();
            $companies_new = db_get_fields("SELECT company_id FROM ?:companies ORDER BY company_id ASC");

            $result = ($companies_new === $companies_old);
        } elseif ($store_data['product_edition'] == 'PROFESSIONAL') {
            General::connectToOriginalDB();
            $companies_new = db_get_fields("SELECT company_id FROM ?:companies ORDER BY company_id ASC");

            $result = ((int) count($companies_new) === 1 && (int) $companies_new[0] === 1);
        } elseif ($store_data['product_edition'] == 'MULTIVENDOR') {
            General::connectToImportedDB($store_data);
            $companies_old = db_get_fields("SELECT company_id FROM ?:companies ORDER BY company_id ASC");
            General::connectToOriginalDB();
            $companies_new = db_get_fields("SELECT company_id FROM ?:companies ORDER BY company_id ASC");
            $companies_common = fn_array_merge($companies_new, $companies_old);
            if (in_array($store_data['product_version'], array('2.2.4','2.2.5','3.0.1','3.0.2','3.0.3','3.0.4','3.0.5','3.0.6'))) {
                $old_companies_count = (int) count($companies_old)+1;
            } else {
                $old_companies_count = (int) count($companies_old);
            }
            if ($companies_new === $companies_common && $old_companies_count === (int) count($companies_new)) {
                $result = true;
            }
        }

        return $result;
    }

    //process 22x addons settings
    public static function processAddonsSettings($addons)
    {
        foreach ($addons as $addons_name => $addon_data) {
            if (!empty($addon_data['options'])) {
                foreach ($addon_data['options'] as $setting_name => $setting_value) {
                    db_query("UPDATE ?:settings_objects SET value = ?s WHERE name = ?s", $setting_value, $setting_name);
                }
            }
        }

        return true;
    }

    //enable addons imported from 22x versions
    public static function enableInstalledAddons($addons)
    {
        foreach ($addons as $addon_name => $addon_data) {
            foreach ($addon_data['names'] as $lang_code => $name) {
                db_query("INSERT INTO ?:addon_descriptions (addon, name, description, lang_code) VALUES (?s, ?s, '', ?s)", $addon_name, $name['description'], $lang_code);
            }

            db_query("INSERT INTO ?:addons (addon, status, version, priority, dependencies, conflicts, separate) VALUES (?s, ?s, '1.0', ?i, ?s, '', 0)", $addon_name, $addon_data['status'], $addon_data['priority'], $addon_data['dependencies']);
        }

        return true;
    }

    //Get installed addons with settings for the 22x version
    public static function get22xAddons()
    {
        $addons = db_get_hash_array("SELECT * FROM ?:addons ORDER BY priority", 'addon');
        foreach ($addons as $key => $addon) {
            $addons[$key]['names'] = db_get_hash_array("SELECT description, lang_code FROM ?:addon_descriptions WHERE object_type = 'A' AND addon = ?s", 'lang_code', $key);
            if (!empty($addon['options'])) {
                $addons[$key]['options'] = unserialize($addon['options']);
            }
        }

        return $addons;
    }

    public static function getFromToText($store_data)
    {
        $from = __('store_import.text_from', array(
            '[product_name]' => $store_data['product_name'],
            '[product_version]' => $store_data['product_version'],
            '[product_edition]' => ucfirst(strtolower($store_data['product_edition'])),
        ));

        $to = __('store_import.text_to', array(
            '[product_edition]' => PRODUCT_NAME,
            '[product_version]' => PRODUCT_VERSION,
        ));

        return array($from, $to);
    }

    public static function setProgressTitle($class_name)
    {
        $upgrate_to = substr($class_name, -3);
        fn_set_progress('title', __('store_import.progress_title', array('[to]' => $upgrate_to)));
    }

    public static function setDefaultLanguage($store_data)
    {
        General::connectToImportedDB($store_data);
        if (!empty($store_data) && in_array($store_data['product_version'], array('2.2.4', '2.2.5'))) {
            $default_language = db_get_field("SELECT value FROM ?:settings WHERE option_name = 'admin_default_language'");
        } elseif (!empty($store_data) && in_array($store_data['product_version'], array('3.0.1', '3.0.2', '3.0.3', '3.0.4', '3.0.5', '3.0.6'))) {
            $default_language = db_get_field("SELECT value FROM ?:settings_objects WHERE name = 'admin_default_language'");
        } else {
            $default_language = db_get_field("SELECT value FROM ?:settings_objects WHERE name = 'backend_default_language'");
        }
        General::connectToOriginalDB();

        self::$default_language = strtolower($default_language);

        return true;
    }

    public static function setActualLangValues()
    {
        $addons = self::getInstalledAddons();
        $languages = db_get_fields("SELECT lang_code FROM ?:languages");
        foreach ($addons as $addon) {
            $addon_scheme = AddonSchemesManager::getScheme($addon);
            if (!empty($addon_scheme)) {
                // Add optional language variables
                $language_variables = $addon_scheme->getLanguageValues();
                if (!empty($language_variables)) {
                    db_query('REPLACE INTO ?:language_values ?m', $language_variables);
                }
                foreach ($languages as $lang_code) {
                    $description = $addon_scheme->getDescription($lang_code);
                    $addon_name = $addon_scheme->getName($lang_code);
                    db_query("UPDATE ?:addon_descriptions SET description = ?s, name = ?s WHERE addon = ?s AND lang_code = ?s", $description, $addon_name, $addon, $lang_code);
                }
                $tabs = $addon_scheme->getSections();
                if (!empty($tabs)) {
                    foreach ($tabs as $tab_index => $tab) {
                        $addon_section_id = db_get_field("SELECT section_id FROM ?:settings_sections WHERE name = ?s AND parent_id = (SELECT section_id FROM ?:settings_sections WHERE name = ?s)", $tab['id'], $addon);
                        //Can't check edition here, so just skip description update if addon section was not found.
                        if (!empty($addon_section_id)) {
                            fn_update_addon_settings_descriptions($addon_section_id, Settings::SECTION_DESCRIPTION, $tab['translations']);
                            $settings = $addon_scheme->getSettings($tab['id']);
                            foreach ($settings as $k => $setting) {
                                $setting_id = db_get_field("SELECT object_id FROM ?:settings_objects WHERE name = ?s AND section_tab_id = ?i", $setting['id'], $addon_section_id);
                                if (!empty($setting_id)) {
                                    fn_update_addon_settings_descriptions($setting_id, Settings::SETTING_DESCRIPTION, $setting['translations']);
                                    if (isset($setting['variants'])) {
                                        foreach ($setting['variants'] as $variant_k => $variant) {
                                            $variant_id = db_get_field("SELECT variant_id FROM ?:settings_variants WHERE object_id = ?i AND name = ?s", $setting_id, $variant['id']);
                                            if (!empty($variant_id)) {
                                                fn_update_addon_settings_descriptions($variant_id, Settings::VARIANT_DESCRIPTION, $variant['translations']);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        $prefix = Registry::get('config.table_prefix');
/*
        db_query("INSERT INTO ?:state_descriptions (SELECT state_id, ?s as lang_code, state FROM " . $prefix . "state_descriptions WHERE lang_code = ?s) ON DUPLICATE KEY UPDATE ?:state_descriptions.state_id = ?:state_descriptions.state_id", General::$default_language, DEFAULT_LANGUAGE);
        if (fn_allowed_for('ULTIMATE')) {
            db_query("INSERT  INTO ?:ult_language_values (SELECT ?s as lang_code, name, value, company_id FROM " . $prefix . "ult_language_values WHERE lang_code = ?s) ON DUPLICATE KEY UPDATE ?:ult_language_values.value = ?:ult_language_values.value", General::$default_language, DEFAULT_LANGUAGE);
        }

        $new_ver_langs = db_get_fields("SELECT lang_code FROM " . $prefix . "languages");
        foreach (db_get_fields("SELECT lang_code FROM ?:languages") as $lang_code) {
            $_lang_code = in_array($lang_code, $new_ver_langs) ? $lang_code : DEFAULT_LANGUAGE;
            //We can update only core settings descriptions because addons descriptions were updated during settings restore.
            db_query("INSERT INTO ?:settings_descriptions "
                    . "(SELECT object_id, object_type, ?s as lang_code, value, tooltip FROM " . $prefix . "settings_descriptions WHERE object_id IN "
                        . "(SELECT object_id FROM " . $prefix . "settings_objects WHERE section_id IN "
                            . "(SELECT section_id FROM " . $prefix . "settings_sections WHERE type = 'CORE')) "
                    . "AND lang_code = ?s) "
                    . "ON DUPLICATE KEY UPDATE ?:settings_descriptions.value = " . $prefix . "settings_descriptions.value, ?:settings_descriptions.tooltip = " . $prefix . "settings_descriptions.tooltip",
                    $lang_code, $_lang_code
            );
            db_query("INSERT INTO ?:language_values "
                    . "(SELECT ?s as lang_code, name, value FROM " . $prefix . "language_values WHERE lang_code = ?s) "
                    . "ON DUPLICATE KEY UPDATE ?:language_values.name = " . $prefix . "language_values.name",
                    $lang_code, $_lang_code
            );
        }

        db_query("REPLACE INTO ?:original_values (SELECT * FROM " . $prefix . "original_values)");
 */
        $langs = db_get_fields("SELECT lang_code FROM ?:languages");
        $po_path = Registry::get('config.dir.lang_packs');
        $params = array(
            'install_newly_added' => true,
            'reinstall' => true,
        );
        foreach ($langs as $lang_code) {
            $params['force_lang_code'] = $lang_code;
            $params['validate_lang_code'] = true;

            if (!is_dir($po_path . $lang_code)) {
                $params['validate_lang_code'] = false;
                if (is_dir($po_path . General::$default_language)) {
                    $lang_code = General::$default_language;
                    $params['force_lang_code'] = General::$default_language;
                } elseif (is_dir($po_path . 'en')) {
                    $lang_code = 'en';
                    $params['force_lang_code'] = 'en';
                }
            }

            $result = Languages::installCrowdinPack($po_path . $lang_code, $params);
        }
        return true;
    }

    public static function setEmptyProgressBar($text = '', $scale_count = 1)
    {
        if (empty($text)) {
            $text = self::getUnavailableLangVar('updating_data');
        }
        fn_set_progress('step_scale', $scale_count);
        fn_set_progress('echo', $text, true);
    }

    private static function _removeTempTables()
    {
        $prefix = General::formatPrefix();
        $fields = db_get_fields("SHOW TABLES LIKE '$prefix%'");

        if (!empty($fields)) {
            foreach ($fields as $field) {
                db_query("DROP TABLE IF EXISTS $field");
            }
        }

        return true;
    }

    private static function _setUnavailableLangVars()
    {
        self::$unavailable_lang_vars = array(
            'cant_connect_to_imported' => __('store_import.cant_connect_to_imported'),
            'cant_connect_to_original' => __('store_import.cant_connect_to_original'),
            'converting_orders' => __('store_import.converting_orders'),
            'processing_addons' => __('store_import.processing_addons'),
            'updating_languages' => __('store_import.updating_languages'),
            'updating_data' => __('store_import.updating_data'),
            'uc_searchanise_disabled' => __('uc_searchanise_disabled', array('[url]' => fn_url('addons.manage'))),
        );
    }

    public static function getUnavailableLangVar($name)
    {
        $langvars = self::$unavailable_lang_vars;

        return !empty($langvars[$name]) ? $langvars[$name] : '';
    }

    private static function _uninstallAllAddons()
    {
        $addons = db_get_fields("SELECT addon FROM ?:addons WHERE addon != 'store_import'");
        if (!empty($addons)) {
            foreach ($addons as $addon) {
                fn_uninstall_addon($addon, false);
            }
        }
    }

    private static function setDefaultSkinName($store_data)
    {
        General::connectToImportedDB($store_data);
        if (!empty($store_data) && in_array($store_data['product_version'], array('2.2.4', '2.2.5'))) {
            $skin_name = db_get_field("SELECT value FROM ?:settings WHERE option_name = 'skin_name_customer'");
        } elseif (!empty($store_data) && in_array($store_data['product_version'], array('3.0.1', '3.0.2', '3.0.3', '3.0.4', '3.0.5', '3.0.6'))) {
            $skin_name = db_get_field("SELECT value FROM ?:settings_objects WHERE name = 'skin_name_customer'");
        } else {
            $skin_name = db_get_field("SELECT value FROM ?:settings_objects WHERE name = 'theme_name'");
        }
        General::connectToOriginalDB();

        return $skin_name;
    }

    private static function processFiles($store_data, $type)
    {
        $exclude_files = array('.htaccess', 'index.php');
        $type_path_info = Registry::get('config.storage.' . $type);
        if (is_dir($store_data['path'] . '/var/' . $type)) {
            fn_copy($store_data['path'] . '/var/' . $type, $type_path_info['dir'] . $type_path_info['prefix'], true, $exclude_files);
        }

        return true;
    }

    public static function import($store_data, $actualize_data = false)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        fn_define('STORE_IMPORT', true);
        fn_define('DISABLE_HOOK_CACHE', true);
        $log_dir = Registry::get('config.dir.store_import');
        fn_mkdir($log_dir);

        $logger = \Tygh\Logger::instance();
        $logger->logfile = $log_dir . date('Y-m-d_H-i') . '.log';

        if ($actualize_data) {
            $logos = self::_backupLogos();
        }

        $import_classes_cascade = self::getImportClassesCascade($store_data);
        $db_already_cloned = false;
        Registry::set('runtime.skip_sharing_selection', true);

        self::_removeTempTables();
        self::_setUnavailableLangVars();
        if (!$actualize_data) {
            self::_uninstallAllAddons();
        }

        fn_set_progress('parts', (count($import_classes_cascade) * 6) + 2);
        $result = !empty($import_classes_cascade) ? true : false;
        self::setDefaultLanguage($store_data);

        $store_data['skin_name'] = self::setDefaultSkinName($store_data);
        $theme_to_be_installed = db_get_field("SELECT value FROM ?:settings_vendor_values WHERE object_id = (SELECT object_id FROM ?:settings_objects WHERE name = 'theme_name')");
        if (empty($theme_to_be_installed)) {
            $theme_to_be_installed = db_get_field("SELECT value FROM ?:settings_objects WHERE name = 'theme_name'");
        }
        $style_id_to_be_installed = db_get_field("SELECT style_id FROM ?:bm_layouts WHERE is_default = '1'");

        foreach ($import_classes_cascade as $class_name) {
            if ($result) {
                if (class_exists($class_name)) {
                    $obj = new $class_name($store_data);
                    $result = $db_already_cloned = $obj -> import($db_already_cloned);
                    Settings::instance()->reloadSections();
                } else {
                    $result = false;
                    fn_set_notification('E', __('error'), __('store_import.class_not_found'));
                    break;
                }
            } else {
                fn_set_notification('E', __('error'), __('store_import.import_failed'));
                break;
            }
        }

        Registry::set('runtime.skip_sharing_selection', false);

        if ($result) {
            if (fn_allowed_for('ULTIMATE')) {
                General::setForcedCompanyId();
            }
            General::setLicenseData();
            //First, we should install all addons from old version in the new version that all templates, etc were installed in the new version
            self::installAddons();
            //Next, we should install all tabs in the upgraded database (mostly for the old version, 2.2.x)
            self::installAddonsTabs();
            
            if (fn_allowed_for('ULTIMATE')) {
                General::ultProcessImages($store_data);
            } else {
                General::mveProcessImages($store_data);
            }

            General::processFiles($store_data, 'downloads');
            General::processFiles($store_data, 'attachments');
            General::processFiles($store_data, 'custom_files');

            fn_clear_cache();

            if (!$actualize_data) {
                self::_removeRussianServices($store_data);
                General::uninstallAddons(array('twigmo', 'searchanise', 'live_help', 'exim_store', 'webmail'));
                /*
                if (fn_allowed_for('ULTIMATE')) {
                    $company_ids = db_get_fields("SELECT company_id FROM ?:companies");
                    foreach ($company_ids as $company_id) {
                        self::_installTheme($company_id);
                    }
                } else {
                    self::_installTheme();
                }
                 */
                db_query("UPDATE ?:settings_objects SET value = '$theme_to_be_installed' WHERE name = 'theme_name'");
                db_query("UPDATE ?:settings_vendor_values SET value = '$theme_to_be_installed' WHERE object_id = (SELECT object_id FROM ?:settings_objects WHERE name = 'theme_name')");
                db_query("UPDATE ?:bm_layouts SET style_id = '$style_id_to_be_installed'");
                db_query("UPDATE ?:bm_layouts SET theme_name  = '$theme_to_be_installed'");
            }

            self::replaceOriginalDB($store_data, $actualize_data);

            fn_install_addon('store_import', false);
            fn_uninstall_addon('twigmo', false);
            self::_removeTempTables();

            if (defined('AJAX_REQUEST')) {
                Registry::get('ajax')->assign('non_ajax_notifications', true);
                Registry::get('ajax')->assign('force_redirection', fn_url('index.index'));
            }
            if ($actualize_data) {
                self::_restoreLogos($logos);
            }
            fn_set_progress('step_scale', '1');
            fn_set_progress('echo', __('store_import.done'), true);

            return true;
        }

        return false;
    }

    private static function _installTheme($company_id = null)
    {
        $theme_name = 'basic';
        $style = 'satori';
        fn_install_theme($theme_name, $company_id);
        $layout = Layout::instance($company_id)->getDefault($theme_name);
        Styles::factory($theme_name)->setStyle($layout['layout_id'], $style);
    }

    public static function updateStoreimportSetting($setting_data)
    {
        $si_data = unserialize(Settings::instance()->getValue('si_data', 'store_import'));
        $si_data = array_merge($si_data, $setting_data);
        Settings::instance()->updateValue('si_data', serialize($si_data), 'store_import');

        return true;
    }

    public static function setLicenseData()
    {
        General::connectToOriginalDB();
        $si_data = unserialize(Settings::instance()->getValue('si_data', 'store_import'));
        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        if (!empty($si_data['license_data'])) {
            Settings::instance()->updateValue('license_number', $si_data['license_data']['license_number']);
            Settings::instance()->updateValue('current_timestamp', $si_data['license_data']['current_timestamp']);
            fn_set_storage_data('store_mode', $si_data['license_data']['store_mode']);
            $_SESSION['last_status'] = 'ACTIVE';
            $_SESSION['mode_recheck'] = false;

            return true;
        }

        return false;
    }

    public static function copyProductsBlocks($store_data)
    {
        General::connectToImportedDB($store_data);
        $blocks_22x = db_get_hash_array("SELECT * FROM ?:blocks WHERE block_type = 'B' AND location = 'products'", 'block_id');
        if (empty($blocks_22x)) {
            General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));

            return true;
        }
        $descriptions_22x = db_get_hash_multi_array("SELECT * FROM ?:block_descriptions WHERE block_id IN (?a)", array('block_id', 'lang_code'), array_keys($blocks_22x));
        $blocks_links_22x = db_get_hash_multi_array("SELECT * FROM ?:block_links WHERE block_id IN (?a)", array('block_id', 'link_id'), array_keys($blocks_22x));

        General::connectToOriginalDB(array('table_prefix' => General::formatPrefix()));
        foreach ($blocks_22x as $block_22x_id => $block_22x_data) {
            $block_22x_data['properties'] = unserialize($block_22x_data['properties']);

            if (!isset($block_22x_data['properties']['fillings']) || $block_22x_data['properties']['fillings'] != 'manually') {
                continue;
            }

            $block_data = array(
                'type' => 'products',
                'properties' => serialize(array(
                    'template' => 'blocks/products/products.tpl',
                    'item_number' => $block_22x_data['properties']['item_number'],
                    'hide_options' => 'N',
                    'hide_add_to_cart_button' => $block_22x_data['properties']['hide_add_to_cart_button'],
                )),
            );

            $block_id = db_query("INSERT INTO ?:bm_blocks ?e", $block_data);

            $default_block_content = array(
                'snapping_id' => '0',
                'object_id' => '0',
                'object_type' => '',
                'block_id' => $block_id,
                'content' => serialize(array(
                    'items' => array(
                        'filling' => 'manually',
                        'item_ids' => '',
                    ),
                )),
            );

            foreach ($descriptions_22x[$block_22x_id] as $lang_code => $descr_data) {
                $default_block_content['lang_code'] = $lang_code;
                db_query("INSERT INTO ?:bm_blocks_content ?e", $default_block_content);
                foreach ($blocks_links_22x[$block_22x_id] as $key => $content_22x) {
                    if (!empty($content_22x['item_ids'])) {
                        $block_content = array(
                            'snapping_id' => '0',
                            'object_id' => $content_22x['object_id'],
                            'object_type' => 'products',
                            'block_id' => $block_id,
                            'lang_code' => $lang_code,
                            'content' => serialize(array(
                                'items' => array(
                                    'filling' => 'manually',
                                    'item_ids' => $content_22x['item_ids'],
                                ),
                            )),
                        );
                        db_query("INSERT INTO ?:bm_blocks_content ?e", $block_content);
                    }
                }
                db_query("INSERT INTO ?:bm_blocks_descriptions (block_id, lang_code, name) VALUES (?i, ?s, ?s)", $block_id, $lang_code, $descr_data['description']);

            }

            $snapping_data = array(
                'block_id' => $block_id,
                'grid_id' => '34', //We can use exact value because defaulr blocks and grids were created during import from 22x versions.
                'status' => 'A',
            );
            $snapping_id = db_query('INSERT INTO ?:bm_snapping ?e', $snapping_data);

            $object_ids = array();
            foreach ($blocks_links_22x[$block_22x_id] as $content_22x) {
                if ($content_22x['enable'] == 'N') {
                    $object_ids[] = $content_22x['object_id'];
                }
            }

            if (!empty($object_ids)) {
                $block_22x_statuses = array(
                    'snapping_id' => $snapping_id,
                    'object_ids' => implode(',', $object_ids),
                    'object_type' => 'products'
                );

                db_query('INSERT INTO ?:bm_block_statuses ?e', $block_22x_statuses);
            }
        }

        return true;
    }

    public static function checkLicense($store_data)
    {
        $result = true;
        General::connectToOriginalDB();
        $new_license_data = self::_getlicenseData();
        if (empty($new_license_data['license_number']) || !self::_getLicenseStatus($new_license_data)) {
            General::connectToImportedDB($store_data);
            $old_license_data = self::_getlicenseData($store_data);
            General::connectToOriginalDB();

            $result = !empty($old_license_data['license_number']) ? self::_getLicenseStatus($old_license_data) : false;
        }

        return $result;
    }

    private static function _getlicenseData($store_data = array())
    {
        $license_data = array(
            'current_timestamp' => self::_getTimestampData($store_data),
            'license_number' => self::_getLicenseNumber($store_data),
            'store_mode' => 'full',
        );

        return $license_data;
    }

    private static function _getTimestampData($store_data)
    {
        $result = '';
        if (!empty($store_data) && in_array($store_data['product_version'], array('2.2.4', '2.2.5'))) {
            $result = db_get_field("SELECT description FROM ?:settings_descriptions WHERE object_id = 70024");
        } elseif (!empty($store_data) && in_array($store_data['product_version'], array('3.0.1', '3.0.2', '3.0.3', '3.0.4', '3.0.5', '3.0.6'))) {
            $result = db_get_field("SELECT value FROM ?:settings_descriptions WHERE object_id = 70024");
            $result = (!empty($result)) ? $result : time();
        } else {
            $result = db_get_field("SELECT value FROM ?:settings_objects WHERE name = 'current_timestamp'");
        }

        return $result;
    }

    private static function _getLicenseNumber($store_data)
    {
        $result = '';
        if (!empty($store_data) && in_array($store_data['product_version'], array('2.2.4', '2.2.5'))) {
            $result = db_get_field("SELECT value FROM ?:settings WHERE option_name = 'license_number'");
        } else {
            $result = db_get_field("SELECT value FROM ?:settings_objects WHERE name = 'license_number'");
        }

        return $result;
    }

    private static function _getLicenseStatus($license_data)
    {
        if (empty($license_data['license_number'])) {
            return false;
        }

        $result = Helpdesk::checkStoreImportAvailability($license_data['license_number'], General::VERSION_FOR_LICENSE_CHECK);
        if ($result) {
            $si_data = unserialize(Settings::instance()->getValue('si_data', 'store_import'));
            $si_data['license_data'] = $license_data;
            Settings::instance()->updateValue('si_data', serialize($si_data), 'store_import');
        }

        return $result;
    }

    private static function _backupLogos()
    {
        $images['images_links'] = db_get_hash_array("SELECT * FROM ?:images_links WHERE object_type = 'logos'", 'image_id');
        if (!empty($images['images_links'])) {
            $images['images_data'] = db_get_hash_array("SELECT * FROM ?:images WHERE image_id IN (?a)", 'image_id', array_keys($images['images_links']));
            $images['images_descriptions'] = db_get_hash_multi_array("SELECT * FROM ?:common_descriptions WHERE object_id IN (?a)", array('lang_code','object_id'), array_keys($images['images_data']));
        }

        return $images;
    }

    private static function _restoreLogos($images)
    {
        db_query("DELETE FROM ?:images WHERE image_id IN (SELECT image_id FROM ?:images_links WHERE object_type = 'logos')");
        db_query("DELETE FROM ?:common_descriptions WHERE object_id IN (SELECT image_id FROM ?:images_links WHERE object_type = 'logos')");
        db_query("DELETE FROM ?:images_links WHERE object_type = 'logos'");
        foreach ($images['images_data'] as $old_id => $data) {
            unset($data['image_id']);
            unset($images['images_links'][$old_id]['pair_id']);
            $image_link = $images['images_links'][$old_id];
            $new_image_id = db_query("INSERT INTO ?:images ?e", $data);
            $image_link['image_id'] = $new_image_id;
            foreach ($images['images_descriptions'] as $lang => $description) {
                if (isset($description[$old_id])) {
                    $new_description = $description[$old_id];
                    $new_description['object_id'] = $new_image_id;
                    db_query("REPLACE INTO ?:common_descriptions ?e", $new_description);
                }
            }
            db_query("REPLACE INTO ?:images_links ?e", $image_link);
        }

        return true;
    }

    public static function get22xSettings($settings_names = array())
    {
        $result = array();
        foreach ($settings_names as $setting_name) {
            $value = db_get_field("SELECT value FROM ?:settings WHERE option_name = ?s", $setting_name);
            if ($value) {
                $result[$setting_name] = $value;
            }
        }

        return $result;
    }

    public static function restore22xSavedSetting($settings_values)
    {
        if (!empty($settings_values)) {
            foreach ($settings_values as $name => $value) {
                db_query("UPDATE ?:settings_objects SET value = '$value' WHERE name = ?s", $name);
            }
        }

        return true;
    }

    public static function process402Settings()
    {
        $mapping = array(
            'allow_anonymous_shopping' => array(
                'Y' => 'allow_shopping',
                'P' => 'hide_price_and_add_to_cart',
                'B' => 'hide_add_to_cart',
            ),
            'alternative_currency' => array(
                'Y' => 'use_selected_and_alternative',
                'N' => 'use_only_selected',
            ),
            'min_order_amount_type' => array(
                'P' => 'only_products',
                'S' => 'products_with_shippings',
            ),
        );

        foreach ($mapping as $setting_name => $setting_data) {
            $old_value = db_get_field("SELECT value FROM ?:settings_objects_upg WHERE name = ?s", $setting_name);
            if (!empty($setting_data[$old_value])) {
                db_query("UPDATE ?:settings_objects_upg SET value = '$setting_data[$old_value]' WHERE name = ?s", $setting_name);
            }
        }

        return true;
    }

    public static function processPaymentCertificates($store_data)
    {
        $payment_methods = db_get_array('SELECT p.payment_id, pp.* FROM ?:payments p, ?:payment_processors pp WHERE pp.processor_id = p.processor_id AND p.processor_id != 0');
        $certificates_dir = Registry::get('config.dir.certificates');

        foreach ($payment_methods as $payment_method) {
            if (in_array($payment_method['processor_script'], array('paypal_express.php', 'paypal_pro.php', 'qbms.php'))) {
                $processor_params = db_get_field("SELECT processor_params FROM ?:payments WHERE payment_id = ?i", $payment_method['payment_id']);
                if (!empty($processor_params)) {
                    $processor_params = unserialize($processor_params);
                    $certificate_filename = '';
                    if (isset($payment_data['processor_params']['certificate_filename'])) {
                        $certificate_filename = $payment_data['processor_params']['certificate_filename'];
                    } elseif (isset($payment_data['processor_params']['certificate'])) {
                        $certificate_filename = $payment_data['processor_params']['certificate'];
                    }
                    if ($certificate_filename) {
                        $filename = $payment_method['payment_id'] . '/' . $certificate_filename;
                        $old_certificate_file = $store_data['path'] . '/payments/certificates/' . $certificate_filename;

                        if (file_exists($old_certificate_file)) {
                            fn_mkdir($certificates_dir . $payment_method['payment_id']);
                            fn_copy($old_certificate_file, $certificates_dir . $filename);
                        } else {
                            $filename = '';
                        }

                        $processor_params['certificate_filename'] = $filename;
                        $processor_params = serialize($processor_params);

                        db_query("UPDATE ?:payments SET processor_params = ?s WHERE payment_id = ?i", $payment_method['payment_id']);
                    }
                }
            }
        }
    }

    public static function checkRussianEdition($store_data)
    {
        if (in_array($store_data['product_version'], array('2.2.4', '2.2.5', '3.0.1'))) {
            return false;
        }

        $addons_to_check = array(
            'exim_1c',
            'kupivkredit',
            'loginza',
            'kupivkredit',
            'yandex_market',
        );

        $result = false;

        General::connectToOriginalDB();
        $addons_to_check_status = db_get_fields("SELECT status FROM ?:addons WHERE addon IN (?a)", $addons_to_check);
        if (!empty($addons_to_check_status)) {
            $result = true;
        }

        $shippings_to_check = array(
            'russian_post',
            'ems',
            'edost',
        );
        $russian_shippings = db_get_fields("SELECT service_id FROM ?:shipping_services WHERE module IN (?a)", $shippings_to_check);
        if (!empty($russian_shippings)) {
            $result = true;
        }

        return $result;
    }

    private static function _removeRussianServices($store_data)
    {
        if (isset($store_data['russian_edition']) && $store_data['russian_edition'] !== true) {
            $addons = db_get_array("SELECT addon FROM ?:addons");

            if (in_array('exim_1c', $addons)) {
                db_query("ALTER TABLE ?:categories DROP external_id");
                db_query("ALTER TABLE ?:products DROP external_id");
                db_query("ALTER TABLE ?:product_features DROP external_id");
                db_query("ALTER TABLE ?:product_option_variants DROP external_id");
                db_query("ALTER TABLE ?:product_options_inventory DROP external_id");
                db_query("ALTER TABLE ?:users DROP external_id");
            }
            if (in_array('loginza', $addons)) {
                db_query("ALTER TABLE ?:users DROP loginza_identifier");
            }
            if (in_array('yandex_market', $addons)) {
                db_query("ALTER TABLE `?:products` DROP `yml_brand`, DROP `yml_origin_country`, DROP `yml_store`, DROP `yml_pickup`, DROP `yml_delivery`, DROP `yml_bid`, DROP `yml_cbid`");
            }
            $filename = Registry::get('config.dir.addons') . 'store_import/database/remove_russian_services.sql';
            if (file_exists($filename)) {
                db_import_sql_file($filename, 16384, true, true, false, false, false, true);
            }
        }
    }

    public static function copyPresetImages()
    {
        $theme_name = Registry::get('config.base_theme');
        $presets_path = fn_get_theme_path('[themes]/' . $theme_name . '/presets/data', 'C');
        $preset_images_path = fn_get_theme_path('[themes]/' . $theme_name . '/media/images/patterns', 'C');
        $files = fn_get_dir_contents($presets_path, false, true);

        foreach ($files as $file) {
            $content = fn_get_contents($presets_path . '/' . $file);
            if (preg_match('/@general_bg_image\: url\(["]?(.*?)["]?\)/', $content, $m)) {
                $image_name = fn_basename($m[1]);
                if (strpos($image_name, '?') !== false) {
                    list($image_name) = explode('?', $image_name);
                }
                if (file_exists($preset_images_path . '/' . $image_name)) {
                    $preset_dir = str_replace('.less', '', $file);

                    $new_path = $preset_images_path . '/' . $preset_dir;
                    fn_mkdir($new_path);
                    fn_copy($preset_images_path . '/' . $image_name, $new_path);

                    $content = str_replace($image_name, $preset_dir . '/' . $image_name, $content);
                    fn_put_contents($presets_path . '/' . $file, $content);
                }
            }
        }

        return true;
    }

    public static function validateStoreImportSettings($path = '', $store_data = array())
    {
        $result = true;
        $config = self::getConfig($path);
        if ($config !== false) {
            $store_data = array('path' => $path);
            $store_data = fn_array_merge($store_data, $config);
            $store_data['new_storefront_url'] = str_replace('http://', '', Registry::get('config.http_location'));
            $store_data['new_secure_storefront_url'] = str_replace('https://', '', Registry::get('config.https_location'));
            if (!self::checkEditionMapping($store_data)) {
                fn_set_notification('E', __('error'), __('store_import.edition_mapping_failed'));
                $result = false;
            } elseif (!self::testDatabaseConnection($store_data)) {
                fn_set_notification('E', __('error'), __('store_import.cannot_connect_to_database_server'));
                $result = false;
            } elseif (!self::checkLicense($store_data)) {
                fn_set_notification('E', __('error'), __('store_import.invalid_license'));
                $result = false;
            }
            $store_data['russian_edition'] = self::checkRussianEdition($store_data);
        } else {
            fn_set_notification('E', __('error'), __('store_import.this_is_not_cart_path'));
            $result = false;
        }

        return array($store_data, $result);
    }

    public static function convertScrollerBlocks()
    {
        $blocks = db_get_array("SELECT * FROM ?:bm_blocks WHERE properties LIKE '%products_scroller.tpl%'");
        $map = array(
            'slow' => '600',
            'normal' => '400',
            'fast' => '200',
        );
        if (!empty($blocks)) {
            foreach ($blocks as $block_data) {
                $block_data['properties'] = unserialize($block_data['properties']);
                if (isset($block_data['properties']['scroller_direction']) && ($block_data['properties']['scroller_direction'] == 'up' || $block_data['properties']['scroller_direction'] == 'down')) {
                    $block_data['properties'] = 'a:4:{s:8:"template";s:41:"blocks/products/products_multicolumns.tpl";s:11:"item_number";s:1:"N";s:17:"number_of_columns";s:1:"1";s:23:"hide_add_to_cart_button";s:1:"Y";}';
                    db_query("REPLACE INTO ?:bm_blocks ?e", $block_data);
                } elseif (isset($block_data['properties']['speed'])) {
                    $block_data['properties']['speed'] = $map[$block_data['properties']['speed']];
                    unset($block_data['properties']['scroller_direction']);
                    unset($block_data['properties']['easing']);
                    $block_data['properties'] = serialize($block_data['properties']);
                    db_query("REPLACE INTO ?:bm_blocks ?e", $block_data);
                }
            }
        }

        return true;
    }

    public static function addEsStates()
    {
        $main_lang_code = db_get_field("SELECT value FROM ?:settings_objects WHERE name = 'admin_default_language'");
        $lang_codes = db_get_fields("SELECT lang_code FROM ?:languages WHERE lang_code != ?s", $main_lang_code);
        $data = array(
            'C' => 'A Coruña',
            'VI' => 'Álava',
            'AB' => 'Albacete',
            'A' => 'Alicante',
            'AL' => 'Almería',
            'O' => 'Asturias',
            'AV' => 'Ávila',
            'BA' => 'Badajoz',
            'PM' => 'Baleares',
            'B' => 'Barcelona',
            'BU' => 'Burgos',
            'CC' => 'Cáceres',
            'CA' => 'Cádiz',
            'S' => 'Cantabria',
            'CS' => 'Castellón',
            'CE' => 'Ceuta',
            'CR' => 'Ciudad Real',
            'CO' => 'Córdoba',
            'CU' => 'Cuenca',
            'GI' => 'Girona',
            'GR' => 'Granada',
            'GU' => 'Guadalajara',
            'SS' => 'Guipúzcoa',
            'H' => 'Huelva',
            'HU' => 'Huesca',
            'J' => 'Jaén',
            'LO' => 'La Rioja',
            'GC' => 'Las Palmas',
            'LE' => 'León',
            'L' => 'Lleida',
            'LU' => 'Lugo',
            'M' => 'Madrid',
            'MA' => 'Málaga',
            'ML' => 'Melilla',
            'MU' => 'Murcia',
            'NA' => 'Navarra',
            'OR' => 'Ourense',
            'P' => 'Palencia',
            'PO' => 'Pontevedra',
            'SA' => 'Salamanca',
            'TF' => 'Santa Cruz de Tenerife',
            'SG' => 'Segovia',
            'SE' => 'Sevilla',
            'SO' => 'Soria',
            'T' => 'Tarragona',
            'TE' => 'Teruel',
            'TO' => 'Toledo',
            'V' => 'Valencia',
            'VA' => 'Valladolid',
            'BI' => 'Vizcaya',
            'ZA' => 'Zamora',
            'Z' => 'Zaragoza',
        );

        foreach ($data as $state_code => $state_name) {
            $old_state_id = db_get_field("SELECT state_id FROM ?:states WHERE country_code = 'ES' AND code = ?s", $state_code);
            $state_id = db_query("REPLACE INTO ?:states (`country_code`, `code`, `status`) VALUES ('ES', ?s, 'A')", $state_code);
            db_query("REPLACE INTO ?:state_descriptions (`state_id`, `lang_code`, `state`) VALUES (?i, ?s, ?s)", $state_id, $main_lang_code, $state_name);
            db_query("UPDATE ?:destination_elements SET element = ?i WHERE element = ?i AND element_type = 'S'", $state_id, $old_state_id);
        }
    }

    public static function addIpv6Support($page_size = 50)
    {
        db_query("CREATE TABLE IF NOT EXISTS ?:ipv6_temp_orders (`order_id` mediumint(8) unsigned NOT NULL,`ip_address` varchar(40) DEFAULT NULL,PRIMARY KEY (`order_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        db_query("INSERT INTO ?:ipv6_temp_orders (SELECT order_id, ip_address FROM ?:orders)");
        db_query("ALTER TABLE ?:orders CHANGE `ip_address` `ip_address` VARBINARY(40) NOT NULL DEFAULT ''");
        db_query("ALTER TABLE ?:user_session_products CHANGE `ip_address` `ip_address` VARBINARY(40) NOT NULL DEFAULT ''");
        $stop = false;
        $page = 0;
        while ($stop === false) {
            $page++;
            $limit_to = $page * $page_size;
            $limit_from = $limit_to - $page_size;
            $original_addresses = db_get_array("SELECT `order_id`, `ip_address` FROM ?:ipv6_temp_orders LIMIT ?i, ?i", $limit_from, $limit_to);
            if (sizeof($original_addresses) < $page_size) {
                $stop = true;
            }
            foreach ($original_addresses as $order_address) {
                $order_address['ip_address'] = fn_ip_to_db($order_address['ip_address']);
                db_query("UPDATE ?:orders SET ip_address = ?s WHERE order_id = ?i", $order_address['ip_address'], $order_address['order_id']);
            }
        }
        db_query("DROP TABLE ?:ipv6_temp_orders");
    }

    public static function checkDesignChanges($store_data)
    {
        $result = true;

        foreach (fn_get_installed_themes() as $theme_name) {
            $theme = Themes::factory($theme_name);
            $theme_manifest = $theme->getManifest();
            if (!empty($theme_manifest['converted_to_css']) && $theme_manifest['converted_to_css'] == true) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    public static function setCustomCssClassForResponsive()
    {
        $default_location_ids = db_get_fields("SELECT location_id FROM ?:bm_locations WHERE is_default = 1");
        if (!empty($default_location_ids)) {
            foreach ($default_location_ids as $default_location_id) {
                db_query("UPDATE ?:bm_containers SET user_class = 'ty-footer-grid__full-width ty-footer-menu' WHERE location_id = ?i AND position = 'FOOTER'", $default_location_id);
            }
        }
    }

    public static function addCheckoutSection()
    {
        $new_section_id = 6;
        $new_settings = array(
            array(
                'name' => 'display_sign_in_step',
                'edition_type' => 'ROOT,ULT:VENDOR',
                'description' => 'Display "Sign in" step',
                'position' => 310,
                'type' => 'C',
                'value' => 'Y',
                'object_id' => 300,
            ),
            array(
                'name' => 'display_shipping_step',
                'edition_type' => 'ULT:ROOT,ULT:VENDOR',
                'description' => 'Display "Shipping method" step (the first active method will be used by default)',
                'position' => 320,
                'type' => 'C',                                                                                                                                                                                             
                'value' => 'Y',
                'object_id' => 301,
            ),
            array(
                'name' => 'display_payment_step',
                'edition_type' => 'ROOT,ULT:VENDOR',
                'description' => 'Display "Payment method" step (the first active method will be used by default)',
                'position' => 330,                                                                                                                                                                                                                                                                                                    'type' => 'C',
                'value' => 'Y',
                'object_id' => 302,
            ),
        );

//        $section_exists = db_get_row("SELECT * FROM ?:settings_sections WHERE section_id = " . $new_section_id);
        $section_exists = true;
        $new_section_id = $section_exists ? 'null' : $new_section_id;

        db_query("
            INSERT INTO ?:settings_sections (section_id, parent_id, edition_type, name, position, type)
            VALUES ($new_section_id, 0, 'ROOT,VENDOR', 'Checkout', 40, 'CORE');
        ");
        $section = db_get_row("SELECT * FROM ?:settings_sections WHERE name = 'Checkout' AND type = 'CORE'");
        $section_id = $section['section_id'];

        db_query("UPDATE ?:settings_objects SET section_id = $section_id WHERE name = 'disable_anonymous_checkout' AND is_global = 'Y'");
        db_query("UPDATE ?:settings_objects SET section_id = $section_id WHERE name = 'address_position' AND is_global = 'Y'");
        db_query("UPDATE ?:settings_objects SET section_id = $section_id WHERE name = 'agree_terms_conditions' AND is_global = 'Y'");
        db_query("UPDATE ?:settings_objects SET section_id = $section_id WHERE name = 'repay' AND is_global = 'Y'");
        db_query("UPDATE ?:settings_objects SET section_id = $section_id WHERE name = 'allow_create_account_after_order' AND is_global = 'Y'");

        foreach ($new_settings as &$setting) {
//            $option_exists = db_get_row("SELECT * FROM ?:settings_objects WHERE object_id = " . $setting['object_id']);
            $option_exists = true;
            $object_id = $option_exists ? 'null' : $setting['object_id'];
            db_query(
                "INSERT INTO ?:settings_objects (object_id, name, edition_type, section_id, type, value, position, is_global) VALUES ($object_id, '"
                . implode("', '", array($setting['name'], $setting['edition_type'], $section_id, $setting['type'], $setting['value'], $setting['position'], 'Y'))
                . "')"
            );
            $option = db_get_row("SELECT * FROM ?:settings_objects WHERE name = '" . $setting['name'] . "' AND section_id = " . $section_id);
            $setting['object_id'] = $option['object_id'];
            $lang_codes = db_get_fields("SELECT lang_code FROM ?:languages");
            foreach ($lang_codes as $lang_code) {
                db_query("REPLACE INTO ?:settings_descriptions (object_id, object_type, lang_code, value, tooltip) VALUES (" . $section_id . ", 'S', '" . $lang_code . "', 'Checkout', '')");
                db_query("REPLACE INTO ?:settings_descriptions (object_id, object_type, lang_code, value, tooltip) VALUES (" . $setting['object_id'] . ", 'O', '" . $lang_code . "', '" . $setting['description'] . "', '')");
            }
        }
    }

    public static function coreCheckoutSettings()
    {
        $settings_objects = array(
            array(
                'previos_name' => 'display_sign_in_step',
                'name' => 'configure_sign_in_step',
                'previos_description' => 'Display "Sign in" step',
                'description' => 'Configure "Sign in" step',
                'previos_type' => 'C',
                'type' => 'S',
                'previos_value' => 'Y',
                'value' => 'returning_customer_first',
                'variants' => array(
                    array(
                        'name' => 'returning_customer_first',
                        'description' => 'Display "Returning customer" first',
                        'position' => 10,
                        'variant_id' => 220,
                    ),
                    array(
                        'name' => 'new_customer_first',
                        'description' => 'Display "New customer" first',
                        'position' => 20,
                        'variant_id' => 221,
                    ),
                    array(
                        'name' => 'hide',
                        'description' => 'Hide step completely',
                        'position' => 30,
                        'variant_id' => 222,
                    ),
                ),
            ),
            array(
                'object_id' => 305,
                'name' => 'sign_in_default_action',
                'description' => 'Default option for the "New customer" section at the "Sign in"',
                'type' => 'S',
                'value' => 'register',
                'edition_type' => 'ROOT,ULT:VENDOR',
                'position' => '315',
                'variants' => array(
                    array(
                        'name' => 'register',
                        'description' => 'Register',
                        'position' => 10,
                        'variant_id' => 223,
                    ),
                    array(
                        'name' => 'checkout_as_guest',
                        'description' => 'Checkout as guest',
                        'position' => 20,
                        'variant_id' => 224,
                    ),
                ),
            ),
        );

        $section = db_get_row("SELECT * FROM ?:settings_sections WHERE name = 'Checkout' AND type = 'CORE'");
        $section_id = $section['section_id'];
        $lang_codes = db_get_fields("SELECT lang_code FROM ?:languages");

        foreach ($settings_objects as $object) {
            $update = false;
            if (!empty($object['previos_name'])) {
                $_object = db_get_row(sprintf("SELECT * FROM ?:settings_objects WHERE name IN('%s', '%s') AND section_id = %s",
                    $object['previos_name'], $object['name'], $section_id
                ));
                $object['object_id'] = $_object['object_id'];
                $update = true;
            }

            if ($update) {
                db_query(sprintf("UPDATE ?:settings_objects SET name = '%s', type = '%s', value = '%s' WHERE object_id = %s",
                    $object['name'], $object['type'], $object['value'], $object['object_id']
                ));
                db_query(sprintf("UPDATE ?:settings_descriptions SET value = '%s' WHERE object_type = '%s' AND object_id = %s",
                    $object['description'], 'O', $object['object_id']
                ));
            } else {
                $_object = db_get_row("SELECT * FROM ?:settings_objects WHERE object_id = " . $object['object_id']);
                $_object = true;
                $object_id = $_object ? 'null' : $object['object_id'];

                db_query(
                    "INSERT INTO ?:settings_objects (object_id, name, edition_type, section_id, type, value, position, is_global) VALUES ($object_id, '"
                    . implode("', '", array($object['name'], $object['edition_type'], $section_id, $object['type'], $object['value'], $object['position'], 'Y'))
                    . "')"
                );
                $_object = db_get_row("SELECT * FROM ?:settings_objects WHERE name = '" . $object['name'] . "' AND section_id = " . $section_id);
                $object['object_id'] = $_object['object_id'];
                foreach ($lang_codes as $lang_code) {
                    db_query("REPLACE INTO ?:settings_descriptions (object_id, object_type, lang_code, value, tooltip) VALUES (" . $object['object_id'] . ", 'O', '" . $lang_code . "', '" . $object['description'] . "', '')");
                }
            }

            foreach ($object['variants'] as &$variant) {
                $variant_exists = db_get_row("SELECT * FROM ?:settings_variants WHERE variant_id = " . $variant['variant_id']);
                $variant_exists = true;
                $variant_id = $variant_exists ? 'null' : $variant['variant_id'];

                db_query(
                    "INSERT INTO ?:settings_variants (variant_id, object_id, name, position) VALUES ({$variant_id}, '"
                    . implode("', '", array($object['object_id'], $variant['name'], $variant['position']))
                    . "')"
                );
                $_variant = db_get_row("SELECT * FROM ?:settings_variants WHERE object_id = " . $object['object_id'] . " AND name = '" . $variant['name'] . "'");
                $variant['variant_id'] = $_variant['variant_id'];
                foreach ($lang_codes as $lang_code) {
                    db_query("REPLACE INTO ?:settings_descriptions (object_id, object_type, lang_code, value, tooltip) VALUES (" . $variant['variant_id'] . ", 'V', '" . $lang_code . "', '" . $variant['description'] . "', '')");
                }
            }
        }
    }

    public static function ultProcessImages($store_data)
    {
        $skin_name = $store_data['skin_name'];
        $exclude_files = array('.htaccess', 'index.php');
        $img_path_info = Registry::get('config.storage.images');
        fn_copy($store_data['path'] . '/images', $img_path_info['dir'] . '/' . $img_path_info['prefix'], true, $exclude_files);

        //process logos
        $logos_exist = db_get_array("SELECT image_id FROM ?:images_links WHERE object_type = 'logos'");
        if (!$logos_exist) {
            $companies = db_get_fields("SELECT company_id FROM ?:companies");
            foreach ($companies as $company_id) {
                $layout_id = db_get_field("SELECT layout_id FROM ?:bm_layouts WHERE is_default = '1' AND company_id = ?i", $company_id);
                $manifest_path = $store_data['path'] . '/stores/' . $company_id . '/skins/' . $skin_name . '/manifest.ini';
                if (!file_exists($manifest_path)) {
                    //hack for the upgrade from PRO versions.
                    $manifest_path = $store_data['path'] . '/skins/' . $skin_name . '/manifest.ini';
                }
                $manifest = parse_ini_file($manifest_path, true);

                $theme_img_path = $store_data['path'] . '/stores/' . $company_id . '/skins/' . $skin_name . '/customer/images/' . $manifest['Customer_logo']['filename'];
                if (!file_exists($theme_img_path)) {
                    $theme_img_path = $store_data['path'] . '/skins/' . $skin_name . '/customer/images/' . $manifest['Customer_logo']['filename'];
                }
                General::createLogo($company_id, $layout_id, $manifest['Customer_logo']['filename'], $theme_img_path, 'theme', 'Customer_logo');

                $favicon_img_path = $store_data['path'] . '/stores/' . $company_id . '/skins/' . $skin_name . '/customer/images/icons/favicon.ico';
                if (!file_exists($favicon_img_path)) {
                    $favicon_img_path = $store_data['path'] . '/skins/' . $skin_name . '/customer/images/icons/favicon.ico';
                }
                General::createLogo($company_id, $layout_id, 'favicon.ico', $favicon_img_path, 'favicon');

                $mail_img_path = $store_data['path'] . '/stores/' . $company_id . '/skins/' . $skin_name . '/mail/images/' . $manifest['Mail_logo']['filename'];
                if (!file_exists($mail_img_path)) {
                    $mail_img_path = $store_data['path'] . '/skins/' . $skin_name . '/mail/images/' . $manifest['Mail_logo']['filename'];
                }
                General::createLogo($company_id, 0, $manifest['Mail_logo']['filename'], $mail_img_path, 'mail', 'Mail_logo');
                if (isset($manifest['Gift_certificate_logo'])) {
                    $gc_img_path = $store_data['path'] . '/stores/' . $company_id . '/skins/' . $skin_name . '/mail/images/' . $manifest['Gift_certificate_logo']['filename'];
                    if (!file_exists($gc_img_path)) {
                        $gc_img_path = $store_data['path'] . '/skins/' . $skin_name . '/mail/images/' . $manifest['Gift_certificate_logo']['filename'];
                    }
                    General::createLogo($company_id, 0, $manifest['Gift_certificate_logo']['filename'], $gc_img_path, 'gift_cert', 'Gift_certificate_logo');
                }
            }
            db_query("DELETE FROM ?:common_descriptions WHERE object_holder IN ('Customer_logo', 'Mail_logo', 'Admin_logo', 'Gift_certificate_logo')");
        }

        return true;
    }

    public static function mveProcessImages($store_data)
    {
        $skin_name = $store_data['skin_name'];
        $exclude_files = array('.htaccess', 'index.php');
        $img_path_info = Registry::get('config.storage.images');
        fn_copy($store_data['path'] . '/images', $img_path_info['dir'] . '/' . $img_path_info['prefix'], true, $exclude_files);
        $logos_exist = db_get_array("SELECT image_id FROM ?:images_links WHERE object_type = 'logos'");
        if (!$logos_exist) {
            $root_manifest = parse_ini_file($store_data['path'] . '/skins/' . $skin_name . '/manifest.ini', true);
            $layout_id = db_get_field("SELECT layout_id FROM ?:bm_layouts WHERE is_default = '1'");
            $root_company_logo_path = $store_data['path'] . '/skins/' . $skin_name . '/customer/images/' . $root_manifest['Customer_logo']['filename'];
            General::createLogo(0, $layout_id, $root_manifest['Customer_logo']['filename'], $root_company_logo_path, 'theme', 'Customer_logo');

            $root_company_favicon_img_path = $store_data['path'] . '/skins/' . $skin_name . '/customer/images/icons/favicon.ico';
            General::createLogo(0, $layout_id, 'favicon.ico', $root_company_favicon_img_path, 'favicon');

            $root_company_mail_img_path = $store_data['path'] . '/skins/' . $skin_name . '/mail/images/' . $root_manifest['Mail_logo']['filename'];
            General::createLogo(0, 0, $root_manifest['Mail_logo']['filename'], $root_company_mail_img_path, 'mail', 'Mail_logo');

            if (isset($root_manifest['Gift_certificate_logo'])) {
                $root_company_gc_img_path = $store_data['path'] . '/skins/' . $skin_name . '/mail/images/' . $root_manifest['Gift_certificate_logo']['filename'];
                General::createLogo(0, 0, $root_manifest['Gift_certificate_logo']['filename'], $root_company_gc_img_path, 'gift_cert', 'Gift_certificate_logo');
            }

            $company_data = db_get_array("SELECT company_id, logos FROM ?:companies");
            foreach ($company_data as $company) {
                $manifest = !empty($company['logos']) ? unserialize($company['logos']) : array();

                $theme_img_path = !empty($manifest['Customer_logo']) ? $store_data['path'] . '/images/' . $manifest['Customer_logo']['filename'] : $root_company_logo_path;
                $theme_name = !empty($manifest['Customer_logo']) ? $manifest['Customer_logo']['filename'] : $root_manifest['Customer_logo']['filename'];
                General::createLogo($company['company_id'], 0, fn_basename($theme_name), $theme_img_path, 'theme', 'Customer_logo');

                $mail_img_path = !empty($manifest['Mail_logo']) ? $store_data['path'] . '/images/' . $manifest['Mail_logo']['filename'] : $root_company_mail_img_path;
                $mail_logo_name = !empty($manifest['Mail_logo']) ? $manifest['Mail_logo']['filename'] : $root_manifest['Mail_logo']['filename'];
                General::createLogo($company['company_id'], 0, fn_basename($mail_logo_name), $mail_img_path, 'mail', 'Mail_logo');

                if (isset($root_manifest['Gift_certificate_logo'])) {
                    $gc_img_path = !empty($manifest['Gift_certificate_logo']) ? $store_data['path'] . '/images/' . $manifest['Gift_certificate_logo']['filename'] : $root_company_gc_img_path;
                    $gc_name = !empty($manifest['Gift_certificate_logo']) ? $manifest['Gift_certificate_logo']['filename'] : $root_manifest['Gift_certificate_logo']['filename'];
                    General::createLogo($company['company_id'], 0, fn_basename($gc_name), $gc_img_path, 'gift_cert', 'Gift_certificate_logo');
                }
            }
            db_query("DELETE FROM ?:common_descriptions WHERE object_holder IN ('Customer_logo', 'Mail_logo', 'Admin_logo', 'Gift_certificate_logo')");
        }

        return true;
    }

    public static function removeNullTextFields()
    {
        db_query("ALTER TABLE ?:bm_blocks CHANGE `properties` `properties` text");
        db_query("ALTER TABLE ?:bm_locations CHANGE `object_ids` `object_ids` text");
        db_query("ALTER TABLE ?:bm_locations CHANGE `custom_html` `custom_html` text");
        db_query("ALTER TABLE ?:bm_block_statuses CHANGE `object_ids` `object_ids` text");
        db_query("ALTER TABLE ?:categories CHANGE `selected_views` `selected_views` text");
        db_query("ALTER TABLE ?:exim_layouts CHANGE `cols` `cols` text");
        db_query("ALTER TABLE ?:exim_layouts CHANGE `options` `options` text");
        db_query("ALTER TABLE ?:views CHANGE `params` `params` text");
        db_query("ALTER TABLE ?:views CHANGE `view_results` `view_results` text");
        db_query("ALTER TABLE ?:logs CHANGE `content` `content` text");
        db_query("ALTER TABLE ?:logs CHANGE `backtrace` `backtrace` text");
        db_query("ALTER TABLE ?:orders CHANGE `notes` `notes` text");
        db_query("ALTER TABLE ?:orders CHANGE `details` `details` text");
        db_query("ALTER TABLE ?:orders CHANGE `promotions` `promotions` text");
        db_query("ALTER TABLE ?:payments CHANGE `processor_params` `processor_params` text");
        db_query("ALTER TABLE ?:product_features CHANGE `categories_path` `categories_path` text");
        db_query("ALTER TABLE ?:product_filters CHANGE `categories_path` `categories_path` text");
        db_query("ALTER TABLE ?:product_options_exceptions CHANGE `combination` `combination` text");
        db_query("ALTER TABLE ?:settings_objects CHANGE `value` `value` text");
        db_query("ALTER TABLE ?:settings_objects CHANGE `handler` `handler` varchar(128) NOT NULL default ''");
        db_query("ALTER TABLE ?:shipping_rates CHANGE `rate_value` `rate_value` text");
        db_query("ALTER TABLE ?:shippings CHANGE `service_params` `service_params` text");
        db_query("ALTER TABLE ?:stored_sessions CHANGE `data` `data` text");
        db_query("ALTER TABLE ?:user_data CHANGE `data` `data` text");
        db_query("ALTER TABLE ?:user_session_products CHANGE `extra` `extra` text");
        db_query("ALTER TABLE ?:users CHANGE `birthday` `birthday` int(11) NOT NULL default 0");
        db_query("ALTER TABLE ?:promotions CHANGE `conditions` `conditions` text");
        db_query("ALTER TABLE ?:promotions CHANGE `bonuses` `bonuses` text");
        db_query("ALTER TABLE ?:promotions CHANGE `conditions_hash` `conditions_hash` text");
        db_query("ALTER TABLE ?:promotions CHANGE `users_conditions_hash` `users_conditions_hash` text");
        db_query("ALTER TABLE ?:shipments CHANGE `comments` `comments` mediumtext");
        db_query("ALTER TABLE ?:storage_data CHANGE `data` `data` mediumblob");
        db_query("ALTER TABLE ?:product_tabs CHANGE `product_ids` `product_ids` text");
        db_query("ALTER TABLE ?:companies CHANGE `countries_list` `countries_list` text");
        db_query("ALTER TABLE ?:companies CHANGE `categories` `categories` text");
        db_query("ALTER TABLE ?:companies CHANGE `shippings` `shippings` text");
        db_query("ALTER TABLE ?:companies CHANGE `logos` `logos` text");
        db_query("ALTER TABLE ?:companies CHANGE `request_account_data` `request_account_data` text");
        db_query("ALTER TABLE ?:settings_vendor_values CHANGE `value` `value` text");
        db_query("ALTER TABLE ?:category_descriptions CHANGE `description` `description` mediumtext");
        db_query("ALTER TABLE ?:category_descriptions CHANGE `age_warning_message` `age_warning_message` text");
        db_query("ALTER TABLE ?:common_descriptions CHANGE `description` `description` mediumtext");
        db_query("ALTER TABLE ?:status_descriptions CHANGE `email_header` `email_header` text");
        db_query("ALTER TABLE ?:payment_descriptions CHANGE `instructions` `instructions` text");
        db_query("ALTER TABLE ?:product_descriptions CHANGE `short_description` `short_description` mediumtext");
        db_query("ALTER TABLE ?:product_descriptions CHANGE `full_description` `full_description` mediumtext");
        db_query("ALTER TABLE ?:product_descriptions CHANGE `search_words` `search_words` text");
        db_query("ALTER TABLE ?:product_descriptions CHANGE `age_warning_message` `age_warning_message` text");
        db_query("ALTER TABLE ?:product_descriptions CHANGE `promo_text` `promo_text` mediumtext");
        db_query("ALTER TABLE ?:product_features_descriptions CHANGE `full_description` `full_description` mediumtext");
        db_query("ALTER TABLE ?:product_feature_variant_descriptions CHANGE `description` `description` mediumtext");
        db_query("ALTER TABLE ?:product_file_descriptions CHANGE `license` `license` text");
        db_query("ALTER TABLE ?:product_file_descriptions CHANGE `readme` `readme` text");
        db_query("ALTER TABLE ?:product_options_descriptions CHANGE `description` `description` mediumtext");
        db_query("ALTER TABLE ?:settings_descriptions CHANGE `value` `value` text");
        db_query("ALTER TABLE ?:settings_descriptions CHANGE `tooltip` `tooltip` text");
        db_query("ALTER TABLE ?:promotion_descriptions CHANGE `short_description` `short_description` text");
        db_query("ALTER TABLE ?:promotion_descriptions CHANGE `detailed_description` `detailed_description` mediumtext");
        db_query("ALTER TABLE ?:company_descriptions CHANGE `company_description` `company_description` text");
    }

    public static function filtersRefactoring()
    {
        db_query("DROP TABLE IF EXISTS ?:product_filter_ranges_descriptions");
        db_query("DROP TABLE IF EXISTS ?:product_filter_ranges");

        $lang_codes = db_get_array("SELECT lang_code FROM ?:languages");

        self::addBlock($lang_codes);
        self::createLocation($lang_codes);
    }

    private static function addBlock($lang_codes)
    {
        $block_data = array(
            'template' => 'blocks/product_filters/selected_filters.tpl'
        );

        $block_id = db_query("INSERT INTO ?:bm_blocks (type, properties) VALUES ('product_filters', '" . addslashes(serialize($block_data)) . "')");

        $block_content = array(
            'items' => array(
                'filling' => 'manually'
            )
        );

        foreach ($lang_codes as $lang_code) {
            db_query("INSERT INTO ?:bm_blocks_content (block_id, lang_code, content) VALUES (" . $block_id . ", '" . $lang_code['lang_code'] . "', '" . addslashes(serialize($block_content)) . "')");

            $name = $lang_code['lang_code'] == 'ru' ? 'Выбранные фильтры товаров' : 'Selected product filters';
            db_query("INSERT INTO ?:bm_blocks_descriptions (block_id, lang_code, name) VALUES (" . $block_id . ", '" . $lang_code['lang_code'] . "', '" . addslashes($name) . "')");
        }

        $location_ids = array();
        $block_ids = array();
        $blocks_data = db_get_array("SELECT block_id FROM ?:bm_blocks WHERE type = 'product_filters' AND properties LIKE '%blocks/product_filters/original.tpl%'");
        foreach ($blocks_data as $block) {
            $block_ids[] = $block['block_id'];
        }

        $grid_data = db_get_array("SELECT grid_id FROM ?:bm_snapping WHERE block_id IN (" . implode(',', $block_ids) . ")");
        foreach ($grid_data as $grid) {
            $container_data = db_get_row("SELECT container_id FROM ?:bm_grids WHERE grid_id = " . $grid['grid_id']);
            $location_data = db_get_row("SELECT location_id FROM ?:bm_containers WHERE container_id = " . $container_data['container_id']);
            $location_ids[$location_data['location_id']] = true;
            $location_ids[$location_data['location_id']] = true;
        }

        if (!empty($location_ids)) {
            $containers = db_get_array("SELECT container_id FROM ?:bm_containers WHERE location_id IN (" . implode(',', array_keys($location_ids)) . ")");

            $container_ids = array();
            foreach ($containers as $container) {
                $container_ids[] = $container['container_id'];
            }

            $grids = db_get_array("SELECT grid_id FROM ?:bm_grids WHERE container_id IN (" . implode(',', $container_ids) . ")");
            $grid_ids = array();
            foreach ($grids as $grid) {
                $grid_ids[] = $grid['grid_id'];
            }

            $bc_block_data = db_get_row("SELECT block_id FROM ?:bm_blocks WHERE type = 'breadcrumbs'");

            $bc_grids = db_get_array("SELECT grid_id FROM ?:bm_snapping WHERE block_id = " . $bc_block_data['block_id'] . " AND grid_id IN (" . implode(',', $grid_ids) .")");

            foreach ($bc_grids as $bc_grid) {
                db_query("INSERT INTO ?:bm_snapping (block_id, grid_id, `order`) VALUES (" . $block_id . ", " . $bc_grid['grid_id'] . ", 10)");
            }
        }
    }

    private static function createLocation($languages)
    {
        $layouts = db_get_array("SELECT * FROM ?:bm_layouts");
        foreach ($layouts as $layout) {
            $layout_id = $layout['layout_id'];

            $is_exists = db_get_row("SELECT * FROM ?:bm_locations WHERE dispatch = 'products.search' AND layout_id = " . $layout_id);

            if (!empty($is_exists)) {
                continue;
            }

            db_query("INSERT INTO ?:bm_locations (`dispatch`, `layout_id`, `position`) VALUES ('products.search', " . $layout_id . ", 140)");
            $location = db_get_row("SELECT * FROM ?:bm_locations WHERE dispatch = 'products.search' AND layout_id = " . $layout_id);

            foreach ($languages as $language) {
                $name = $language['lang_code'] == 'ru' ? 'Результаты поиска' : 'Search results';
                db_query("INSERT INTO ?:bm_locations_descriptions (`lang_code`, `name`, `title`, `meta_description`, `meta_keywords`, `location_id`) VALUES ('" . $language['lang_code'] . "', '" . $name . "', '', '', '', " . $location['location_id'] . ")");
            }

            foreach (array('TOP_PANEL', 'HEADER', 'CONTENT', 'FOOTER') as $position) {
                $use_default = $position !== 'CONTENT' ? 'Y' : 'N';
                db_query("INSERT INTO ?:bm_containers (`location_id`, `position`, `width`, `linked_to_default`) VALUES (" . $location['location_id'] . ", '" . $position . "', 16, '" . $use_default . "')");
            }

            $_containers = db_get_array("SELECT * FROM ?:bm_containers WHERE location_id = " . $location['location_id']);
            foreach ($_containers as $container) {
                $containers[$container['position']] = $container;
            }
            unset($_containers);

            $grid_id = db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (16, 'breadcrumbs-grid', 'A', 0, 1, 1, '', 'FULL_WIDTH', 'div', 1, " . $containers['CONTENT']['container_id'] . ", 0)");
            self::createBlock('breadcrumbs', 'a:1:{s:8:\"template\";s:22:\"common/breadcrumbs.tpl\";}', $grid_id, '', '', 2);
            self::createBlock('product_filters', 'a:1:{s:8:\"template\";s:43:\"blocks/product_filters/selected_filters.tpl\";}', $grid_id, '', '', 4);
            $grid_id = db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (4, 'side-grid', 'A', 0, 0, 1, '', 'FULL_WIDTH', 'div', 0, " . $containers['CONTENT']['container_id'] . ", 0)");
            self::createBlock('product_filters', 'a:1:{s:8:\"template\";s:35:\"blocks/product_filters/original.tpl\";}', $grid_id, 'blocks/wrappers/sidebox_general.tpl', '', 2);

            $grid_id = db_query("INSERT INTO ?:bm_grids (`width`, `user_class`, `status`, `offset`, `omega`, `alpha`, `wrapper`, `content_align`, `html_element`, `clear`, `container_id`, `parent_id`) VALUES (12, 'main-content-grid', 'A', 0, 1, 0, '', 'FULL_WIDTH', 'div', 1, " . $containers['CONTENT']['container_id'] . ", 0)");
            self::createBlock('main', '', $grid_id, 'blocks/wrappers/mainbox_general.tpl', '', 0);
        }
    }

    private static function createBlock($type, $properties, $grid_id, $wrapper, $user_class, $order)
    {
        $block_id = self::isBlockExists($type, $properties);
        db_query("INSERT INTO ?:bm_snapping (`block_id`, `grid_id`, `wrapper`, `user_class`, `order`, `status`) VALUES ({$block_id}, {$grid_id}, '{$wrapper}', '{$user_class}', {$order}, 'A')");

        return $block_id;
    }

    private static function isBlockExists($type, $properties)
    {
        $block = db_get_row("SELECT * FROM ?:bm_blocks WHERE type = '" . $type . "' AND properties = '" . $properties . "'");

        if (!empty($block['block_id'])) {
            return $block['block_id'];
        } else {
            return false;
        }
    }

    public static function removeNewsLogger()
    {
        $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'log_type_news' AND section_id = 12");
        if (!empty($option)) {
            db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
            db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
            $variants = db_get_array("SELECT variant_id FROM ?:settings_variants WHERE object_id = " . $option['object_id']);
            foreach ($variants as $variant) {
                db_query("DELETE FROM ?:settings_variants WHERE variant_id = " . $variant['variant_id']);
                db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'V' AND object_id = " . $variant['variant_id']);
            }
        }
    }

    public static function convertNewsToBlog()
    {
        $table_prefix = General::formatPrefix();
        $tables = self::getTables(Registry::get('config.db_name'), $table_prefix);
        $languages = db_get_array("SELECT lang_code FROM ?:languages");
        if (!in_array($table_prefix .'blog_authors', $tables)) {
            db_query("CREATE TABLE ?:blog_authors (
                `page_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
                `user_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`page_id`,`user_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
        }
        db_query("INSERT INTO ?:addons (addon, status, version, priority) VALUES ('blog', 'A', '1.0', 2400)");
        foreach ($languages as $language) {
            db_query("INSERT INTO ?:addon_descriptions (addon, name, description, lang_code) VALUES ('blog', 'Blog', 'Lets you start your blog easily', '" . $language['lang_code'] . "')");
        }
        if (in_array($table_prefix . 'news', $tables)) {
            $company_ids = array();
            $news = db_get_array("SELECT news_id, status, date, company_id FROM ?:news");
            foreach ($news as $news_data) {
                $company_ids[$news_data['company_id']] = 0;
            }

            $pos = 100;
            foreach ($company_ids as $company_id => $root) {
                db_query("INSERT INTO ?:pages (parent_id, status, page_type, timestamp, company_id, position) VALUES (0, 'A', 'B', " . time() . ", " . $company_id . ", " . $pos .")");
                $insert_info = db_get_row("SELECT LAST_INSERT_ID() as id");
                db_query("UPDATE ?:pages SET id_path = " . $insert_info['id'] . " WHERE page_id = " . $insert_info['id']);
                db_query("REPLACE INTO ?:blog_authors (page_id, user_id) VALUES (" . $insert_info['id'] . ", 1)");
                foreach ($languages as $language) {
                    db_query("INSERT INTO ?:page_descriptions (page_id, page, description, lang_code) VALUES (" . $insert_info['id'] . ", 'News', '', '" . $language['lang_code'] ."')");
                }
                $company_ids[$company_id] = $insert_info['id'];

                if (in_array($table_prefix . 'ult_objects_sharing', $tables)) {
                    db_query("INSERT INTO ?:ult_objects_sharing (`share_company_id`, `share_object_id`, `share_object_type`) VALUES (" . $company_id . ", " . $insert_info['id'] . ", 'pages')");
                }
            }

            foreach ($news as $news_data) {
                $pos += 100;
                db_query("INSERT INTO ?:pages (parent_id, status, page_type, timestamp, company_id, position) VALUES (" . $company_ids[$news_data['company_id']] . ", '" . $news_data['status'] . "', 'B', " . $news_data['date'] . ", " . $news_data['company_id'] . ", " . $pos . ")");
                $insert_info = db_get_row("SELECT LAST_INSERT_ID() as id");
                db_query("UPDATE ?:pages SET id_path = '" . $company_ids[$news_data['company_id']] . "/" . $insert_info['id'] . "' WHERE page_id = " . $insert_info['id']);

                db_query("REPLACE INTO ?:blog_authors (page_id, user_id) VALUES (" . $insert_info['id'] . ", 1)");

                $news_descriptions = db_get_array("SELECT * FROM ?:news_descriptions WHERE news_id = " . $news_data['news_id']);
                foreach ($news_descriptions as $desc) {
                    db_query("INSERT INTO ?:page_descriptions (page_id, page, description, lang_code) VALUES (" . $insert_info['id'] . ", '" . addslashes($desc['news']) . "', '" . addslashes($desc['description']) ."', '" . $desc['lang_code'] ."')");
                }
                if (in_array($table_prefix . 'ult_objects_sharing', $tables)) {
                    db_query("INSERT INTO ?:ult_objects_sharing (`share_company_id`, `share_object_id`, `share_object_type`) VALUES (" . $news_data['company_id'] . ", " . $insert_info['id'] . ", 'pages')");
                }

                if (in_array($table_prefix . 'seo_redirects', $tables)) {
                    $seo_names = db_get_array("SELECT name, lang_code FROM ?:seo_names WHERE object_id = " . $news_data['news_id'] . " AND type = 'n'");
                    if (!empty($seo_names)) {
                        $default = reset($seo_names);
                        $converted = array();
                        foreach ($languages as $language) {
                            $found = false;
                            foreach ($seo_names as $_name) {
                                if ($_name['lang_code'] == $language['lang_code']) {
                                    $found = true;
                                    $converted[$language['lang_code']] = $_name;
                                    break;
                                }
                            }

                            if (!$found) {
                                $converted[$language['lang_code']] = array(
                                    'name' => $default['name'],
                                    'lang_code' => $language['lang_code']
                                );
                            }
                        }

                        $seo_settings = db_get_row("SELECT value FROM ?:settings_objects WHERE name = 'seo_other_type'");

                        foreach ($languages as $language) {
                            $old_url = '/' . $converted[$language['lang_code']]['name'] . ($seo_settings['value'] == 'file' ? '.html' : '');

                            db_query("INSERT INTO ?:seo_redirects (src, type, object_id, company_id, lang_code) VALUES ('" . addslashes($old_url) ."', 'a', " . $insert_info['id'] . ", " . $news_data['company_id'] . ", '" . $language['lang_code'] . "')");
                        }
                    }
                }

                if (in_array($table_prefix . 'discussion', $tables)) {
                    db_query("UPDATE ?:discussion SET object_id = " . $insert_info['id'] . ", object_type = 'A' WHERE object_id = " . $news_data['news_id'] . " AND object_type = 'N'");
                }
            }

            if (in_array($table_prefix . 'seo_names', $tables)) {
                db_query("DELETE FROM ?:seo_names WHERE type = 'n'");
            }

            db_query("DROP TABLE ?:news");
            db_query("DROP TABLE ?:news_descriptions");
        }

        $news = db_get_array("SELECT * FROM ?:bm_blocks WHERE type = 'news'");
        $blog_page = db_get_row("SELECT page_id FROM ?:pages WHERE page_type = 'B' AND parent_id = 0 LIMIT 1");
        if (!empty($news)) {
            foreach ($news as $news_block) {
                $props = unserialize($news_block['properties']);
                $props['filling'] = 'blog.text_links';
                $props['template'] = 'addons/blog/blocks/text_links.tpl';

                db_query("UPDATE ?:bm_blocks SET type = 'blog', properties = '" . addslashes(serialize($props)) . "' WHERE block_id = " . $news_block['block_id']);

                $settings = db_get_array("SELECT * FROM ?:bm_blocks_content WHERE block_id = " . $news_block['block_id']);
                if (!empty($settings)) {
                    foreach ($settings as $settings_data) {
                        $content = unserialize($settings_data['content']);
                        if (!empty($content)) {
                            $content = array(
                                'items' => array(
                                    'filling' => 'blog.text_links',
                                    'parent_page_id' => $blog_page['page_id'],
                                    'limit' => 3
                                )
                            );

                            db_query("UPDATE ?:bm_blocks_content SET content = '" . addslashes(serialize($content)) . "' WHERE block_id = " . $settings_data['block_id'] . " AND snapping_id = " . $settings_data['snapping_id'] . " AND lang_code = '" . $settings_data['lang_code'] ."' AND object_id = " . $settings_data['object_id'] . " AND object_type = '" . $settings_data['object_type'] . "'");
                        }
                    }
                }
            }
        }
    }

    public static function pagesRemoveShowInPopup()
    {
        db_query("ALTER TABLE ?:pages DROP COLUMN show_in_popup");
    }

    public static function removeTplDebugSetting()
    {
        $data = db_get_row("SELECT object_id FROM ?:settings_objects WHERE `name` = 'debugging_console'");

        if (isset($data['object_id'])) {
            $id = (int)$data['object_id'];

            db_query("DELETE FROM ?:settings_objects WHERE object_id = {$id}");
            db_query("DELETE FROM ?:settings_descriptions WHERE object_id = {$id} AND object_type = 'O'");
        }
    }

    public static function revertSearchFieldBlock()
    {
        $template_blocks = db_get_array("SELECT * FROM ?:bm_blocks WHERE `type` = 'search_field'");
        foreach ($template_blocks as &$block) {
            $properties = @unserialize($block['properties']);
            if (isset($properties['template']) && $properties['template'] == 'blocks/search_field.tpl') {
                $block['type'] = 'template';
                $properties['template'] = 'blocks/static_templates/search.tpl';
                $block['properties'] = @serialize($properties);

                db_query("REPLACE INTO ?:bm_blocks (`block_id`, `type`, `properties`, `company_id`) VALUES ({$block['block_id']}, '{$block['type']}', '{$block['properties']}', {$block['company_id']});");
            }
        }
    }

    public static function setForcedCompanyId()
    {
        $companies = db_get_fields('SELECT company_id FROM ?:companies');
        Registry::set('runtime.forced_company_id', reset($companies));

        return true;
    }

    public static function addFullHttps()
    {
        $secure_checkout = db_get_row("SELECT object_id, value FROM ?:settings_objects WHERE name = 'secure_checkout' AND section_tab_id = 0");
        $secure_auth = db_get_row("SELECT object_id, value FROM ?:settings_objects WHERE name = 'secure_auth' AND section_tab_id = 0");

        if ($secure_checkout) {
            $id = $secure_checkout['object_id'];

            $value = ($secure_checkout['value'] == 'Y' || $secure_auth['value'] == 'Y') ? 'partial' : 'none';

            db_query("UPDATE ?:settings_objects SET name = 'secure_storefront', type = 'S', value = '$value' WHERE object_id = $id");
            db_query("INSERT INTO ?:settings_variants (`object_id`, `name`, `position`) VALUES ($id, 'none', 10)");
            db_query("INSERT INTO ?:settings_variants (`object_id`, `name`, `position`) VALUES ($id, 'partial', 20)");
            db_query("INSERT INTO ?:settings_variants (`object_id`, `name`, `position`) VALUES ($id, 'full', 30)");
        }

        if ($secure_auth) {
            $id = $secure_auth['object_id'];
            db_query("DELETE FROM ?:settings_objects WHERE object_id = $id");
            db_query("DELETE FROM ?:settings_descriptions WHERE object_id = $id AND object_type = 'O'");
        }
    }

    public static function removeUseEmailAsLoginSetting()
    {
        $setting_object = db_get_row("SELECT * FROM ?:settings_objects WHERE `name` = 'use_email_as_login';");

        if (!empty($setting_object['object_id'])) {
            db_query("DELETE FROM ?:settings_objects WHERE `object_id` = {$setting_object['object_id']};");
        }
    }

    public static function removeUserLoginEximField()
    {
        $layouts = db_get_array("SELECT * FROM ?:exim_layouts WHERE `pattern_id` = 'users';");
        foreach ($layouts as $layout) {
            $fields = isset($layout['cols']) ? $layout['cols'] : '';
            $fields = explode(',', $fields);
            $found_login = false;

            foreach ($fields as $k => $field) {
                if (strpos($field, 'Login') !== false) {
                    $found_login = true;
                    unset($fields[$k]);
                    break;
                }
            }

            if ($found_login) {
                $layout['cols'] = implode(',', $fields);
                db_query("UPDATE ?:exim_layouts SET `cols` = '{$layout['cols']}' WHERE `layout_id` = {$layout['layout_id']};");
            }
        }
    }
}
