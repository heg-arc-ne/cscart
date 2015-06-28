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

if (!defined('BOOTSTRAP')) { die('Access denied'); }

db_query("UPDATE ?:settings_objects SET name = 'paypal_status_map_settings', type = 'E' WHERE value = 'statuses_map.tpl'");
db_query("UPDATE ?:settings_objects SET name = 'paypal_logo_uploader_settings', type = 'E' WHERE value = 'logo_uploader.tpl'");

$section_ids = db_get_row("SELECT section_id, section_tab_id FROM ?:settings_objects WHERE name = 'paypal_ipn_settings'");
db_query("INSERT INTO ?:settings_objects (`edition_type`, `name`, `section_id`, `section_tab_id`, `type`, `value`, `position`, `is_global`, `handler`, `parent_id`) VALUES ('ROOT,ULT:VENDOR', 'paypal_status_map', ?i, ?i, 'H', '', 30, 'N', '', 0)", $section_ids['section_id'], $section_ids['section_tab_id']);
db_query("INSERT INTO ?:settings_objects (`edition_type`, `name`, `section_id`, `section_tab_id`, `type`, `value`, `position`, `is_global`, `handler`, `parent_id`) VALUES ('ROOT,ULT:VENDOR', 'paypal_logo_uploader', ?i, ?i, 'H', '', 50, 'N', '', 0)", $section_ids['section_id'], $section_ids['section_tab_id']);
$pp_settings = array(
    'paypal_ipn_settings' => 0,
    'override_customer_info' => 10,
    'test_mode' => 20,
    'paypal_status_map' => 30,
    'paypal_status_map_settings' => 40,
    'paypal_logo_uploader' => 50,
    'paypal_logo_uploader_settings' => 60,
    'pp_statuses' => 70,
);
foreach ($pp_settings as $pp_setting => $position) {
    db_query("UPDATE ?:settings_objects SET position = {$position} WHERE name = '{$pp_setting}' AND section_id = {$section_ids['section_id']}");
}

db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = "
    . "(SELECT object_id FROM ?:settings_objects WHERE name = 'test_mode' AND section_id = "
    . "(SELECT section_id FROM ?:settings_sections WHERE name = 'paypal'))"
);
db_query("DELETE FROM ?:settings_objects WHERE name = 'test_mode' AND section_id = "
    . "(SELECT section_id FROM ?:settings_sections WHERE name = 'paypal')"
);
