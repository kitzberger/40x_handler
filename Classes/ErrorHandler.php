<?php

namespace Kitzberger\FourOhExHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerInterface;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerNotConfiguredException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

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
                            return $this->handle403($login_url);
                        }
                    }
                }
            }
        }

        // Fallback to configured 404 handler
        $this->log(LogLevel::INFO, 'Fallback to 404 handler');
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            try {
                $errorHandler = $site->getErrorHandler(404);
                if ($errorHandler instanceof PageErrorHandlerInterface) {
                    return $errorHandler->handlePageError($request, $message, $reasons);
                }
            } catch (PageErrorHandlerNotConfiguredException $e) {
                // No error handler found, so fallback back to the generic TYPO3 error handler.
            }
        }

        throw new \Exception('Somethings really wrong!');
    }

    protected function handle403(string $login_url, int $status = 307): ResponseInterface
    {
        $this->log(LogLevel::INFO, 'Redirecting to 403 page: ' . $login_url);

        return new RedirectResponse($login_url, $status);
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
            $this->log(LogLevel::DEBUG, 'Override 403 settings via site configuration: ' . $page403);
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
}
