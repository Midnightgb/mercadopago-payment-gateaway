# Mercado Pago Payment Gateway for Bagisto

A payment gateway integration for Mercado Pago on the Bagisto e-commerce platform.

## Features

- Seamless integration with Bagisto checkout process
- Support for Mercado Pago payment methods
- Configurable from Bagisto admin panel
- Sandbox mode support for testing


## Requirements

- Bagisto ^2.0
- PHP ^8.1
- Composer
- Mercado Pago account

## Installation

### Option 1: Installation via Composer (Recommended)

```bash
composer require midnightgb/mercadopago
```

### Option 2: Manual Installation

1. Download this repository
2. Extract the file and copy the `mercadopago-payment-gateaway` folder to the `packages/` directory of your Bagisto installation
3. Add the following line to the `composer.json` file in the `autoload.psr-4` section:
   ```json
   "Midnight\\MercadoPago\\": "packages/mercadopago-payment-gateaway/src/"
   ```
4. Add the following service provider to the `config/app.php` file:
   ```php
   Midnight\MercadoPago\Providers\MercadoPagoServiceProvider::class
   ```
5. Run:
   ```bash
   composer dump-autoload
   ```

## Configuration

1. Get your Mercado Pago credentials:
   - Log in to your [Mercado Pago Developers](https://developers.mercadopago.com/) account
   - Go to the Credentials section
   - Copy your Access Token and Public Key

2. Configure the credentials in your `.env` file:
   ```
   MERCADOPAGO_ACCESS_TOKEN=YOUR_ACCESS_TOKEN
   MERCADOPAGO_PUBLIC_KEY=YOUR_PUBLIC_KEY
   ```

3. Configure the payment method in the Bagisto admin panel:
   - Go to **Settings > Sales > Payment Methods**
   - Find "Mercado Pago" in the list
   - Enable the payment method
   - Configure the title and description
   - Enable Sandbox mode for testing if needed
   - Save the configuration

## Usage

Once configured, Mercado Pago will appear as a payment option during the checkout process in your Bagisto store.

## Testing

For testing in Sandbox mode, you can use the [test cards provided by Mercado Pago](https://www.mercadopago.com.co/developers).

## Troubleshooting

If you encounter any issues, check the Laravel logs in `storage/logs/laravel.log`.

## Contributing

Contributions are welcome. Please submit a Pull Request or open an Issue to discuss proposed changes.

## License

This package is licensed under the [MIT License](LICENSE).

---
