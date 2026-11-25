# Bancard VPOS 2.0 for Laravel

[![Latest Version](https://img.shields.io/packagist/v/krugerdavid/laravel-bancard.svg?style=flat-square)](https://packagist.org/packages/krugerdavid/laravel-bancard)
[![Total Downloads](https://img.shields.io/packagist/dt/krugerdavid/laravel-bancard.svg?style=flat-square)](https://packagist.org/packages/krugerdavid/laravel-bancard)
[![License](https://img.shields.io/packagist/l/krugerdavid/laravel-bancard.svg?style=flat-square)](https://packagist.org/packages/krugerdavid/laravel-bancard)
[![PHP Version](https://img.shields.io/packagist/php-version/krugerdavid/laravel-bancard.svg?style=flat-square)](https://packagist.org/packages/krugerdavid/laravel-bancard)

Laravel integration for Bancard VPOS 2.0 (Single Buy, Cards, Charge). Basado en la [documentación oficial Bancard](https://comercios.bancard.com.py) eCommerce Single Buy v1.22.

## Why this package exists

Bancard es la pasarela de pagos líder en Paraguay. Este paquete encapsula la API VPOS 2.0 (Single Buy, tarjetas, Zimple) en una interfaz Laravel limpia: Facade, Manager inyectable, DTOs tipados y manejo de errores con excepciones. Evita duplicar lógica de tokens, URLs y validación en cada proyecto.

## Requirements

* PHP 8.2+
* Laravel 10, 11, 12 o 13
* GuzzleHTTP 7.x

## Compatibilidad

| Paquete | PHP | Laravel |
|---------|-----|---------|
| 1.1.x   | 8.2+ | 10, 11, 12, 13 |
| 1.0.x   | 8.1+ | 10, 11 |

## Installation

```bash
composer require krugerdavid/laravel-bancard
```

## Configuration

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

## Usage

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

> `description` admite máximo **20 caracteres** (doc v1.22); el paquete lo trunca automáticamente.

### Rollback (reversa)

```php
$result = Bancard::rollback(12313);
// $result['status'] === 'success'
```

Reglas de la doc oficial (v1.22):

* Solo se puede reversar **el mismo día** de la operación, y mientras no esté *cuponada* (`TransactionAlreadyConfirmed` → anulación manual por el área comercial de Bancard).
* Conviene llamarlo para transacciones canceladas o abandonadas por el usuario.
* `PaymentNotFoundError` significa que el cliente no pagó: se toma como respuesta correcta.
* `AlreadyRollbackedError`: ya existía una reversa previa.

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

### Confirmación (webhook)

Bancard envía un POST server-to-server a tu URL de confirmación con el resultado del pago. **El `return_url` es solo experiencia de usuario: el cobro debe registrarse acá.**

Payload (resumen): `operation.shop_process_id`, `operation.token`, `operation.response` (`S` aprobado / `N`), `operation.response_code`, `operation.amount`, `operation.currency`.

```php
// routes/web.php  (excluir la ruta de CSRF)
Route::post('/bancard/confirm', function (Request $request, BancardManager $bancard) {
    $op = $request->input('operation', []);

    if (! $bancard->verifyConfirmToken($op['shop_process_id'], $op['amount'], $op['token'], $op['currency'] ?? 'PYG')) {
        return response()->json(['status' => 'error'], 403);
    }

    // Idempotente: Bancard puede reintentar. Comparar también el monto con el registrado.
    if (($op['response'] ?? null) === 'S') {
        // registrar el cobro una sola vez por shop_process_id
    }

    return response()->json(['status' => 'success']);
});
```

Requisitos de la doc oficial: responder **HTTP 200 dentro de 30 segundos** (un no-200 no significa que el pago fue denegado: siempre verificá el estado), URL pública y TLS 1.2. Si el pago no se confirma tras ~10 minutos, consultá `getConfirmation` y, si no fue pagado, hacé `rollback`.

Si el callback no llega, consultá el estado con `Bancard::getConfirmation($shopProcessId)`.

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

## Related packages

- [laravel-bancard-qr](https://github.com/krugerdavid/laravel-bancard-qr) — Bancard QR (pagos con código QR)
- [laravel-pagopar](https://github.com/krugerdavid/laravel-pagopar) — Pagopar (transferencias, billeteras, múltiples medios)

## Used in Paraguay / production-ready

Este paquete está en uso en producción en comercios de Paraguay. Bancard VPOS es la pasarela estándar para pagos con tarjeta en el país.

## Credits

- [David Krüger](https://github.com/krugerdavid)

## License

MIT
