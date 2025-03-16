<?php
try {
    $mercadoPagoStandard = app('Midnight\MercadoPago\Payment\Standard');
    $formFields = $mercadoPagoStandard->getFormFields();
} catch (Exception $e) {
    $formFields = [
        'error' => true,
        'message' => 'Error: ' . $e->getMessage()
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('mercadopago::app.mercadopago-redirect.title') }}</title>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 20px;
        }
        .loader {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .error {
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php if (isset($formFields['error']) && $formFields['error']): ?>
        <div class="error">
            <p><?php echo $formFields['message']; ?></p>
            <p>{{ __('mercadopago::app.mercadopago-redirect.error') }}</p>
            <a href="<?php echo route('shop.checkout.cart.index'); ?>" class="btn">{{ __('mercadopago::app.mercadopago-redirect.return-to-cart') }}</a>
        </div>
    <?php else: ?>
        <div class="loader"></div>
        <p>{{ __('mercadopago::app.mercadopago-redirect.description') }}</p>

        <?php if (isset($formFields['init_point']) && $formFields['init_point']): ?>
            <script>
                // Redirect after 5 seconds
                setTimeout(function() {
                    window.location.href = '<?php echo $formFields['init_point']; ?>';
                }, 5000);
            </script>
        <?php else: ?>
            <div id="mercadopago-button"></div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    try {
                        const mp = new MercadoPago('<?php echo $formFields['public_key']; ?>', {
                            locale: 'es-AR'
                        });

                        mp.checkout({
                            preference: {
                                id: '<?php echo $formFields['preference_id']; ?>'
                            },
                            autoOpen: true,
                            render: {
                                container: '#mercadopago-button',
                                label: '{{ __('mercadopago::app.mercadopago-redirect.pay-with-mercadopago') }}'
                            }
                        });
                    } catch (error) {
                        console.error('Error initializing Mercado Pago:', error);
                        alert('Error initializing Mercado Pago: ' + error.message);
                        window.location.href = '<?php echo route('shop.checkout.cart.index'); ?>';
                    }
                });
            </script>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
