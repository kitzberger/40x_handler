<?php

namespace Kitzberger\FourOhExHandler;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;

class ErrorHandler implements PageErrorHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var int
     */
    protected $statusCode;

    /**
     * @var array
     */
    protected $errorHandlerConfiguration;

    /**
     * @var array
     */
    protected $extConf;

    public function __construct(int $statusCode = null, array $configuration = [])
    {
        $this->statusCode = $statusCode;
        $this->errorHandlerConfiguration = $configuration;
    }

    /**
     * Modern error handler (TYPO3 9+)
     *
     * @param  ServerRequestInterface $request
     * @param  string                 $message
     * @param  array                  $reasons
     * @return [type]
     */
    public function handlePageError(ServerRequestInterface $request, string $message, array $reasons = []): ResponseInterface
    {
		$this->log(LogLevel::INFO, 'fox_handler triggered!');
		$this->log(LogLevel::DEBUG, print_r([$message, $reasons], true));

		if ($this->statusCode === 403) {
			if (isset($reasons['fe_group'])) {
				if ($reasons['fe_group'] != ['' => 0]) {
					$redirect_url = rawurlencode((string)$request->getUri());

					$fe_groups = $reasons['fe_group'];
					$fe_groups = array_pop($fe_groups);
					$fe_groups = GeneralUtility::intExplode(',', $fe_groups, true);
					foreach ($fe_groups as $fe_group) {
						$login_pid = $this->getPageUid_403($fe_group);
						if (is_numeric($login_pid)) {
							$login_url = $this->getTypoLink($login_pid, ['redirect_url' => $redirect_url]);
							$this->handle403($login_url);
						}
					}
				}
			}
		}

		// fallback
		$this->handle404();
	}

	protected function handle404()
	{
		if ($pid = $this->getPageUid_404()) {
			if (is_numeric($pid)) {
				$url = 'index.php?id=' . $pid;
				$this->log(LogLevel::INFO, 'Redirecting to 404 page: ' . $url);
				$GLOBALS['TSFE']->pageErrorHandler($url);
			} else {
				die('Unhandled case for 404 setting! TODO: implement something ;-)');
			}
		}
		die('Undefined 404 setting! TODO: implement something ;-)');
	}

	protected function handle403($login_url)
	{
		$this->log(LogLevel::INFO, 'Redirecting to 403 page: ' . $login_url);
		header('HTTP/1.0 403 Forbidden');
		header('Location: ' . $login_url);
		die;
	}

	protected function getPageUid_404()
	{
        $page404 = null;

        // Read setting from extension configuration
        if (isset($this->getExtConf()['404_page'])) {
            $page404 = $this->getExtConf()['404_page'];
            $this->log(LogLevel::DEBUG, 'Read 404 settings from extension configuration: ' . $page404);
        }
        // Override with setting from site configuration (if given)
        if (isset($this->errorHandlerConfiguration['404_page'])) {
            $page404 = $this->errorHandlerConfiguration['404_page'];
            $this->log(LogLevel::DEBUG, 'Override 404 settings via site configuration: ' . $page404);
        }

		return $page404 ?: 1;
	}

	protected function getPageUid_403($fe_group)
	{
        $page403 = null;

        // Read setting from extension configuration
		if (isset($this->getExtConf()['403_pages'])) {
			$page403 = $this->getExtConf()['403_pages'];
            $this->log(LogLevel::DEBUG, 'Read 403 settings from extension configuration: ' . $page403);
        }
        // Override with setting from site configuration (if given)
        if (isset($this->errorHandlerConfiguration['403_pages'])) {
            $page403 = $this->errorHandlerConfiguration['403_pages'];
            $this->log(LogLevel::DEBUG, 'Override 404 settings via site configuration: ' . $page403);
        }

        if ($page403) {
			if (is_numeric($page403)) {
				$this->log(LogLevel::DEBUG, 'Found global 403 page: ' . $page403);
				return $page403;
			} else {
				$mappings = GeneralUtility::trimExplode(',', $page403, true);
				if (!empty($mappings)) {
					foreach ($mappings as $mapping) {
						list($group, $loginPage) = GeneralUtility::trimExplode('=', $mapping, true);
						if ($group == $fe_group) {
							$this->log(LogLevel::DEBUG, 'Found 403 page for group ' . $group . ': ' . $loginPage);
							return $loginPage;
						}
						if ($group == '*') {
							$this->log(LogLevel::DEBUG, 'Found 403 page for group wildcard: ' . $loginPage);
							return $loginPage;
						}
					}
				}
			}
        }

		return false;
	}

	protected function getExtConf()
	{
		if (is_null($this->extConf)) {
			$this->extConf = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['fox_handler'];
		}

		return $this->extConf;
	}

	protected function getTypoLink($pid, $params)
	{
		// re-initialize TSFE with target pid so link generation will actually work
		$this->getTypoScriptFrontendController($pid);

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

	protected function log($level = LogLevel::DEBUG, $message = '')
	{
		$this->logger->log($level, $message);
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
