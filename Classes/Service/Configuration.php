<?php
declare(strict_types=1);

namespace SyzygyTypo3\SyzygyQrpreview\Service;

/*
 * This file is part of the package syzygy-typo3/syzygy-qrpreview.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

class Configuration
{
    const EXTENSION_KEY = 'syzygy_qrpreview';
    const QR_TEMPLATE_PATH = 'EXT:syzygy_qrpreview/Resources/Private/Templates/QrPreview/GetCode.html';
    const QR_LAYOUTS_ROOT_PATH = ['EXT:syzygy_qrpreview/Resources/Private/Layouts/'];
    const QR_BACKEND_LANGUAGE_FILE_PATH = 'EXT:syzygy_qrpreview/Resources/Private/Language/Backend.xlf';
}
