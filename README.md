Commerce Printful
-----------------
This module integrates Drupal Commerce 2.x with the Printful drop-shipping
and fulfillment provider.

Features
--------
The module provides the following:
* integration of products and their variations with Printful store variants
  (pulling data from Printful stores),
* integration of Printful shipment payment rates with a new shipping method
* integration of orders (when an order is fully paid in the Commerce store,
  it's shipments that use the printful_shipping method - usually one - are
  being sent to Printful).
* Printful "Package shipped" webhook integration: shipments get automatic status update
  and get shipping data including a tracking code.

Basic setup
-----------

1.  Install the module as any otfer Drupal module.
2.  Create a Printful store and add some products on printful.com.
3.  Create a Commerce product type with a variation type that
    has required attributes to map (colour, size) and an image field.
4.  Add a Printful store including the API key and synchronization settings
    on /admin/commerce/config/printful/printful_store.
5.  Make your product variation type shippable.
6.  Add the "Printful dropshipping" shipping method.
7.  Enable shipping for your Commerce order type, ddd shipping pane on your checkout
    flow, enable shipping for your Commerce order type.
8.  Enable order synchronization with Draft export for testing.
9.  Go to admin/commerce/config/printful/synchronization and execute synchronization
    for your product type.

Paid orders with the "Printful dropshipping" shipping method will now be sent to your
Printful store.

Additional notes
----------------
Probably everyone will wonder why sometimes Printful order external IDs don't correspond
to Drupal Commerce order IDs. It's because those are shipment IDs and not order IDs.
a Commerce order can have many shipments, each for certain items and each using different
shipping methods so we have to reference shipments not orders.
In simple stores though every order will have one shipment and order and shipment IDs will
be the same.
