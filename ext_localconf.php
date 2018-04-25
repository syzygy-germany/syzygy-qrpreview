<?php
defined('TYPO3_MODE') or die();

/*
 * This file is part of the package syzygy-typo3/syzygy-qrpreview.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

(function ($extKey) {
    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
    $iconRegistry->registerIcon(
        'syzygy-qr-preview',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:' . $extKey . '/Resources/Public/Icons/qr-code.svg']
    );
})(\SyzygyTypo3\SyzygyQrpreview\Service\Configuration::EXTENSION_KEY);
