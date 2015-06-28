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

$addon_status = db_get_row("SELECT * FROM ?:addons WHERE addon = 'social_buttons'");
if (!empty($addon_status)) {
    $gplus_size_status = db_get_field("SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_size'");
    if (empty($gplus_size_status)) {
        $section_id = db_get_row("SELECT section_id FROM ?:settings_sections WHERE name = 'social_buttons'");
        if (!empty($section_id)) {
            $section_id = $section_id['section_id'];
            db_query("INSERT INTO ?:settings_sections (`parent_id`,`edition_type`,`name`,`position`,`type`) VALUES ($section_id, 'ROOT,ULT:VENDOR', 'gplus', 30, 'TAB')");
            db_query("INSERT INTO ?:settings_sections (`parent_id`,`edition_type`,`name`,`position`,`type`) VALUES ($section_id, 'ROOT,ULT:VENDOR', 'pinterest', 40, 'TAB')");
            db_query("UPDATE ?:settings_sections SET position = 50 WHERE parent_id = $section_id AND name = 'email'");


            $gplus_section_id = db_get_row("SELECT section_id FROM ?:settings_sections WHERE name = 'gplus' AND parent_id = $section_id");
            $gplus_section_id = $gplus_section_id['section_id'];
            db_query("INSERT INTO ?:settings_objects (`edition_type`, `name`, `section_id`, `section_tab_id`, `type`, `value`, `position`, `is_global`, `handler`, `parent_id`) VALUES
                ('ROOT,ULT:VENDOR', 'gplus_header', $section_id, $gplus_section_id, 'H', '', 0, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'gplus_enable', $section_id, $gplus_section_id, 'C', 'N', 10, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'gplus_href', $section_id, $gplus_section_id, 'I', '', 20, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'gplus_size', $section_id, $gplus_section_id, 'S', 'standard', 30, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'gplus_annotation', $section_id, $gplus_section_id, 'S', 'bubble', 40, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'gplus_width', $section_id, $gplus_section_id, 'I', '', 50, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'gplus_align', $section_id, $gplus_section_id, 'S', 'left', 60, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'gplus_expandto', $section_id, $gplus_section_id, 'S', 'auto', 70, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'gplus_recommendations', $section_id, $gplus_section_id, 'S', 'yes', 80, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'gplus_display_on', $section_id, $gplus_section_id, 'N', '#M#products=Y', 90, 'N', '', 0);
            ");


            $pinterest_section_id = db_get_row("SELECT section_id FROM ?:settings_sections WHERE name = 'pinterest' AND parent_id = $section_id");
            $pinterest_section_id = $pinterest_section_id['section_id'];
            db_query("INSERT INTO ?:settings_objects (`edition_type`, `name`, `section_id`, `section_tab_id`, `type`, `value`, `position`, `is_global`, `handler`, `parent_id`) VALUES
                ('ROOT,ULT:VENDOR', 'pinterest_header', $section_id, $pinterest_section_id, 'H', '', 0, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'pinterest_enable', $section_id, $pinterest_section_id, 'C', 'N', 10, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'pinterest_size', $section_id, $pinterest_section_id, 'S', '20', 20, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'pinterest_shape', $section_id, $pinterest_section_id, 'S', 'rect', 30, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'pinterest_color', $section_id, $pinterest_section_id, 'S', 'gray', 40, 'N', '', 0),
                ('ROOT,ULT:VENDOR', 'pinterest_display_on', $section_id, $pinterest_section_id, 'N', '#M#products=Y', 50, 'N', '', 0);
            ");


            db_query("INSERT INTO ?:settings_variants (`object_id`, `name`, `position`) VALUES
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_size'), 'small', 0),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_size'), 'medium', 10),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_size'), 'standard', 20),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_size'), 'tall', 30),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_annotation'), 'none', 0),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_annotation'), 'bubble', 10),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_annotation'), 'inline', 20),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_align'), 'left', 0),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_align'), 'right', 10),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_expandto'), 'top', 0),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_expandto'), 'right', 10),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_expandto'), 'bottom', 20),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_expandto'), 'left', 30),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_recommendations'), 'yes', 0),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_recommendations'), 'no', 10),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_display_on'), 'products', 0),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'gplus_display_on'), 'pages', 10);
            ");


            db_query("INSERT INTO ?:settings_variants (`object_id`, `name`, `position`) VALUES
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'pinterest_size'), '20', 0),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'pinterest_size'), '28', 10),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'pinterest_shape'), 'rect', 0),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'pinterest_shape'), 'round', 10),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'pinterest_color'), 'gray', 0),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'pinterest_color'), 'red', 10),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'pinterest_color'), 'white', 20),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'pinterest_display_on'), 'products', 0),
                ((SELECT object_id FROM ?:settings_objects WHERE name = 'pinterest_display_on'), 'pages', 10);
            ");
        }
    }

    $gplus_expandto = db_get_row("SELECT * FROM ?:settings_objects WHERE name = 'gplus_expandto'");
    if ($gplus_expandto['value'] == 'auto') {
        db_query("UPDATE ?:settings_objects SET value = 'top' WHERE object_id = " . $gplus_expandto['object_id']);
    }
}
