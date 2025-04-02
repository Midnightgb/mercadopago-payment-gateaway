<?php

namespace Midnight\MercadoPago\Payment;

use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use Exception;

class Standard extends MercadoPago
{
    /**
     * Payment method code.
     *
     * @var string
     */
    protected $code = 'mercadopago_standard';

    /**
     * Return mercadopago redirect url.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return route('mercadopago.standard.redirect');
    }

    /**
     * Initialize the SDK and set the access token
     *
     * @return void
     */
    protected function authenticate()
    {
        // Set the token in the SDK's config
        MercadoPagoConfig::setAccessToken($this->getConfigData('access_token'));

        // Set the runtime environment based on sandbox setting
        if ($this->getConfigData('sandbox')) {
            Log::info('MercadoPago Environment: LOCAL (sandbox)');
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        } else {
            Log::info('MercadoPago Environment: SERVER (production)');
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::SERVER);
        }
    }

    /**
     * Create a MercadoPago preference
     *
     * @return object|null
     */
    public function createPreference()
    {
        try {
            // Authenticate with Mercado Pago
            $this->authenticate();

            $cart = $this->getCart();

            if (!$cart) {
                Log::error('MercadoPago Error: Cart is null');
                return null;
            }

            // Prepare array of items with individual products
            $items = [];
            $subtotal = 0;

            foreach ($this->getCartItems() as $item) {
                $itemPrice = $this->formatCurrencyValue($item->price);
                $subtotal += $itemPrice * $item->quantity;

                $items[] = [
                    'id' => (string) $item->id,
                    'title' => $item->name,
                    'description' => substr($item->name, 0, 127),
                    'quantity' => $item->quantity,
                    'currency_id' => $cart->cart_currency_code,
                    'unit_price' => $itemPrice
                ];
            }

            if (empty($items)) {
                Log::error('MercadoPago Error: No items in cart');
                return null;
            }

            // Add shipping if applicable
            if ($cart->selected_shipping_rate) {
                $shippingPrice = $this->formatCurrencyValue($cart->selected_shipping_rate->price);
                $subtotal += $shippingPrice;

                $items[] = [
                    'id' => 'shipping',
                    'title' => trans('mercadopago::app.payment.items.shipping', ['carrier' => $cart->selected_shipping_rate->carrier_title]),
                    'description' => trans('mercadopago::app.payment.items.shipping-description'),
                    'quantity' => 1,
                    'currency_id' => $cart->cart_currency_code,
                    'unit_price' => $shippingPrice
                ];
            }

            // Calculate difference between subtotal and grand_total
            // This will include coupon discounts and taxes
            $grandTotal = $this->formatCurrencyValue($cart->grand_total);
            $difference = $this->formatCurrencyValue($subtotal - $grandTotal);

            // If there is a difference (discount or additional tax), add it as an item
            if ($difference != 0) {
                $items[] = [
                    'id' => 'adjustment',
                    'title' => $difference > 0
                        ? trans('mercadopago::app.payment.items.discount')
                        : trans('mercadopago::app.payment.items.tax'),
                    'description' => $difference > 0
                        ? trans('mercadopago::app.payment.items.discount-description')
                        : trans('mercadopago::app.payment.items.tax-description'),
                    'quantity' => 1,
                    'currency_id' => $cart->cart_currency_code,
                    'unit_price' => $difference > 0 ? -$difference : abs($difference)
                ];
            }

            // Set payer information
            $billingAddress = $cart->billing_address;

            if (!$billingAddress) {
                Log::error('MercadoPago Error: Billing address is null');
                return null;
            }

            $payer = [
                'name' => $billingAddress->first_name,
                'surname' => $billingAddress->last_name,
                'email' => $billingAddress->email
            ];

            // Create the preference request - simplified to match documentation
            $request = [
                'items' => $items,
                'payer' => $payer,
                'back_urls' => [
                    'success' => route('mercadopago.standard.success'),
                    'failure' => route('mercadopago.standard.cancel'),
                    'pending' => route('mercadopago.standard.pending')
                ],
                'auto_return' => 'approved',
                'external_reference' => (string) $cart->id
                // Removed notification_url as it's causing issues with local development
            ];

            // Log the request for debugging
            Log::info('MercadoPago Preference Request: ' . json_encode($request));

            // Instantiate a new Preference Client
            $client = new PreferenceClient();

            // Create the preference
            $preference = $client->create($request);

            // Log the response for debugging
            Log::info('MercadoPago Preference Response: ' . json_encode($preference));

            return $preference;
        } catch (MPApiException $e) {
            // Log detailed error information
            Log::error('MercadoPago API Error: ' . $e->getMessage());
            if ($e->getApiResponse()) {
                Log::error('MercadoPago API Error Details: ' . json_encode([
                    'status' => $e->getApiResponse()->getStatusCode(),
                    'content' => $e->getApiResponse()->getContent()
                ]));
            }
            return null;
        } catch (Exception $e) {
            Log::error('MercadoPago General Error: ' . $e->getMessage());
            Log::error('MercadoPago Error Trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Return form field array.
     *
     * @return array
     */
    public function getFormFields()
    {
        try {
            $preference = $this->createPreference();

            if (!$preference) {
                return [
                    'error' => true,
                    'message' => 'Error creating MercadoPago preference'
                ];
            }

            return [
                'preference_id' => $preference->id,
                'init_point' => $preference->init_point,
                'public_key' => $this->getConfigData('public_key'),
                'site_url' => $this->getMercadoPagoUrl(),
            ];
        } catch (Exception $e) {
            Log::error('MercadoPago Form Fields Error: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
}
