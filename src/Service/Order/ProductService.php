<?php
namespace ShieldPpPayment\Service\Order;

use WC_Admin_Duplicate_Product;
use WC_Product;
use WP_Error;

class ProductService
{
  public function find_closest_product_by_price(float $targetPrice): ?WC_Product
  {
    /** @var WC_Product[] $products */
    $products = wc_get_products([
      'status' => 'publish',
      'limit' => -1,
    ]);

    /** @var ?WC_Product $closest */
    $closest = null;

    foreach ($products as $product) {
      if (false && $product->is_type('variable')) {
        $children = $product->get_children();
        foreach ($children as $variationId) {
          $variation  = wc_get_product($variationId);
          $shouldPick = $this->shouldPickProduct($closest, $variation, $targetPrice);
          if (!$shouldPick) {
            continue;
          }
          $closest = $variation;
        }
      } else {
        $shouldPick = $this->shouldPickProduct($closest, $product, $targetPrice);
        if (!$shouldPick) {
          continue;
        }
        $closest = $product;
      }

      if ($closest) {
        $closestPrice = $this->getProductPrice($closest);
        if ($closestPrice == 0) {
          break;
        }
      }
    }

    return $closest;
  }

  private function shouldPickProduct(?WC_Product $closest, WC_Product $product, $targetPrice)
  {
    if (!$product || !$product->is_purchasable()) {
      return false;
    }
    $price = $this->getProductPrice($product);
    if ($price <= 0) {
      return false;
    }
    if (!$closest) {
      return true;
    }

    $diff         = $price - $targetPrice;
    $closestPrice = $this->getProductPrice($closest);
    $closestDiff  = $closestPrice - $targetPrice;
    return abs($diff) <= abs($closestDiff);
  }

  public function getProductPrice(WC_Product $product)
  {
    return (float) $product->get_price('edit');
  }

  /**
   * Clone the non-cloned product whose price is closest to $targetPrice.
   *
   * @param float $targetPrice
   * @return int|WP_Error New product ID or WP_Error.
   */
  public static function cloneClosestProduct(float $targetPrice)
  {
    global $wpdb;

    // Find nearest non-cloned product
    $productId = $wpdb->get_var($wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} price
            ON price.post_id = p.ID
            AND price.meta_key = '_price'
        LEFT JOIN {$wpdb->postmeta} cloned
            ON cloned.post_id = p.ID
            AND cloned.meta_key = '_is_cloned'
        WHERE p.post_type = 'product'
          AND p.post_status = 'publish'
          AND (cloned.meta_value IS NULL OR cloned.meta_value != 'yes')
        ORDER BY ABS(CAST(price.meta_value AS DECIMAL(20,8)) - %f)
        LIMIT 1
    ", $targetPrice));

    if (!$productId) {
      return new WP_Error('no_product', 'No available product found.');
    }

    $original = wc_get_product($productId);

    if (!$original) {
      return new WP_Error('invalid_product', 'Product not found.');
    }

    // Clone
    $clone = clone $original;
    $clone->set_id(0);

    $clone->set_name($original->get_name());
    $clone->set_status('publish');

    // Set desired price
    $clone->set_regular_price($targetPrice);
    $clone->set_price($targetPrice);

    $newProductId = $clone->save();

    // Mark original as cloned
    update_post_meta($productId, '_is_cloned', 'yes');

    $duplicator  = new WC_Admin_Duplicate_Product();
    $new_product = $duplicator->product_duplicate($original);
    return $newProductId;
  }

  public function find_closest_product_by_price1(float $targetPrice): ?WC_Product
  {
    /** @var WC_Product[] $products */
    global $wpdb;

    $productId = $wpdb->get_var(
      $wpdb->prepare(
        "
        SELECT p.ID
        FROM {$wpdb->wc_product_meta_lookup} lookup
        INNER JOIN {$wpdb->posts} p ON p.ID = lookup.product_id
        WHERE p.post_status = 'publish'
          AND p.post_type = 'product'
          AND lookup.stock_status = 'instock'
          AND lookup.min_price > 0
        ORDER BY ABS(lookup.min_price - %f)
        LIMIT 1
        ",
        $targetPrice
      )
    );

    return $productId ? wc_get_product($productId) : null;
  }
}