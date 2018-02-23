At this time, the module provides:

An admin UI for setting the Printful API key
A permission to access the admin UI
A Printful product type for Commerce (printful)
A Printful product display node type (printful)
A drush command (pt) for testing the API connection
A drush command (psp) for importing sync products from Printful into Drupal Commerce products with corresponding product displays
A replacement rules shipping action which limits methods and adds Printful shipping fees
Dynamic tax rates and calculation based on Printful API response
Order submission to Printful in draft state

Installation

Install the composer_manager module
Install the commerce_printful module
Verify the printful/php-api-sdk package is installed at admin/config/system/composer-manager
Add your Printful API key and select desired shipping methods at admin/commerce/config/printful
Call drush pt to test your connection to Printful
Call drush psp to import your Printful sync products
Edit pricing and enable desired products
Publish desired product displays

Optionally replace the rules action on existing shipping rates to allow Printful
shipping charges to be added to base rates when mixing Printful products with
standard products in the same order.
