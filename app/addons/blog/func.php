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

use Tygh\Mailer;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

function fn_blog_page_object_by_type(&$types)
{
    $types[PAGE_TYPE_BLOG] = array(
        'content' => 'blog',
        'single' => 'blog.post',
        'name' => 'blog.posts',
        'add_name' => 'blog.add_post',
        'edit_name' => 'blog.editing_post',
        'new_name' => 'blog.new_post',
        'exclusive' => true, // indicates that this page type should not be combined with other pages
        'hide_fields' => array(
            'position' => true
        )
    );
}


function fn_blog_remove_pages()
{
    $pages = db_get_fields("SELECT page_id FROM ?:pages WHERE page_type = ?s ", PAGE_TYPE_BLOG);

    foreach ($pages as $page_id) {
        fn_delete_page($page_id, $recurse = true);
    }
}

function fn_blog_post_get_pages(&$pages, $params, $lang_code)
{
    $blog_pages = array();
    foreach ($pages as $idx => $page) {
        if (!empty($page['page_type']) && $page['page_type'] == PAGE_TYPE_BLOG) {
            $blog_pages[$idx] = $page['page_id'];
            if (!empty($page['description'])) {
                if (strpos($page['description'], BLOG_CUT) !== false) {
                    list($pages[$idx]['spoiler']) = explode(BLOG_CUT, $page['description'], 2);
                } else {
                    $pages[$idx]['spoiler'] = $page['description'];
                }
            }

            if (!empty($params['get_image'])) {
                $pages[$idx]['main_pair'] = fn_get_image_pairs($page['page_id'], 'blog', 'M', true, false, $lang_code);
            }

            if (!empty($page['subpages'])) {
                fn_blog_post_get_pages($pages[$idx]['subpages'], $params, $lang_code);
            }
        }
    }

    if (!empty($blog_pages)) {
        $authors = db_get_hash_single_array("SELECT CONCAT(u.firstname, ' ', u.lastname) as author, b.page_id FROM ?:blog_authors as b LEFT JOIN ?:users  as u ON b.user_id = u.user_id WHERE b.page_id IN (?n)", array('page_id', 'author'), $blog_pages);
        foreach ($blog_pages as $idx => $page_id) {
            $pages[$idx]['author'] = $authors[$page_id];
        }
    }
}

function fn_blog_get_page_data(&$page_data, $lang_code, $preview, $area)
{
    if ($page_data['page_type'] == PAGE_TYPE_BLOG) {
        $page_data['main_pair'] = fn_get_image_pairs($page_data['page_id'], 'blog', 'M', true, false, $lang_code);
        $page_data['author'] = db_get_field("SELECT CONCAT(u.firstname, ' ', u.lastname) FROM ?:blog_authors as b LEFT JOIN ?:users  as u ON b.user_id = u.user_id WHERE b.page_id = ?i", $page_data['page_id']);
    }
}

function fn_blog_get_pages_pre(&$params, $items_per_page, $lang_code)
{
    if (!empty($params['blog_page_id'])) {
        $parent_id = db_get_field("SELECT parent_id FROM ?:pages WHERE page_id = ?i", $params['blog_page_id']);
        if (!empty($parent_id)) {
            $params['parent_id'] = $parent_id;
        } else {
            $params['parent_id'] = $params['blog_page_id'];
        }
    }

    if (!empty($params['page_type']) && $params['page_type'] == PAGE_TYPE_BLOG) {
        $params['sort_by'] = 'timestamp';
        $params['sort_order'] = 'desc';
    }
}

function fn_blog_get_pages($params, $join, $condition, $fields, $group_by, &$sortings, $lang_code)
{
    if (!empty($params['page_type']) && $params['page_type'] == PAGE_TYPE_BLOG && !empty($params['get_tree'])) {
        $sortings['multi_level'] = array(
            '?:pages.parent_id',
            '?:pages.timestamp',
        );
    }
}

function fn_blog_update_page_post($page_data, $page_id, $lang_code, $create, $old_page_data)
{
    if (!empty($page_data['page_type']) && $page_data['page_type'] == PAGE_TYPE_BLOG) {
        fn_attach_image_pairs('blog_image', 'blog', $page_id, $lang_code);

        db_query("REPLACE INTO ?:blog_authors ?e", array(
            'page_id' => $page_id,
            'user_id' => $_SESSION['auth']['user_id']
        ));
    }
}

function fn_blog_delete_page($page_id)
{
    fn_delete_image_pairs($page_id, 'blog');

    db_query("DELETE FROM ?:blog_authors WHERE page_id = ?i", $page_id);
}

function fn_blog_clone_page($page_id, $new_page_id)
{
    fn_clone_image_pairs($new_page_id, $page_id, 'blog');

    db_query("INSERT INTO ?:blog_authors (page_id, user_id) SELECT ?i as page_id, user_id FROM ?:blog_authors WHERE page_id = ?i", $new_page_id, $page_id);
}
