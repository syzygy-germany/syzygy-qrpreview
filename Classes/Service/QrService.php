<?php
declare(strict_types = 1);

namespace SyzygyTypo3\SyzygyQrpreview\Service;

/*
 * This file is part of the package syzygy-typo3/syzygy-qrpreview.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Endroid\QrCode\QrCode;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class QrService
{
    private $qrCode;

    public function __construct(QrCode $qrCode = null)
    {
        $this->qrCode = $qrCode ?: GeneralUtility::makeInstance(QrCode::class);
    }

    /**
     * This will generate a QR code within the standard temporary TYPO3 folder (typo3temp/var/transient).
     * NOTICE: It will not take care of the deletion of this file. Please handle this on your own.
     *
     * @param string $text
     * @return string
     */
    public function getCodeForText(string $text): string
    {
        $this->initialize();

        $tempFile = GeneralUtility::tempnam('qr-code-', '.png');

        $this->qrCode->setText($text);
        $this->qrCode->writeFile($tempFile);

        return $tempFile;
    }

    private function initialize()
    {
        $this->qrCode->setWriterByName('png');
        $this->qrCode->setSize(400);
    }
}
