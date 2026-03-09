# Bancard VPOS 2.0 for Laravel

Laravel integration for Bancard VPOS 2.0 (Single Buy, Cards, Charge). Basado en la [documentación oficial Bancard](https://comercios.bancard.com.py) eCommerce Single Buy v1.22.

## Requisitos

* PHP 8.1+
* Laravel 10, 11 o 12
* GuzzleHTTP 7.x

## Instalación

```bash
composer require krugerdavid/laravel-bancard
```

## Configuración

Publicar la config:

```bash
php artisan vendor:publish --tag=bancard-config
```

Variables de entorno (.env):

```
BANCARD_PUBLIC_KEY=tu_clave_publica_32_caracteres
BANCARD_PRIVATE_KEY=tu_clave_privada_40_caracteres
BANCARD_STAGING=true
BANCARD_TIMEOUT=30
```

## Uso

### Pago ocasional (Single Buy)

Inicia un pago y obtén el `process_id` para el iframe de checkout:

```php
use KrugerDavid\Bancard\Facades\Bancard;

$result = Bancard::singleBuy(
    shopProcessId: 54322,
    amount: '10330.00',
    description: 'Ejemplo de pago',
    returnUrl: 'https://tusitio.com/finish'
);

// $result['process_id'] → usar en Bancard.Checkout.createForm()
// $result['status'] → 'success'
```

Opciones adicionales:

```php
Bancard::singleBuy(54322, '10330.00', 'Pago', 'https://x.com/finish', [
    'cancel_url' => 'https://x.com/cancel',
    'additional_data' => '099VS ORO000045',  // promociones
    'iva_amount' => '1033.00',
    'preauthorization' => 'S',
    'extra_response_attributes' => ['payment_card_type'],
]);
```

### Pago con Zimple

```php
$result = Bancard::singleBuyZimple(
    shopProcessId: 54322,
    amount: '10330.00',
    description: 'Pago Zimple',
    returnUrl: 'https://tusitio.com/finish',
    userPhone: '0981123456'
);
```

### Iframe de checkout

Incluir el script e inicializar el formulario:

```html
<script src="{{ Bancard::getCheckoutScriptUrl() }}"></script>
<div id="iframe-container"></div>
<script>
  window.onload = function() {
    Bancard.Checkout.createForm('iframe-container', '{{ $processId }}', {});
  };
</script>
```

Librería: [bancard-checkout-js](https://github.com/Bancard/bancard-checkout-js)

### Rollback (reversa)

```php
$result = Bancard::rollback(12313);
// $result['status'] === 'success'
```

### Consultar confirmación

```php
$result = Bancard::getConfirmation(12313);
// $result['confirmation']['response'] → 'S' (aprobado) o 'N'
// $result['confirmation']['response_code'] → '00' (aprobada)
```

### Catastro de tarjeta (Cards New)

```php
$result = Bancard::cardsNew(
    cardId: 1,
    userId: 966389,
    userCellPhone: '0919876543',
    userMail: 'usuario@mail.com',
    returnUrl: 'https://tusitio.com/cards/result'
);
// Usar $result['process_id'] con Bancard.Cards.createForm()
```

### Listar tarjetas del usuario

```php
$result = Bancard::usersCards(966389);
// $result['cards'] → array de tarjetas con alias_token
```

### Pago con token (Charge)

```php
$result = Bancard::charge(
    60361,
    '723215.00',
    $aliasToken,  // de usersCards
    [
        'return_url' => 'https://tusitio.com/result',
        'number_of_payments' => 1,
    ]
);
```

### Eliminar tarjeta

```php
$result = Bancard::deleteCard(966389, $aliasToken);
```

### Verificar token de confirmación (webhook)

Bancard envía un POST a tu URL de confirmación. Verifica el token:

```php
$isValid = Bancard::verifyConfirmToken(
    $shopProcessId,
    $amount,
    $receivedToken
);
```

## Inyección de dependencias

```php
use KrugerDavid\Bancard\BancardManager;

class PaymentController
{
    public function __construct(
        private BancardManager $bancard
    ) {}

    public function checkout()
    {
        $result = $this->bancard->singleBuy(...);
    }
}
```

## Factura electrónica (Billing)

Para enviar datos de facturación:

```php
use KrugerDavid\Bancard\DTOs\Billing;
use KrugerDavid\Bancard\DTOs\BillingDetail;

$billing = new Billing(
    clientRuc: '123456-1',
    clientName: 'JUAN GONZALEZ',
    clientEmail: 'juan@mail.com',
    commerceStamp: '12559969',
    commerceExpeditionPoint: '001',
    commerceEstablishment: '002',
    details: [
        new BillingDetail('Item 1', '10000.00', 10, 1),
        new BillingDetail('Item 2', '330.00', 10, 1),
    ]
);

Bancard::singleBuy(54322, '10330.00', 'Pago', 'https://x.com', [
    'billing' => $billing,
]);
```

## Operaciones implementadas

| Operación | Endpoint | Método |
|-----------|----------|--------|
| Single Buy | single_buy | POST |
| Single Buy Zimple | single_buy | POST |
| Rollback | single_buy/rollback | POST |
| Get Confirmation | single_buy/confirmations | POST |
| Cards New | cards/new | POST |
| Users Cards | users/{id}/cards | POST |
| Charge | charge | POST |
| Delete Card | users/{id}/cards | DELETE |

## Créditos

- [David Krüger](https://github.com/krugerdavid)

## Licencia

MIT
