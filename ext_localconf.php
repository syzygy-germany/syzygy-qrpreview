<?php
defined('TYPO3_MODE') or die();

/*
 * This file is part of the package syzygy-typo3/syzygy-qrpreview.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

(function ($extKey) {
    // Validate if we have the class available via autoload
    // if not, this is the TER version and we need to add our own autoload
    if (class_exists('Endroid\QrCode\QrCode') === false) {
        /** @noinspection PhpIncludeInspection */
        include \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath(
            $extKey,
            'Resources/Private/Composer/vendor/autoload.php'
        );
    }

    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
    $iconRegistry->registerIcon(
        'syzygy-qr-preview',
        \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        ['source' => 'EXT:' . $extKey . '/Resources/Public/Icons/qr-code.svg']
    );
})(\SyzygyTypo3\SyzygyQrpreview\Service\Configuration::EXTENSION_KEY);
