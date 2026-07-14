<?php
namespace Dell\WpShieldpp\Paypal;

//v2
use WC_Product;
use Dell\WpShieldpp\Paypal\Entity\Item;
use Dell\WpShieldpp\Paypal\Entity\Money;
use Dell\WpShieldpp\Service\IpSignService;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

class PaypalType2
{
    public function __construct(
        private ProxyPayPalHttpClient $client,
        private $path,
        private $invoiceIdPrefix,
    ) {
    }

    public function createOrder($order, $cs_ref_code, $customerIp)
    {
        $order_id = $order->get_id();
        // $checkoutUrl = wc_get_checkout_url();
        // $returnUrl = $checkoutUrl;
        // $cancelUrl = $checkoutUrl;

        $siteUrl = site_url();
        $cancelUrl = $siteUrl . '/wc-api/wc_cancel/';
        $computedSign = IpSignService::genIpSign($customerIp, $order_id);
        $params = [
            'sign' => $computedSign,
        ];
        $baseReturnUrl = $this->path;
        if (!$baseReturnUrl) {
            $params = [
                'orderId' => $order_id,
                'csRefCode' => $cs_ref_code,
                ...$params,
            ];
            $siteUrl . '/wc-api/wc_return_url';
            $baseReturnUrl = WC()->api_request_url('wc_return_url');
        }
        // $escapedQuery = wp_unslash($params);
        // $returnUrl = add_query_arg($escapedQuery, $baseReturnUrl);
        $returnUrl = WC()->api_request_url('wc_return_url');
        $cancelUrl = WC()->api_request_url('wc_cancel');

        $client = $this->client;
        // $unique_invoice_id = $order_id . '-' . time();
        $unique_invoice_id = $this->invoiceIdPrefix . $order_id;

        $purchaseUnitItems =
            $this->getPurchaseUnitItems($order);
        $amount =
            $this->amount_from_wc_order($order);
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $payload = [
            "intent" => "CAPTURE",
            "purchase_units" => [
                [
                    "reference_id" => $order_id,
                    "amount" => [
                        ...$amount
                    ],
                    "description" => "",
                    ...$purchaseUnitItems,
                    "invoice_id" => $unique_invoice_id,
                ]
            ],
            "application_context" => [
                "cancel_url" => $cancelUrl,
                "return_url" => $returnUrl,
            ]
        ];

        $request->body = $payload;
        $response = $client->execute($request);
        $result = $response->result;
        $paypalOrderId = $result->id ?? '';

        $approvalLink = null;
        foreach ($result->links as $link) {
            if ($link->rel === 'approve') {
                $approvalLink = $link->href;
                break;
            }
        }

        update_post_meta($order_id, 'paypal_order_id', $paypalOrderId);
        update_post_meta($order_id, 'paypal_invoice_id', $unique_invoice_id);
        update_post_meta($order_id, 'cs_pp_payment_link', $approvalLink);

        return [
            'status' => true,
            'payment_link' => $approvalLink,
        ];
    }

    private function getPurchaseUnitItems($order)
    {
        $items =
            $this->order_item_from_wc_order($order);
        $items = array_map(function ($item) {
            return $item->to_array();
        }, $items);
        $item = reset($items) ?? null;
        if (!$item) {
            return [];

        }
        $total = $order->get_total();
        $item['unit_amount']['value'] = $total;
        return ['items' => [$item]];
    }

    /**
     * Returns an Amount object based off a WooCommerce order.
     *
     * @param \WC_Order $order The order.
     *
     */
    public function amount_from_wc_order(\WC_Order $order)
    {
        $currency = $order->get_currency();
        $currency = 'USD';
        // $items = $this->item_factory->from_wc_order($order);
        // $discount_value = array_sum(array(
        //     (float) $order->get_total_discount(),
        //     // Only coupons.
        //     $this->discounts_from_items($items),
        // ));
        $discount = null;
        // if ($discount_value) {
        //     $discount = new Money((float) $discount_value, $currency);
        // }
        $total_value = (float) $order->get_total();
        // if ((in_array($order->get_payment_method(), array(CreditCardGateway::ID, CardButtonGateway::ID), \true) || PayPalGateway::ID === $order->get_payment_method() && 'card' === $order->get_meta(PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY)) && $this->is_free_trial_order($order)) {
        //     $total_value = 1.0;
        // }
        $total = new Money($total_value, $currency);
        $item_total = new Money((float) $order->get_subtotal() + (float) $order->get_total_fees(), $currency);
        $shipping = new Money((float) $order->get_shipping_total(), $currency);
        $tax_total = new Money((float) $order->get_total_tax(), $currency);


        $breakdown = array();
        if ($item_total) {
            $breakdown['item_total'] = $item_total->to_array();
        }
        if ($shipping) {
            $breakdown['shipping'] = $shipping->to_array();
        }
        if ($tax_total) {
            $breakdown['tax_total'] = $tax_total->to_array();
        }
        // if ($handling) {
        //     $breakdown['handling'] = $handling->to_array();
        // }
        // if ($insurance) {
        //     $breakdown['insurance'] = $insurance->to_array();
        // }
        // if ($shipping_discount) {
        //     $breakdown['shipping_discount'] = $shipping_discount->to_array();
        // }
        if ($discount) {
            $breakdown['discount'] = $discount->to_array();
        }
        $amountArray = $total->to_array();
        if ($breakdown && \count($breakdown)) {
            $amountArray['breakdown'] = $breakdown;
        }
        return $amountArray;
    }
    /**
     * Creates Items based off a WooCommerce order.
     *
     * @param \WC_Order $order The order.
     * @return Item[]
     */
    public function order_item_from_wc_order($order): array
    {
        $items = array_map(
            // @phpstan-ignore argument.type
            function (\WC_Order_Item_Product $item) use ($order) {
                return $this->from_wc_order_line_item($item, $order);
            },
            $order->get_items('line_item')
        );
        $fees = array_map(function (\WC_Order_Item_Fee $item) use ($order) {
            return $this->from_wc_order_fee($item, $order);
        }, $order->get_fees());
        return array_merge($items, $fees);
    }
    /**
     * Creates an Item based off a WooCommerce Order Item.
     *
     * @param \WC_Order_Item_Product $item The WooCommerce order item.
     * @param \WC_Order              $order The WooCommerce order.
     *
     * @return Item
     */
    private function from_wc_order_line_item(\WC_Order_Item_Product $item, \WC_Order $order): Item
    {
        $product = $item->get_product();
        $currency = $order->get_currency();
        $currency = 'USD';
        $quantity = (int) $item->get_quantity();
        $price_without_tax = (float) $order->get_item_subtotal($item, \false);
        $price_without_tax_rounded = round($price_without_tax, 2);
        $image = $product instanceof WC_Product ? wp_get_attachment_image_src((int) $product->get_image_id(), 'full') : '';
        $line_tax = (float) $item->get_total_tax();
        $unit_tax = $quantity > 0 ? $line_tax / (float) $quantity : 0.0;
        return new Item(
            $this->prepare_item_string($item->get_name()),
            new Money($price_without_tax_rounded, $currency),
            $quantity,
            $product instanceof WC_Product ? $this->prepare_item_string($product->get_description()) : '',
            $unit_tax ? new Money($unit_tax, $currency) : null,
            $product instanceof WC_Product ? $this->prepare_sku($product->get_sku()) : '',
            $product instanceof WC_Product && $product->is_virtual() ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS,
            $product instanceof WC_Product ? $product->get_permalink() : '',
            $image[0] ?? '',
            0,
            null,
            $product instanceof WC_Product ? $product->get_id() : null,
            new Money((float) $item->get_subtotal() - (float) $item->get_total(), $currency)
        );
    }

    /**
     * Creates an Item based off a WooCommerce Fee Item.
     *
     * @param \WC_Order_Item_Fee $item The WooCommerce order item.
     * @param \WC_Order          $order The WooCommerce order.
     *
     * @return Item
     */
    private function from_wc_order_fee(\WC_Order_Item_Fee $item, \WC_Order $order): Item
    {
        return new Item(
            $this->prepare_item_string($item->get_name()),
            new Money((float) $item->get_amount(), $order->get_currency()),
            $item->get_quantity(),
            '',
            null
        );
    }

    /**
     * Cleans up item strings (title and description for example) and prepares them for sending to PayPal.
     *
     * @param string $string Item string.
     * @return string
     */
    protected function prepare_item_string(string $string): string
    {
        $string = strip_shortcodes(wp_strip_all_tags($string));
        return substr($string, 0, 127) ?: '';
    }
    /**
     * Prepares the sku for sending to PayPal.
     *
     * @param string $sku Item sku.
     * @return string
     */
    protected function prepare_sku(string $sku): string
    {
        return substr(wp_strip_all_tags($sku), 0, 127) ?: '';
    }
}
