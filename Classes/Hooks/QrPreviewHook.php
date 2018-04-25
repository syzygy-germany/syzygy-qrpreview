<?php
declare(strict_types=1);

namespace SyzygyTypo3\SyzygyQrpreview\Hooks;

/*
 * This file is part of the package syzygy-typo3/syzygy-qrpreview.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use SyzygyTypo3\SyzygyQrpreview\Service\Configuration;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function version_compare;

class QrPreviewHook
{
    /**
     * @var IconFactory
     */
    private $iconFactory;

    private $languageService;

    const MODULE_VIEW_ROUTE = '/web/ViewpageView/';
    const MODULE_VIEW_M = 'web_ViewpageView';

    public function __construct(IconFactory $iconFactory = null)
    {
        $this->iconFactory = $iconFactory ?: GeneralUtility::makeInstance(IconFactory::class);
        $this->languageService = $this->getLanguageService();
        $this->languageService->includeLLFile(Configuration::QR_BACKEND_LANGUAGE_FILE_PATH);
    }

    public function registerQrPreviewButton($params, &$ref)
    {
        // return early if not Web->View module
        if (GeneralUtility::_GP('route') !== static::MODULE_VIEW_ROUTE
            && GeneralUtility::_GP('M') !== static::MODULE_VIEW_M
        ) {
            return $params['buttons'];
        }

        // register JavaScript
        $this->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/SyzygyQrpreview/QrPreview');

        // retrieve button for qr code in modal
        $params['buttons']['left'][][] = $this->retrieveButton();
        return $params['buttons'];
    }

    private function retrieveButton()
    {
        $pageId = $this->retrievePageIdFromRequest();
        $languageId = $this->getCurrentLanguage($pageId, $this->retrieveLanguageFromRequest());

        $qrCodePreviewButton = GeneralUtility::makeInstance(LinkButton::class);
        return $qrCodePreviewButton
            ->setHref('#')
            ->setDataAttributes([
                'action' => 'show-qr-preview',
                'qr-preview-page-id' => $pageId,
                'qr-preview-language' => $languageId,
                'qr-preview-version' => TYPO3_version,
            ])
            ->setTitle($this->languageService->getLL('title'))
            ->setIcon($this->iconFactory->getIcon('syzygy-qr-preview', Icon::SIZE_SMALL));
    }

    private function retrievePageIdFromRequest(): int
    {
        return (int) GeneralUtility::_GP('id');
    }

    /**
     * Tries to get the
     *
     * @return int|null
     */
    private function retrieveLanguageFromRequest()
    {
        if (!empty(GeneralUtility::_GP('language'))) {
            return (int) GeneralUtility::_GP('language');
        }
        return null;
    }

    /**
     * Returns the current language
     *
     * @param int $pageId
     * @param string $languageParam
     * @return int
     */
    private function getCurrentLanguage(int $pageId, string $languageParam = null): int
    {
        $languageId = (int)$languageParam;
        if ($languageParam === null) {
            $states = $this->getBackendUser()->uc['moduleData']['web_view']['States'];
            $languages = $this->getPreviewLanguages($pageId);
            if (isset($states['languageSelectorValue'], $languages[$states['languageSelectorValue']])) {
                $languageId = (int)$states['languageSelectorValue'];
            }
        } else {
            $this->getBackendUser()->uc['moduleData']['web_view']['States']['languageSelectorValue'] = $languageId;
            $this->getBackendUser()->writeUC($this->getBackendUser()->uc);
        }
        return $languageId;
    }

    /**
     * Returns the preview languages
     *
     * @param int $pageId
     * @return array
     */
    private function getPreviewLanguages(int $pageId): array
    {
        $localizationParentField = $GLOBALS['TCA']['pages']['ctrl']['transOrigPointerField'];
        $languageField = $GLOBALS['TCA']['pages']['ctrl']['languageField'];
        $modSharedTSconfig = BackendUtility::getModTSconfig($pageId, 'mod.SHARED');
        if ($modSharedTSconfig['properties']['view.']['disableLanguageSelector'] === '1') {
            return [];
        }
        $languages = [
            0 => isset($modSharedTSconfig['properties']['defaultLanguageLabel'])
                ? $modSharedTSconfig['properties']['defaultLanguageLabel'] . ' (' . $this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_mod_web_list.xlf:defaultLanguage') . ')'
                : $this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_mod_web_list.xlf:defaultLanguage')
        ];
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_language');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        if (!$this->getBackendUser()->isAdmin()) {
            $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        }

        $tablePageOverlay = 'pages';
        if (version_compare(TYPO3_version, '9.0', '<')) {
            $tablePageOverlay = 'pages_language_overlay';
            $localizationParentField = 'pid';
            $languageField = 'sys_language_uid';
        }

        $result = $queryBuilder->select('sys_language.uid', 'sys_language.title')
            ->from('sys_language')
            ->join(
                'sys_language',
                $tablePageOverlay,
                'o',
                $queryBuilder->expr()->eq('o.' . $languageField, $queryBuilder->quoteIdentifier('sys_language.uid'))
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'o.' . $localizationParentField,
                    $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)
                )
            )
            ->groupBy('sys_language.uid', 'sys_language.title', 'sys_language.sorting')
            ->orderBy('sys_language.sorting')
            ->execute();

        while ($row = $result->fetch()) {
            if ($this->getBackendUser()->checkLanguageAccess($row['uid'])) {
                $languages[$row['uid']] = $row['title'];
            }
        }
        return $languages;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getPageRenderer()
    {
        return GeneralUtility::makeInstance(PageRenderer::class);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
