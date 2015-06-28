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

db_query("ALTER TABLE ?:em_subscribers ADD `company_id` int(11) unsigned NOT NULL default '0'");
db_query("ALTER TABLE ?:em_subscribers DROP INDEX email, ADD UNIQUE KEY `email` (`email`,`company_id`)");

$core_section = db_get_row("SELECT * FROM ?:settings_sections WHERE name = 'email_marketing'");
$sub_sections = db_get_array("SELECT * FROM ?:settings_sections WHERE parent_id = " . $core_section['section_id']);

$section_ids = $core_section['section_id'];
foreach ($sub_sections as $sub_section) {
    $section_ids .= ',' . $sub_section['section_id'];
}

db_query("UPDATE ?:settings_sections SET edition_type = 'MVE:ROOT,ULT:VENDOR' WHERE section_id IN (" . $section_ids . ")");
db_query("UPDATE ?:settings_objects SET edition_type = 'MVE:ROOT,ULT:VENDOR' WHERE section_id IN (" . $section_ids . ")");

db_query("UPDATE ?:addons SET conflicts = 'newsletters' WHERE addon = 'email_marketing'");
