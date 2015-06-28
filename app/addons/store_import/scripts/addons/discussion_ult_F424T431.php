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

db_query("ALTER TABLE ?:discussion_messages CHANGE `message` `message` text");

$section = db_get_row("SELECT section_id FROM ?:settings_sections WHERE name = 'discussion'");
if (!empty($section)) {
    $tab = db_get_row("SELECT section_id FROM ?:settings_sections WHERE parent_id = " . $section['section_id'] . " AND name = 'news'");
    if (!empty($tab)) {
        db_query("DELETE FROM ?:settings_sections WHERE section_id = " . $tab['section_id']);
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'S' AND object_id = " . $tab['section_id']);

        $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'news_posts_per_page' AND section_id = " . $section['section_id']);
        if (!empty($option)) {
            db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
            db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
        }

        $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'news_post_approval' AND section_id = " . $section['section_id']);
        if (!empty($option)) {
            db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
            db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
            $variants = db_get_array("SELECT variant_id FROM ?:settings_variants WHERE object_id = " . $option['object_id']);
            foreach ($variants as $variant) {
                db_query("DELETE FROM ?:settings_variants WHERE variant_id = " . $variant['variant_id']);
                db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'V' AND object_id = " . $variant['variant_id']);
            }
        }

        $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'news_post_ip_check' AND section_id = " . $section['section_id']);
        if (!empty($option)) {
            db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
            db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
        }

        $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'news_notification_email' AND section_id = " . $section['section_id']);
        if (!empty($option)) {
            db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
            db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
        }

        $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'news_share_discussion' AND section_id = " . $section['section_id']);
        if (!empty($option)) {
            db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
            db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
        }
    }
}
