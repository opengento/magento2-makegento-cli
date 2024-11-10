{
  "name": "{{fullComposerStandardNewModuleName}}",
  "version": "100.0.0",
  "description": "N/A",
  "type": "magento2-module",
  "require": {
    "magento/framework": "*"
  },
  "license": [
    "Proprietary"
  ],
  "autoload": {
    "files": [
      "registration.php"
    ],
    "psr-4": {
      "{{vendorName}}\\{{moduleName}}\\": ""
    }
  }
}
