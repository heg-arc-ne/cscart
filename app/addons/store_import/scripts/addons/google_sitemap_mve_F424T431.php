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

$section = db_get_row("SELECT section_id FROM ?:settings_sections WHERE name = 'google_sitemap'");
if (!empty($section)) {
    $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'news_setting' AND section_id = " . $section['section_id']);
    if (!empty($option)) {
        db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
    }

    $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'include_news' AND section_id = " . $section['section_id']);
    if (!empty($option)) {
        db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
    }

    $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'news_change' AND section_id = " . $section['section_id']);
    if (!empty($option)) {
        db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
    }

    $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'news_priority' AND section_id = " . $section['section_id']);
    if (!empty($option)) {
        db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
    }
}
