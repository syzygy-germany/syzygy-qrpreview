# TYPO3 QR code for preview URI

> To simplify the handling of URI on e.g. mobile devices, this extension provide an additional button in the Web -> View module to generate a QR code of the preview URI.

## Installation

Installation only via composer with:

```
composer require syzygy-typo3/syzygy-qrpreview
```

## Usage

- Open the TYPO3 backend
- Open the module Web -> View
- Select a page to preview
- In the top bar there is a new button right beside the "View webpage" button
- On click it will open a modal with the QR code that includes the preview URI
- Open an QR code scanner app on your mobile device and scan the code
- The site to preview should be opened in a browser 
- Have fun!
