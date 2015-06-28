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

$section = db_get_row("SELECT * FROM ?:settings_sections WHERE name = 'bestsellers'");
if (empty($section)) {
    db_query("INSERT INTO ?:settings_sections (edition_type, name, type) VALUES ('ROOT,ULT:VENDOR', 'bestsellers', 'ADDON');");
}

$section = db_get_row("SELECT * FROM ?:settings_sections WHERE name = 'bestsellers'");

if ($section) {
    db_query(
        "INSERT INTO ?:settings_sections (parent_id, edition_type, name, type) VALUES (". $section['section_id'] .", 'ROOT,ULT:VENDOR', 'general', 'TAB');"
    );

    db_query(
        "INSERT INTO ?:settings_sections (parent_id, edition_type, name, type) VALUES (" . $section['section_id'] .",'ROOT,ULT:VENDOR', 'newest', 'TAB');"
    );

    $tab_general = db_get_row("SELECT * FROM ?:settings_sections WHERE name = 'general' AND parent_id = " . $section['section_id']);
    $tab_newest = db_get_row("SELECT * FROM ?:settings_sections WHERE name = 'newest' AND parent_id = " . $section['section_id']);

    addObject('final_sale_from', $section['section_id'], $tab_general['section_id'], 'I', 40, 0);
    addObject('sales_amount_from', $section['section_id'], $tab_general['section_id'], 'I', 1, 10);

    addObject('period', $section['section_id'], $tab_newest['section_id'], 'S', 'all', 0);
    addObject('last_days', $section['section_id'], $tab_newest['section_id'], 'I', '3', 10);

    $object_period = db_get_row("SELECT * FROM ?:settings_objects WHERE name = 'period' AND section_id = " . $section['section_id'] . " AND section_tab_id = " . $tab_newest['section_id']);

    addVariant($object_period['object_id'], 'all', 0);
    addVariant($object_period['object_id'], 'today', 10);
    addVariant($object_period['object_id'], 'last_days', 20);
}

function addObject($name, $section_id, $tab, $type, $value, $pos)
{
   db_query(
        "INSERT INTO ?:settings_objects (edition_type, name, section_id, section_tab_id, type, value, position, is_global, handler, parent_id) VALUES"
        . "('ROOT,ULT:VENDOR','" . $name . "', " . $section_id . ", " . $tab . ", '" .  $type . "', '" . $value . "', " . $pos .", 'N', '', 0);"
    );
}

function addVariant($object_id, $name, $position)
{
    db_query(
        "INSERT INTO ?:settings_variants (object_id, name, position) VALUES"
        . "(" . $object_id . ",'" . $name . "', " . $position . ");"
    );
}
