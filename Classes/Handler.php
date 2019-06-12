<?php

namespace Kitzberger\FourOhExHandler;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class Handler
{
    static $extConf = null;

    /**
     * ...
     *
     * @param  array $param
     * @param  TypoScriptFrontendController $TypoScriptFrontendController
     * @return void
     */
    public static function pageNotFound($param, TypoScriptFrontendController $TypoScriptFrontendController)
    {
    	#var_dump($param); die();
        if ($param['reasonText'] === 'ID was not an accessible page') {
	        if (isset($param['pageAccessFailureReasons']['fe_group'])) {
	            if ($param['pageAccessFailureReasons']['fe_group'] != ['' => 0]) {
	                $redirect_url = rawurlencode($param["currentUrl"]);

	                $fe_groups = $param['pageAccessFailureReasons']['fe_group'];
	                $fe_groups = array_pop($fe_groups);
	                $fe_groups = GeneralUtility::intExplode(',', $fe_groups, true);
	                foreach ($fe_groups as $fe_group) {
	                    $login_pid = self::getPageUid_403($fe_group);
	                    if (is_numeric($login_pid)) {
	                    	$login_url = self::getTypoLink($login_pid, ['redirect_url' => $redirect_url]);
	                    	self::handle403($login_url);
	                    }
	                }
	            }
	        }
        }

	    // fallback
	    self::handle404();
    }

    protected static function handle404()
    {
		if ($pid = self::getPageUid_404()) {
			if (is_numeric($pid)) {
				$GLOBALS['TSFE']->pageErrorHandler('index.php?id=' . $pid);
			} else {
				die('Unhandled case for 404 setting! TODO: implement something ;-)');
			}
		}
		die('Undefined 404 setting! TODO: implement something ;-)');
    }

    protected static function handle403($login_url)
    {
        header('HTTP/1.0 403 Forbidden');
        header('Location: ' . $login_url);
        die;
    }

    protected static function getPageUid_404()
    {
        return self::getExtConf()['404_page'] ?: 1;
    }

    protected static function getPageUid_403($fe_group)
    {
        if (isset(self::getExtConf()['403_pages'])) {
            $page403 = self::getExtConf()['403_pages'];
            if (is_numeric($page403)) {
                return $page403;
            } else {
            	$mappings = GeneralUtility::trimExplode(',', $page403, true);
            	if (!empty($mappings)) {
            		foreach ($mappings as $mapping) {
            			list($group, $loginPage) = GeneralUtility::trimExplode('=', $mapping, true);
            			if ($group == $fe_group) {
            				return $loginPage;
            			}
            			if ($group == '*') {
            				return $loginPage;
            			}
            		}
            	}
            }
        }

        return false;
    }

    protected static function getExtConf()
    {
        if (is_null(self::$extConf)) {
            $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['40x_handler']);
        }

        return $extConf;
    }

    protected static function getTypoLink($pid, $params)
    {
    	// re-initialize TSFE with target pid so link generation will actually work
    	self::getTypoScriptFrontendController($pid);

        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        $additionalParams = '';
        foreach ($params as $key => $value) {
        	$additionalParams .= '&' . $key . '=' . $value;
        }

        return $cObj->typoLink_URL([
        	'forceAbsoluteUrl' => true,
        	'parameter' => $pid,
        	'additionalParams' => $additionalParams
        ]);
    }

	/**
	 * Initialize the typoscript frontend controller
	 *
	 * @param int $pid
	 *
	 * @return void
	 */
	protected function getTypoScriptFrontendController($pid = 1)
	{
	    /** @var TypoScriptFrontendController $frontend */
	    $frontend = GeneralUtility::makeInstance(
	        TypoScriptFrontendController::class,
	        null,
	        $pid,
	        0
	    );
	    $GLOBALS['TSFE'] = $frontend;
	    $frontend->connectToDB();
	    $frontend->initFEuser();
	    $frontend->determineId();
	    $frontend->initTemplate();
	    $frontend->getConfigArray();
	}
}
