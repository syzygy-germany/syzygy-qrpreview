<?php

/*
 * This file is part of the package syzygy-typo3/syzygy-qrpreview.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'SYZYGY QR preview',
    'description' => 'Opens a dialog with a QR code which includes the preview URI.',
    'category' => 'module',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'SYZYGY TYPO3 Development Team',
    'author_email' => 'typo3-team@syzygy.de',
    'author_company' => 'SYZYGY Deutchland GmbH',
    'version' => '1.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '8.0.0-9.2.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
