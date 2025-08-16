<?php

namespace Midnight\MercadoPago\Payment;

use Illuminate\Support\Facades\Storage;
use Webkul\Payment\Payment\Payment;

abstract class MercadoPago extends Payment
{
    /**
     * MercadoPago API URL getter
     *
     * @return string
     */
    public function getMercadoPagoUrl()
    {
        return $this->getConfigData('sandbox')
            ? 'https://sandbox.mercadopago.com'
            : 'https://www.mercadopago.com';
    }
    /**
     * Add order item fields
     *
     * @param  array  $fields
     * @param  int  $i
     * @return void
     */
    protected function addLineItemsFields(&$fields)
    {
        $cartItems = $this->getCartItems();
        $items = [];

        foreach ($cartItems as $item) {
            $items[] = [
                'id'          => $item->id,
                'title'       => $item->name,
                'description' => $item->name,
                'quantity'    => $item->quantity,
                'currency_id' => $this->getCart()->cart_currency_code,
                'unit_price'  => $this->formatCurrencyValue($item->price),
            ];
        }

        $fields['items'] = $items;
    }

    /**
     * Add billing address fields
     *
     * @param  array  $fields
     * @return void
     */
    protected function addAddressFields(&$fields)
    {
        $cart = $this->getCart();
        $billingAddress = $cart->billing_address;

        $fields['payer'] = [
            'name'    => $billingAddress->first_name,
            'surname' => $billingAddress->last_name,
            'email'   => $billingAddress->email,
            'phone'   => [
                'area_code' => '',
                'number'    => $this->formatPhone($billingAddress->phone),
            ],
            'address' => [
                'zip_code'    => $billingAddress->postcode,
                'street_name' => $billingAddress->address,
                'street_number' => '',
            ],
        ];
    }

    /**
     * Format a currency value according to mercadopago's api constraints
     * - Preserve cents for currencies that support them
     * - Round to whole numbers only for zero-decimal currencies (e.g., JPY, CLP)
     * - Preserve the sign (needed for discounts/adjustments)
     *
     * @param  float|int  $number
     */
    public function formatCurrencyValue($number)
    {
        $cart = method_exists($this, 'getCart') ? $this->getCart() : null;
        $currencyCode = $cart && isset($cart->cart_currency_code)
            ? strtoupper($cart->cart_currency_code)
            : 'USD';

        $isZeroDecimal = in_array($currencyCode, $this->getZeroDecimalCurrencies(), true);

        // Round to the appropriate precision and preserve sign
        $rounded = round((float) $number, $isZeroDecimal ? 0 : 2);

        // Normalize negative zero values
        if ($rounded == 0) {
            return $isZeroDecimal ? 0 : 0.0;
        }

        return $isZeroDecimal ? (int) $rounded : (float) $rounded;
    }

    /**
     * List of ISO currency codes that do not use decimal fractions
     *
     * @return array<string>
     */
    protected function getZeroDecimalCurrencies(): array
    {
        return [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
            'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'
        ];
    }

    /**
     * Format phone field according to mercadopago's api constraints
     *
     * @param  mixed  $phone
     */
    public function formatPhone($phone): string
    {
        return preg_replace('/[^0-9]/', '', (string) $phone);
    }

    /**
     * Returns payment method image
     *
     * @return array
     */
    public function getImage()
    {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : 'https://img.icons8.com/color/512/mercado-pago.png';
    }
}
