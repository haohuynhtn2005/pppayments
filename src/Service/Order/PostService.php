<?php
namespace ShieldPpPayment\Service\Order;

class PostService
{
  public function getPostIdViaPaypalInvoiceId($invoiceId)
  {
    global $wpdb;
    $query = <<<SQL
      SELECT post_id
      FROM {$wpdb->postmeta}
      WHERE meta_key = %s
      AND meta_value = %s
      LIMIT 1
    SQL;
    $postId = $wpdb->get_var(
      $wpdb->prepare(
        $query,
        'paypal_invoice_id',
        $invoiceId
      )
    );
    return $postId;
  }
  
  public function getPostIdViaPaypalOrderId($orderId)
  {
    global $wpdb;
    $query = <<<SQL
      SELECT post_id
      FROM {$wpdb->postmeta}
      WHERE meta_key = %s
      AND meta_value = %s
      LIMIT 1
    SQL;
    $postId = $wpdb->get_var(
      $wpdb->prepare(
        $query,
        'paypal_order_id',
        $orderId
      )
    );
    return $postId;
  }
}