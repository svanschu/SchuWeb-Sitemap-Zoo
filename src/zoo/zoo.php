<?php
/**
 * @version     sw.build.version
 * @copyright   Copyright (C) 2019 - 2023 Sven Schultschik. All rights reserved
 * @license     GPL-3.0-or-later
 * @author      Sven Schultschik (extensions@schultschik.de)
 * @link        extensions.schultschik.de
 */

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;
use Joomla\Registry\Registry;

class schuweb_sitemap_zoo
{
    protected static $_menu_items;

    static function prepareMenuItem(&$node, &$params)
    {
        $link_query = parse_url($node->link);
        parse_str(html_entity_decode($link_query['query']), $link_vars);
        $component = ArrayHelper::getValue($link_vars, 'option', '');
        $view      = ArrayHelper::getValue($link_vars, 'view', '');

        if ($component == 'com_zoo' && $view == 'frontpage') {
            $id = intval(ArrayHelper::getValue($link_vars, 'id', 0));
            if ($id != 0) {
                $node->uid        = 'zoo' . $id;
                $node->expandible = false;
            }
        }
    }

    static function getTree(&$sitemap, &$parent, &$params)
    {

        $link_query = parse_url($parent->link);
        parse_str(html_entity_decode($link_query['query']), $link_vars);

        $include_categories           = ArrayHelper::getValue($params, 'include_categories', 1);
        $include_categories           = ($include_categories == 1
            || ($include_categories == 2 && $sitemap->isXmlsitemap())
            || ($include_categories == 3 && !$sitemap->isXmlsitemap()));
        $params['include_categories'] = $include_categories;

        $include_items           = ArrayHelper::getValue($params, 'include_items', 1);
        $include_items           = ($include_items == 1
            || ($include_items == 2 && $sitemap->isXmlsitemap())
            || ($include_items == 3 && !$sitemap->isXmlsitemap())
            || $sitemap->isImagesitemap());
        $params['include_items'] = $include_items;

        $priority   = ArrayHelper::getValue($params, 'cat_priority', $parent->priority);
        $changefreq = ArrayHelper::getValue($params, 'cat_changefreq', $parent->changefreq);
        if ($priority == '-1')
            $priority = $parent->priority;
        if ($changefreq == '-1')
            $changefreq = $parent->changefreq;

        $params['cat_priority']   = $priority;
        $params['cat_changefreq'] = $changefreq;

        $priority   = ArrayHelper::getValue($params, 'item_priority', $parent->priority);
        $changefreq = ArrayHelper::getValue($params, 'item_changefreq', $parent->changefreq);
        if ($priority == '-1')
            $priority = $parent->priority;

        if ($changefreq == '-1')
            $changefreq = $parent->changefreq;

        $params['item_priority']   = $priority;
        $params['item_changefreq'] = $changefreq;

        self::getCategoryTree($sitemap, $parent, $params);

    }

    static function getCategoryTree(&$sitemap, &$parent, &$params)
    {
        $db = Factory::getDBO();

        // first we fetch what application we are talking about

        $app        = Factory::getApplication();
        $menu       = $app->getMenu('site');
        $menuparams = $menu->getParams($parent->id);
        $appid      = intval($menuparams->get('application', 0));

        // if selected, we print title category
        if ($params['include_categories']) {

            // we print title if there is any
            // commented out as non-functioning - Matt Faulds
            //	if ($params['categories_title'] != "" && !$sitemap->isXmlsitemap()) {
            //		echo "<".$params['categories_title_tag'].">".$params['categories_title']."</".$params['categories_title_tag'].">";
            //	}
            // get categories info from database

            // $queryc = 'SELECT c.id, c.name ' .
            //     'FROM #__zoo_category c ' .
            //     ' WHERE c.application_id = ' . $appid . ' AND c.published=1 ' .
            //     ' ORDER by c.ordering';

            $query = $db->getQuery(true);
            $query->select(
                array(
                    $db->qn('id'),
                    $db->qn('name')
                )
            )
                ->from($db->qn('#__zoo_category'))
                ->where(
                    array(
                        $db->qn('application_id') . '=' . $db->q($appid),
                        $db->qn('published') . '=1'
                    )
                )
                ->order($db->escape('ordering'));

            $db->setQuery($query);
            $cats = $db->loadObjectList();

            foreach ($cats as $cat) {
                $node             = new stdclass;
                $node->id         = $parent->id;
                $id               = $node->uid = $parent->uid . 'c' . $cat->id;
                $node->browserNav = $parent->browserNav;
                $node->name       = $cat->name;
                $node->link       = 'index.php?option=com_zoo&amp;task=category&amp;category_id='
                    . $cat->id . '&amp;Itemid=' . $node->id;
                $node->priority   = $params['cat_priority'];
                $node->changefreq = $params['cat_changefreq'];

                $node->expandible = true;

                if (!isset($parent->subnodes))
                    $parent->subnodes = new \stdClass();

                $node->params = &$parent->params;

                $parent->subnodes->$id = $node;
            }
        }

        if ($params['include_items']) {
            // get items info from database
            // basically it select those items that are published now (publish_up is less then now, meaning it's in past)
            // and not unpublished yet (either not have publish_down date set, or that date is in future)

            $query = $db->getQuery(true);
            $query->select(
                array(
                    $db->qn('id'),
                    $db->qn('name'),
                    $db->qn('publish_up'),
                    $db->qn('application_id'),
                    $db->qn('modified'),
                    $db->qn('elements')
                )
            )
                ->from($db->qn('#__zoo_item'))
                ->where(
                    array(
                        $db->qn('application_id') . '=' . $db->q($appid),
                        'DATEDIFF(' . $db->qn('publish_up') . ' , NOW()) <=0',
                        'IF(' . $db->qn('publish_down') . ' >0, DATEDIFF( ' . $db->qn('publish_down') . ', NOW( ) ) >0, true )'
                    )
                )
                ->order($db->qn('publish_up'));

            if ($sitemap->isNewssitemap()) {
                $query->where($db->qn('created') . ' > DATE_ADD(CURRENT_TIMESTAMP, INTERVAL -2 DAY)');
            }

            $db->setQuery($query);
            $items = $db->loadObjectList();

            // now we print items
            foreach ($items as $item) {
                // if we are making news map, we should ignore items older then 3 days
                if (
                    $sitemap->isNews
                    && strtotime($item->publish_up) < ($sitemap->now - (3 * 86400))
                ) {
                    continue;
                }
                $node             = new stdclass;
                $node->id         = $parent->id;
                $id               = $node->uid = $parent->uid . 'i' . $item->id;
                $node->browserNav = $parent->browserNav;
                $node->name       = $item->name;
                $node->link       = 'index.php?option=com_zoo&amp;task=item&amp;item_id='
                    . $item->id . '&amp;Itemid=' . $parent->id;
                $node->priority   = $params['item_priority'];
                $node->changefreq = $params['item_changefreq'];

                $node->expandible = true;
                $node->modified   = $item->modified;
                $node->newsItem   = 1; // if we are making news map and it get this far, it's news

                if ($sitemap->isImagesitemap())
                    $node->images = self::getImages($item->elements);

                if (!isset($parent->subnodes))
                    $parent->subnodes = new \stdClass();

                $node->params = &$parent->params;

                $parent->subnodes->$id = $node;
            }
        }
    }

    static function getImages(&$elements_json)
    {
        $urlBase = Uri::base();

        $urlBaseLen = strlen($urlBase);

        $images = null;

        $elements = new Registry($elements_json);

        foreach ($elements as $element) {
            if (
                isset($element->{0})
                && isset($element->{0}->value)
                && $element->{0}->value != ""
            ) {
                $text     = $element->{0}->value;
                $matches1 = $matches2 = array();
                // Look <img> tags
                preg_match_all('/<img[^>]*?(?:(?:[^>]*src="(?P<src>[^"]+)")|(?:[^>]*alt="(?P<alt>[^"]+)")|(?:[^>]*title="(?P<title>[^"]+)"))+[^>]*>/i', $text, $matches1, PREG_SET_ORDER);
                // Loog for <a> tags with href to images
                preg_match_all('/<a[^>]*?(?:(?:[^>]*href="(?P<src>[^"]+\.(gif|png|jpg|jpeg))")|(?:[^>]*alt="(?P<alt>[^"]+)")|(?:[^>]*title="(?P<title>[^"]+)"))+[^>]*>/i', $text, $matches2, PREG_SET_ORDER);
                $matches = array_merge($matches1, $matches2);
                if (count($matches)) {
                    $images = array();

                    $count = count($matches);
                    for ($i = 0; $i < $count; $i++) {
                        if (trim($matches[$i]['src']) && (substr($matches[$i]['src'], 0, 1) == '/' || !preg_match('/^https?:\/\//i', $matches[$i]['src']) || substr($matches[$i]['src'], 0, $urlBaseLen) == $urlBase)) {
                            $src = $matches[$i]['src'];
                            if (substr($src, 0, 1) == '/') {
                                $src = substr($src, 1);
                            }
                            if (!preg_match('/^https?:\//i', $src)) {
                                $src = $urlBase . $src;
                            }
                            $image        = new stdClass;
                            $image->src   = $src;
                            $image->title = (isset($matches[$i]['title']) ? $matches[$i]['title'] : @$matches[$i]['alt']);
                            $images[]     = $image;
                        }
                    }
                }
            }

            if (
                property_exists($element, "lightbox_image")
                && isset($element->file)
                && !empty($element->file)
            ) {

                $src = $element->file;
                if (!preg_match('/^https?:\//i', $src)) {
                    $src = $urlBase . $src;
                }
                $image      = new stdClass;
                $image->src = $src;
                $images[]   = $image;
            }
        }

        return $images;
    }
}