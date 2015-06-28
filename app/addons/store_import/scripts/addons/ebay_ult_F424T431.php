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

db_query("ALTER TABLE ?:ebay_cached_transactions CHANGE `result` `result` text");
db_query("ALTER TABLE ?:ebay_categories CHANGE `full_name` `full_name` text");
db_query("ALTER TABLE ?:ebay_template_descriptions CHANGE `template_id` `template_id` int(11) unsigned NOT NULL DEFAULT 0");
db_query("ALTER TABLE ?:ebay_template_descriptions CHANGE `lang_code` `lang_code` char(2) NOT NULL DEFAULT ''");
db_query("ALTER TABLE ?:ebay_template_descriptions CHANGE `name` `name` varchar(255) NOT NULL DEFAULT ''");
