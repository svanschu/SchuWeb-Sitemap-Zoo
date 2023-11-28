<?php
/**
 * @package     SchuWeb_Sitemap
 * @subpackage  SchuWeb_Sitemap_Zoo
 * 
 * @version     sw.build.version
 * @author      Sven Schultschik
 * @copyright   (C) 2010 - 2023 Sven Schultschik. All rights reserved
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @link        http://extensions.schultschik.de
 */
defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Factory;

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
            || ($include_items == 3 && !$sitemap->isXmlsitemap()));
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

                $node->xmlInsertChangeFreq = $parent->xmlInsertChangeFreq;
                $node->xmlInsertPriority   = $parent->xmlInsertPriority;

                $node->expandible = true;
                $node->lastmod    = $parent->lastmod;

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

            // $queryi = 'SELECT i.id, i.name, i.publish_up ,i.application_id ,i.modified' .
            //     ' FROM #__zoo_item i' .
            //     ' WHERE i.application_id= ' . $appid .
            //     ' AND DATEDIFF( i.publish_up, NOW( ) ) <=0' .
            //     ' AND IF( i.publish_down >0, DATEDIFF( i.publish_down, NOW( ) ) >0, true )' .
            //     ' ORDER BY i.publish_up';

            $query = $db->getQuery(true);
            $query->select(
                array(
                    $db->qn('id'),
                    $db->qn('name'),
                    $db->qn('publish_up'),
                    $db->qn('application_id'),
                    $db->qn('modified')
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

                $node->xmlInsertChangeFreq = $parent->xmlInsertChangeFreq;
                $node->xmlInsertPriority   = $parent->xmlInsertPriority;

                $node->expandible = true;
                $node->lastmod    = $parent->lastmod;
                $node->modified   = $item->modified;
                $node->newsItem   = 1; // if we are making news map and it get this far, it's news

                if (!isset($parent->subnodes))
                    $parent->subnodes = new \stdClass();

                $node->params = &$parent->params;

                $parent->subnodes->$id = $node;
            }
        }
    }
}