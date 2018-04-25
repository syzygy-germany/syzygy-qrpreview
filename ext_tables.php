<?php
defined('TYPO3_MODE') or die();

/*
 * This file is part of the package syzygy-typo3/syzygy-qrpreview.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

(function () {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['Backend\Template\Components\ButtonBar']['getButtonsHook'][] =
        \SyzygyTypo3\SyzygyQrpreview\Hooks\QrPreviewHook::class . '->registerQrPreviewButton';
})();
