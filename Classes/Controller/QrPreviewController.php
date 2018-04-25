<?php
declare(strict_types=1);

namespace SyzygyTypo3\SyzygyQrpreview\Controller;

/*
 * This file is part of the package syzygy-typo3/syzygy-qrpreview.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SyzygyTypo3\SyzygyQrpreview\Exception\DomainException;
use SyzygyTypo3\SyzygyQrpreview\Service\Configuration;
use SyzygyTypo3\SyzygyQrpreview\Service\QrService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use function trim;
use function strpos;
use function is_array;

class QrPreviewController
{
    /**
     * @var StandaloneView
     */
    private $view;

    /**
     * @var QrService
     */
    private $qrService;

    /**
     * @var ServerRequest
     */
    private $request;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var LanguageService
     */
    private $languageService;

    public function __construct(
        ViewInterface $view = null,
        QrService $qrService = null,
        ObjectManagerInterface $objectManager = null
    ) {
        $this->view = $this->view ?: GeneralUtility::makeInstance(StandaloneView::class);
        $this->qrService = $qrService ?: new QrService();
        $this->objectManager = $objectManager ?: GeneralUtility::makeInstance(ObjectManager::class);
        $this->languageService = $this->getLanguageService();
        $this->languageService->includeLLFile(Configuration::QR_BACKEND_LANGUAGE_FILE_PATH);
    }

    public function getCode(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->request = $request;
        $this->initializeView();

        if (!$this->requestHasArgument('pageId') || (int) $this->getArgumentFromRequest('pageId') === 0) {
            $this->addErrorMessage($this->languageService->getLL('no_page_selected'));
            if (version_compare(TYPO3_version, '9.0', '<')) {
                $response->getBody()->write($this->view->render());
                return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            return new HtmlResponse($this->view->render());
        }

        $pageId = (int) $this->getArgumentFromRequest('pageId');
        $languageId = 0;
        if ((int) $this->getArgumentFromRequest('language') !== 0) {
            $languageId = (int) $this->getArgumentFromRequest('language');
        }

        $isHttps = $this->isHttps($request);
        try {
            $uri = $this->getTargetUrl($pageId, $languageId, $isHttps);

            // generate qr code and assign path to view
            $tempFilePath = $this->qrService->getCodeForText($uri);
            $renderedQrCode = file_get_contents($tempFilePath);
            $this->view->assign('qrCode', base64_encode($renderedQrCode));

            // TODO: new FEATURE implement download of the QR code

            // delete temporary file
            GeneralUtility::unlink_tempfile($tempFilePath);
        } catch (DomainException $exception) {
            $this->addErrorMessage($exception->getMessage());
        } finally {
            if (version_compare(TYPO3_version, '9.0', '<')) {
                $response->getBody()->write($this->view->render());
                return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            return new HtmlResponse($this->view->render());
        }
    }

    private function isHttps(ServerRequestInterface $request): bool
    {
        if (version_compare(TYPO3_version, '9.0', '<')) {
            return $this->legacyIsHttps($request);
        }

        return $request->getAttribute('normalizedParams')->isHttps();
    }

    private function legacyIsHttps(ServerRequestInterface $serverParams): bool
    {
        $isHttps = false;
        $typo3ConfVars = $GLOBALS['TYPO3_CONF_VARS'];
        $configuredProxySSL = trim($typo3ConfVars['SYS']['reverseProxySSL'] ?? '');
        if ($configuredProxySSL === '*') {
            $configuredProxySSL = trim($typo3ConfVars['SYS']['reverseProxyIP'] ?? '');
        }
        $httpsParam = (string)($serverParams->getServerParams()['HTTPS'] ?? '');
        if (GeneralUtility::cmpIP(trim($serverParams->getServerParams()['REMOTE_ADDR'] ?? ''), $configuredProxySSL)
            || ($serverParams->getServerParams()['SSL_SESSION_ID'] ?? '')
            // https://secure.php.net/manual/en/reserved.variables.server.php
            // "Set to a non-empty value if the script was queried through the HTTPS protocol."
            || ($httpsParam !== '' && $httpsParam !== 'off' && $httpsParam !== '0')
        ) {
            $isHttps = true;
        }
        return $isHttps;
    }

    /**
     * Assign error message to the view
     *
     * @param string $message
     */
    private function addErrorMessage(string $message): void
    {
        $this->view->assign('errorMessage', $message);
    }

    /**
     * Determine the url to view
     *
     * @param int $pageId
     * @param int $languageId
     * @param bool $isHttps
     * @return string
     * @throws DomainException
     */
    private function getTargetUrl(int $pageId, int $languageId, bool $isHttps): string
    {
        $permissionClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageRecord = BackendUtility::readPageAccess($pageId, $permissionClause);
        if ($pageRecord) {
            $adminCommand = $this->getAdminCommand($pageId);
            $domainName = $this->getDomainName($pageId);
            $languageParameter = $languageId ? '&L=' . $languageId : '';
            // Mount point overlay: Set new target page id and mp parameter
            /** @var \TYPO3\CMS\Frontend\Page\PageRepository $sysPage */
            $sysPage = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
            $sysPage->init(false);
            $mountPointMpParameter = '';
            $finalPageIdToShow = $pageId;
            $mountPointInformation = $sysPage->getMountPointInfo($pageId);
            if ($mountPointInformation && $mountPointInformation['overlay']) {
                // New page id
                $finalPageIdToShow = $mountPointInformation['mount_pid'];
                $mountPointMpParameter = '&MP=' . $mountPointInformation['MPvar'];
            }

            // TCEMAIN.previewDomain can contain the protocol, check prevents double protocol URLs
            if (strpos($domainName, '://') !== false) {
                $protocolAndHost = $domainName;
            } else {
                $protocol = $isHttps ? 'https' : 'http';
                $protocolAndHost = $protocol . '://' . $domainName;
            }
            return $protocolAndHost . '/index.php?id=' . $finalPageIdToShow . $this->getTypeParameterIfSet($finalPageIdToShow) . $mountPointMpParameter . $adminCommand . $languageParameter;
        }
        return '#';
    }

    /**
     * Get domain name for requested page id
     *
     * @param int $pageId
     * @return string|null Domain name from first sys_domains-Record or from TCEMAIN.previewDomain, NULL if neither is configured
     * @throws DomainException
     */
    private function getDomainName(int $pageId): string
    {
        $previewDomainConfig = $this->getBackendUser()->getTSConfig(
            'TCEMAIN.previewDomain',
            BackendUtility::getPagesTSconfig($pageId)
        );
        if ($previewDomainConfig['value']) {
            $domain = $previewDomainConfig['value'];
        } else {
            $domain = BackendUtility::firstDomainRecord(BackendUtility::BEgetRootLine($pageId));
        }

        if (!$domain) {
            throw new DomainException($this->languageService->getLL('domain_exception_message'));
        }

        return $domain;
    }

    /**
     * With page TS config it is possible to force a specific type id via mod.web_view.type
     * for a page id or a page tree.
     * The method checks if a type is set for the given id and returns the additional GET string.
     *
     * @param int $pageId
     * @return string
     */
    private function getTypeParameterIfSet(int $pageId): string
    {
        $typeParameter = '';
        $modTSconfig = BackendUtility::getModTSconfig($pageId, 'mod.web_view');
        $typeId = (int)$modTSconfig['properties']['type'];
        if ($typeId > 0) {
            $typeParameter = '&type=' . $typeId;
        }
        return $typeParameter;
    }

    /**
     * Get admin command
     *
     * @param int $pageId
     * @return string
     */
    private function getAdminCommand(int $pageId): string
    {
        // The page will show only if there is a valid page and if this page may be viewed by the user
        $pageinfo = BackendUtility::readPageAccess($pageId, $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW));
        $addCommand = '';
        if (is_array($pageinfo)) {
            $addCommand = '&ADMCMD_editIcons=1' . BackendUtility::ADMCMD_previewCmds($pageinfo);
        }
        return $addCommand;
    }

    /**
     * @param string $argument
     * @return bool
     */
    private function requestHasArgument(string $argument): bool
    {
        return $this->getArgumentFromRequest($argument) !== null;
    }

    /**
     * @param string $argument
     * @return mixed
     */
    private function getArgumentFromRequest(string $argument)
    {
        $queryParams = $this->request->getQueryParams();
        if (isset($queryParams[$argument])) {
            return $queryParams[$argument];
        }
    }

    private function initializeView(): void
    {
        $this->view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName(Configuration::QR_TEMPLATE_PATH));
        $this->view->setLayoutRootPaths(Configuration::QR_LAYOUTS_ROOT_PATH);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
