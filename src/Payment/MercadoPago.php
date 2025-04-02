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
     * Some currencies like COP don't use cents, so no need to multiply by 100
     * Ensure value is positive and integer as MercadoPago requires
     *
     * @param  float|int  $number
     */
    public function formatCurrencyValue($number): int
    {
        // This works correctly for currencies without cents like COP
        $value = (int) round(abs((float) $number));

        // Ensure we return at least 1 if the value is very small but not zero
        if ($number != 0 && $value == 0) {
            return 1;
        }

        return $value;
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
