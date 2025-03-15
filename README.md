# Mercado Pago Payment Gateway for Bagisto

Este paquete proporciona integración con la pasarela de pago Mercado Pago para la plataforma de comercio electrónico Bagisto.

## Características

- Integración perfecta con el proceso de pago de Bagisto
- Soporte para métodos de pago de Mercado Pago
- Configurable desde el panel de administración de Bagisto
- Soporte para modo Sandbox (pruebas)
- Soporte multiidioma (Español e Inglés)

## Requisitos

- Bagisto ^2.0
- PHP ^8.1
- Composer
- Cuenta de Mercado Pago

## Instalación

### Opción 1: Instalación vía Composer (Recomendada)

```bash
composer require midnight/mercadopago
```

### Opción 2: Instalación Manual

1. Descarga este repositorio
2. Descomprime el archivo y copia la carpeta `mercadopago-payment-gateaway` en el directorio `packages/` de tu instalación de Bagisto
3. Añade la siguiente línea al archivo `composer.json` en la sección `autoload.psr-4`:
   ```json
   "Midnight\\MercadoPago\\": "packages/mercadopago-payment-gateaway/src/"
   ```
4. Añade el siguiente proveedor de servicios al archivo `config/app.php`:
   ```php
   Midnight\MercadoPago\Providers\MercadoPagoServiceProvider::class
   ```
5. Ejecuta:
   ```bash
   composer dump-autoload
   ```

## Configuración

1. Obtén tus credenciales de Mercado Pago:
   - Inicia sesión en tu cuenta de [Mercado Pago Developers](https://developers.mercadopago.com/)
   - Ve a la sección de Credenciales
   - Copia tu Access Token y Public Key

2. Configura las credenciales en tu archivo `.env`:
   ```
   MERCADOPAGO_ACCESS_TOKEN=TU_ACCESS_TOKEN
   MERCADOPAGO_PUBLIC_KEY=TU_PUBLIC_KEY
   ```

3. Configura el método de pago en el panel de administración de Bagisto:
   - Ve a **Configuración > Ventas > Métodos de Pago**
   - Busca "Mercado Pago" en la lista
   - Activa el método de pago
   - Configura el título y la descripción
   - Habilita el modo Sandbox para pruebas si es necesario
   - Guarda la configuración

## Uso

Una vez configurado, Mercado Pago aparecerá como una opción de pago durante el proceso de compra en tu tienda Bagisto.

## Pruebas

Para realizar pruebas en modo Sandbox, puedes usar las [tarjetas de prueba proporcionadas por Mercado Pago](https://www.mercadopago.com.ar/developers/es/docs/checkout-api/additional-content/test-cards).

## Solución de problemas

Si encuentras algún problema, verifica los logs de Laravel en `storage/logs/laravel.log`.

## Contribuir

Las contribuciones son bienvenidas. Por favor, envía un Pull Request o abre un Issue para discutir los cambios propuestos.

## Licencia

Este paquete está licenciado bajo la [Licencia MIT](LICENSE). 
