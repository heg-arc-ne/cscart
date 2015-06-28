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

use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($mode == 'view') {
    fn_add_breadcrumb(__('tags'));

    $tag = '';

    if (!empty($_REQUEST['tag'])) {
        if (Registry::get('addons.tags.tags_for_products') == 'Y') {
            $params = $_REQUEST;
            $params['extend'] = array('description');

            list($products, $search) = fn_get_products($params, Registry::get('settings.Appearance.products_per_page'));

            fn_gather_additional_products_data($products, array('get_icon' => true, 'get_detailed' => true, 'get_options' => true, 'get_discounts' => true));

            $selected_layout = fn_get_products_layout($params);

            Registry::get('view')->assign('selected_layout', $selected_layout);
            Registry::get('view')->assign('products', $products);
            Registry::get('view')->assign('search', $search);
        }

        if (Registry::get('addons.tags.tags_for_pages') == 'Y') {

            $page_types = fn_get_page_object_by_type();
            $params = $_REQUEST;
            $params['page_type'] = array_keys($page_types);
            $params['status'] = array('A');
            $params['simple'] = true;

            list($pages, $params) = fn_get_pages($params);

            Registry::get('view')->assign('pages', $pages);
            Registry::get('view')->assign('page_types', $page_types);
        }

        $tag = $_REQUEST['tag'];
    }

    $title = __('items_marked_by_tag', array(
        '[tag]' => $tag
    ));

    Registry::get('view')->assign('page_title', $title);
    fn_add_breadcrumb($title);

    if (!empty($products) || !empty($pages)) {
        Registry::get('view')->assign('tag_objects_exist', true);
    }
}
