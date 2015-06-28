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

db_query("ALTER TABLE ?:call_requests CHANGE `notes` `notes` text");

$pm_setting_exist = db_get_row("SELECT * FROM ?:settings_objects WHERE name = 'phone_mask'");
if (empty($pm_setting_exist)) {
    $sections = db_get_row("SELECT * FROM ?:settings_objects WHERE name = 'buy_now_with_one_click'");
    db_query("INSERT INTO `?:settings_objects` (`edition_type`, `name`, `section_id`, `section_tab_id`, `type`, `value`, `position`, `is_global`, `handler`, `parent_id`) VALUES ('ROOT,ULT:VENDOR', 'phone_mask', {$sections['section_id']}, {$sections['section_tab_id']}, 'I', '', 40, 'N', '', 0);");
}
