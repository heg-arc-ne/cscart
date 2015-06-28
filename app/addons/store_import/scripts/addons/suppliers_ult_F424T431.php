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

db_query("ALTER TABLE ?:suppliers CHANGE `company_id` `company_id` int(11) unsigned NOT NULL DEFAULT 0");
db_query("ALTER TABLE ?:suppliers CHANGE `address` `address` varchar(255) NOT NULL DEFAULT ''");

db_query("ALTER TABLE ?:suppliers CHANGE `city` `city` varchar(255) NOT NULL DEFAULT ''");
db_query("ALTER TABLE ?:suppliers CHANGE `state` `state` varchar(255) NOT NULL DEFAULT ''");
db_query("ALTER TABLE ?:suppliers CHANGE `country` `country` varchar(255) NOT NULL DEFAULT ''");
db_query("ALTER TABLE ?:suppliers CHANGE `zipcode` `zipcode` varchar(255) NOT NULL DEFAULT ''");
db_query("ALTER TABLE ?:suppliers CHANGE `email` `email` varchar(255) NOT NULL DEFAULT ''");
db_query("ALTER TABLE ?:suppliers CHANGE `phone` `phone` varchar(255) NOT NULL DEFAULT ''");
db_query("ALTER TABLE ?:suppliers CHANGE `fax` `fax` varchar(255) NOT NULL DEFAULT ''");
db_query("ALTER TABLE ?:suppliers CHANGE `url` `url` varchar(255) NOT NULL DEFAULT ''");
