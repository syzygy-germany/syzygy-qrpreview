/**
 * Module: TYPO3/CMS/SavenciaBase/Backend/DashboardWidget/ContentHistory
 */
define(['jquery', 'TYPO3/CMS/Backend/Modal', 'TYPO3/CMS/Backend/Severity'], function ($, Modal, Severity) {
    'use strict';

    function initialize() {
        _registerActions()
    }

    function _registerActions() {

        $('a[data-action="show-qr-preview"]').on('click', function (event) {
            event.preventDefault();
            var $target = $(this);
            var pageId = $target.data('qr-preview-page-id');
            var language = $target.data('qr-preview-language');
            var version = parseFloat($target.data('qr-preview-version'));
            var modalSize = Modal.sizes.medium;
            if (version < 9.0) {
                modalSize = Modal.sizes.large;
            }

            Modal.advanced({
                type: Modal.types.iframe,
                title: $target.attr('title'),
                size: modalSize,
                content: top.TYPO3.settings.ajaxUrls.syzygy_qrpreview_get_qr_code + '&pageId=' + pageId + '&language=' + language,
                severity: Severity.info,
                buttons: []
            });
        });
    }

    initialize();
});
