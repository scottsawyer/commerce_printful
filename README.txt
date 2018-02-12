At this time, the module provides:

An admin UI for setting the Printful API key
A permission to access the admin UI
A drush command (pt) for testing the API connection
A drush command (psp) for importing sync products from Printful into Drupal Commerce products with corresponding product displays
A hook_commerce_checkout_complete function which submits the order to Printful

It is assumed that you have a product type with a machine name of product and a node type with a machine name of product_display.

Installation

Make printful directory in sites/all/libraries
Change directory to printful
Call git clone https://github.com/printful/php-api-sdk.git
Change directory into php-api-sdk
Call composer install
Install the commerce_printful module
Define your API key
Call drush pt to test your connection to Printful