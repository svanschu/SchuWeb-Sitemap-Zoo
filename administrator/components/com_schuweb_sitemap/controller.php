<?php
/**
 * @version     sw.build.version
 * @copyright   Copyright (C) 2019 - 2022 Sven Schultschik. All rights reserved
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Sven Schultschik (extensions@schultschik.de)
 */
// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * Component Controller
 *
 * @package     SchuWeb Sitemap
 * @subpackage  com_schuweb_sitemap
 */
class SchuWeb_SitemapController extends JControllerLegacy
{

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Display the view
     */
    public function display($cachable = false, $urlparams = false)
    {
        require_once JPATH_COMPONENT . '/helpers/schuweb_sitemap.php';

        $app    = JFactory::getApplication();
        // Get the document object.
        $document = $app->getDocument();

        $jinput = $app->input;

        // Set the default view name and format from the Request.
        $vName = $jinput->getWord('view', 'sitemaps');
        $vFormat = $document->getType();
        $lName = $jinput->getWord('layout', 'default');

        // Get and render the view.
        if ($view = $this->getView($vName, $vFormat)) {
            // Get the model for the view.
            $model = $this->getModel($vName);

            // Push the model into the view (as default).
            $view->setModel($model, true);
            $view->setLayout($lName);

            // Push document object into the view.
            $view->document = &$document;

            $view->display();

        }
    }
}