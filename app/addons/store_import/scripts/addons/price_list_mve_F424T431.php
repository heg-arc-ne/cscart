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

$setting_exist = db_get_row("SELECT value FROM ?:settings_objects WHERE name = 'disable_images_in_xml'");
if (empty($setting_exist)) {
    db_query("INSERT INTO `?:settings_objects` (`edition_type`, `name`, `section_id`, `section_tab_id`, `type`, `value`, `position`, `is_global`, `handler`, `parent_id`) VALUES
        ('ROOT,ULT:VENDOR', 'disable_images_in_xml', (SELECT section_id FROM ?:settings_sections WHERE name = 'price_list'), (SELECT section_id FROM ?:settings_sections WHERE parent_id = (SELECT section_id FROM ?:settings_sections WHERE name = 'price_list')), 'C', 'N', 50, 'N', '', 0)
    ");
}
