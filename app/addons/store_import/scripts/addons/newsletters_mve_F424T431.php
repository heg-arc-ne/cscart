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

db_query("ALTER TABLE ?:news_descriptions CHANGE `description` `description` mediumtext");
db_query("ALTER TABLE ?:newsletters CHANGE `users` `users` text");
db_query("ALTER TABLE ?:newsletter_links CHANGE `campaign_id` `campaign_id` mediumint(8) unsigned NOT NULL DEFAULT 0");
db_query("ALTER TABLE ?:newsletter_links CHANGE `newsletter_id` `newsletter_id` mediumint(8) unsigned NOT NULL DEFAULT 0");
db_query("ALTER TABLE ?:newsletter_descriptions CHANGE `newsletter_multiple` `newsletter_multiple` text");
db_query("ALTER TABLE ?:newsletter_descriptions CHANGE `body_html` `body_html` mediumtext");

db_query("UPDATE ?:privileges SET privilege = 'manage_newsletters' WHERE privilege = 'manage_news'");
db_query("UPDATE ?:privileges SET privilege = 'view_newsletters' WHERE privilege = 'view_news'");

db_query("UPDATE ?:usergroup_privileges SET privilege = 'manage_newsletters' WHERE privilege = 'manage_news'");
db_query("UPDATE ?:usergroup_privileges SET privilege = 'view_newsletters' WHERE privilege = 'view_news'");

db_query("UPDATE ?:addons SET addon = 'newsletters' WHERE addon = 'news_and_emails'");
db_query("UPDATE ?:settings_sections SET name = 'newsletters' WHERE name = 'news_and_emails' AND type = 'ADDON'");

db_query("UPDATE ?:addon_descriptions SET addon = 'newsletters', name = 'Newsletters', description = 'Lets you configure newsletter sending' WHERE addon = 'news_and_emails'");

$section = db_get_row("SELECT section_id FROM ?:settings_sections WHERE name = 'newsletters'");

if (!empty($section)) {

    $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'elm_sendmail_settings' AND section_id = " . $section['section_id']);
    if (!empty($option)) {
        db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
    }

    $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'mailer_sendmail_path' AND section_id = " . $section['section_id']);
    if (!empty($option)) {
        db_query("DELETE FROM ?:settings_objects WHERE object_id = " . $option['object_id']);
        db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'O' AND object_id = " . $option['object_id']);
    }

    $option = db_get_row("SELECT object_id FROM ?:settings_objects WHERE name = 'mailer_send_method' AND section_id = " . $section['section_id']);
    if (!empty($option)) {
        db_query("UPDATE ?:settings_objects SET value = 'mail' WHERE object_id = " . $option['object_id'] . " AND value = 'sendmail'");
        $variant = db_get_row("SELECT variant_id FROM ?:settings_variants WHERE name = 'sendmail' AND object_id = " . $option['object_id']);
        if (!empty($variant)) {
            db_query("DELETE FROM ?:settings_variants WHERE variant_id = " . $variant['variant_id']);
            db_query("DELETE FROM ?:settings_descriptions WHERE object_type = 'V' AND object_id = " . $variant['variant_id']);
        }
    }
}

$blocks = db_get_array("SELECT * FROM ?:bm_blocks WHERE properties LIKE '%news_and_emails/blocks/static_templates/subscribe.tpl%'");
if (!empty($blocks)) {
    foreach ($blocks as $block) {
        $props = unserialize($block['properties']);
        if ($props['template'] == 'addons/news_and_emails/blocks/static_templates/subscribe.tpl') {
            $props['template'] = 'addons/newsletters/blocks/static_templates/subscribe.tpl';
        }
        db_query("UPDATE ?:bm_blocks SET properties = '" . addslashes(serialize($props)) . "' WHERE block_id = " . $block['block_id']);
    }
}

$section = db_get_row("SELECT * FROM ?:settings_sections WHERE `name` = 'newsletters'");
if (!empty($section)) {
    $tab = db_get_row(
        "SELECT * FROM ?:settings_sections WHERE `name` = 'general' AND `parent_id` = {$section['section_id']} AND `type` = 'TAB'"
    );

    if (!empty($tab)) {
        $setting_mailer_smtp_ecrypted_connection = db_get_field("SELECT object_id FROM ?:settings_objects WHERE name = 'mailer_smtp_ecrypted_connection' AND section_id = {$section['section_id']}"); 
        if (empty($setting_mailer_smtp_ecrypted_connection)) {
            db_query(
                "INSERT INTO ?:settings_objects"
                . "(`name`, `section_id`, `section_tab_id`, `type`, `value`, `position`, `is_global`) VALUES"
                . "('mailer_smtp_ecrypted_connection', {$section['section_id']}, {$tab['section_id']}, 'S', 'none', 80, 'N');"
            );

            $setting_object = db_get_row(
                "SELECT * FROM ?:settings_objects WHERE `name` = 'mailer_smtp_ecrypted_connection' AND `section_id` = " .
                $section['section_id'] . " AND `section_tab_id` = " . $tab['section_id']
            );

            db_query(
                "INSERT INTO ?:settings_variants (`object_id`, `name`, `position`) VALUES"
                . "({$setting_object['object_id']}, 'none', 0);"
            );
            db_query(
                "INSERT INTO ?:settings_variants (`object_id`, `name`, `position`) VALUES"
                . "({$setting_object['object_id']}, 'tls', 10);"
            );
            db_query(
                "INSERT INTO ?:settings_variants (`object_id`, `name`, `position`) VALUES"
                . "({$setting_object['object_id']}, 'ssl', 20);"
            );

            $none_variant = db_get_row(
                "SELECT * FROM ?:settings_variants WHERE `object_id` = {$setting_object['object_id']} AND `name` = 'none'"
            );
            $tls_variant = db_get_row(
                "SELECT * FROM ?:settings_variants WHERE `object_id` = {$setting_object['object_id']} AND `name` = 'tls'"
            );
            $ssl_variant = db_get_row(
                "SELECT * FROM ?:settings_variants WHERE `object_id` = {$setting_object['object_id']} AND `name` = 'ssl'"
            );
        }    
    }
    $section_promotions = db_get_row("SELECT * FROM ?:settings_sections WHERE name = 'promotions' AND parent_id = {$section['section_id']}");
    if (empty($section_promotions)) {
        db_query(
            "INSERT INTO ?:settings_sections (parent_id, edition_type, name, position, type) VALUES"
            . "({$section['section_id']}, 'ROOT', 'promotions', 10, 'TAB');"
        );
    }

    $section_tab = db_get_row("SELECT * FROM ?:settings_sections WHERE name = 'promotions' AND parent_id = {$section['section_id']}");

    if (!empty($section_tab)) {
        $setting_coupon = db_get_field("SELECT object_id FROM ?:settings_objects WHERE name = 'coupon' AND section_id = {$section['section_id']}");
        if (empty($setting_coupon)) {
            db_query(
                "INSERT INTO ?:settings_objects (edition_type, name, section_id, section_tab_id, type, value, position, is_global, handler, parent_id) VALUES"
                . "('ROOT', 'coupon', {$section['section_id']}, {$section_tab['section_id']}, 'S', '', 0, 'N', '', 0);"
            );
        }
    }
}
