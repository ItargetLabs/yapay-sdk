# SDK PHP Yapay

SDK de integração com Yapay (Boleto, Pix, Cartão de Crédito), com consulta de transações e parser de webhook de liquidação.

## Funcionalidades

- Pix: geração e consulta de status
- Cartão de crédito: criação e cobrança (inclui parcelamento)
- Boleto: emissão e consulta de dados
- Consulta de transação por `token_transaction` / `transaction_id`
- Extração pública de dados da consulta (valor, boleto, Pix e cartão)
- Listagem de transações (`GET /api/v3/sales`, requer `ACCESS_TOKEN`)
- Mapeamento de status de pagamento e parser de webhook de liquidação

## Requisitos

- PHP >= 8.1
- Guzzle HTTP

## Instalação

```bash
composer require devsitarget/sdk-yapay-php
```

## Configuração

```php
<?php
use YapaySdk\Environment;
use YapaySdk\Store;
use YapaySdk\Yapay;

$store = new Store(
    tokenAccount: 'SEU_TOKEN_ACCOUNT',
    environment: Environment::sandbox(), // ou Environment::production()
    accessToken: 'SEU_ACCESS_TOKEN' // opcional; necessário para listar transações
);

$yapay = new Yapay($store);
```

## Uso Básico

### Pix: gerar cobrança

```php
<?php
use YapaySdk\Customer;
use YapaySdk\Address;
use YapaySdk\Pix\PixRequest;

$customer = new Customer(
    id: '123',
    name: 'Cliente',
    email: 'cliente@exemplo.com',
    document: '12345678900',
    phone: '11999999999',
    address: new Address(
        street: 'Rua A',
        number: '100',
        zipCode: '01234567',
        neighborhood: 'Centro',
        city: 'Sao Paulo',
        state: 'SP'
    )
);

$pixResponse = $yapay->createPixCharge(new PixRequest(
    amount: 120.50,
    currency: 'BRL',
    customer: $customer,
    description: 'Pedido 123',
    number: '123'
));
```

### Consultar transação

```php
<?php
$status = $yapay->getBankData('cb22c716c80ddbaa16f8b8dbc49302a2');
echo "Link do Boleto: " . $status->url . PHP_EOL;
```

### Extrair dados da consulta (boleto, Pix e cartão)

Os métodos abaixo são **públicos** na instância de `Yapay` (não são estáticos). Recebem o retorno de `getTransactionByToken()` / `getTransactionById()` e funcionam para boleto, Pix e cartão de crédito — o mesmo payload da Yapay (`data_response.transaction.payment`).

`parseTransactionLookup()` devolve o conjunto completo:

| Campo | Origem na Yapay | Boleto | Pix | Cartão |
| --- | --- | --- | --- | --- |
| `amount` | `price_payment` | sim | sim | sim |
| `tid` / `transactionId` | `transaction_id` | sim | sim | sim |
| `statusCode` | `status_id` / `status_name` | sim | sim | sim |
| `paymentMethodCode` | `payment_method_id` | `bank_slip` | `pix` | `credit_card` |
| `nsu` | `number_proccess` | sim | sim | sim |
| `authorizationCode` | `payment_response_code` | sim | sim | sim |
| `installments` | `split` | — | — | sim |
| `digitableLine` | `linha_digitavel` | sim | — | — |
| `barCode` | `bar_code` | sim | — | — |
| `pixCopyPaste` | `pix_copy_paste` / `qrcode_original_path` | — | sim | — |

Campos de outro meio de pagamento voltam `null` (ou `0` no valor, se não existir).

```php
<?php
$raw = $yapay->getTransactionByToken('cb22c716c80ddbaa16f8b8dbc49302a2');
$parsed = $yapay->parseTransactionLookup($raw);

echo $parsed['amount'];
echo $parsed['statusCode'];
echo $parsed['paymentMethodCode'];
echo $parsed['nsu'];
echo $parsed['authorizationCode'];
```

Ou campo a campo:

```php
<?php
$amount = $yapay->lookupAmount($raw);

// Boleto
$digitableLine = $yapay->lookupDigitableLine($raw);
$barCode = $yapay->lookupBarCode($raw);

// Pix
$pixCopyPaste = $yapay->lookupPixCopyPaste($raw);
```

Cartão de crédito usa o mesmo parse para parcelas, NSU e autorização:

```php
<?php
$raw = $yapay->getTransactionByToken('token_do_cartao');
$parsed = $yapay->parseTransactionLookup($raw);

$amount = $parsed['amount']; // ou $yapay->lookupAmount($raw)
$installments = (int) ($parsed['installments'] ?? 1);
$nsu = $parsed['nsu'];
$authorizationCode = $parsed['authorizationCode'];
```

### Listar transações

Essa API exige `ACCESS_TOKEN` (não o `token_account`). O retorno traz o `transaction_token`, que pode ser usado em `getTransactionByToken()` para o detalhe completo.

```php
<?php
$lista = $yapay->listTransactions([
    'page' => 1,
    'per_page' => 20,
    // 'id' => '1727671', // opcional: uma transação específica
]);

foreach ($lista['data'] as $sale) {
    echo $sale['id'] . ' ' . $sale['transaction_token'] . PHP_EOL;
}

$detalhe = $yapay->getTransactionByToken($lista['data'][0]['transaction_token']);
```

Para gerar ou renovar o token:

```php
<?php
$yapay->generateAccessToken('CONSUMER_KEY', 'CONSUMER_SECRET', 'CODE');
$yapay->refreshAccessToken(refreshToken: 'SEU_REFRESH_TOKEN');
```

### Facade

O facade também expõe:

- `createCreditCardPayment()`
- `processInstallmentCreditCardPayment()`
- `generateBank()`
- `getBankData()`
- `checkPixStatus()`
- `getPixPayload()`
- `checkPaymentStatus()`
- `listTransactions()`
- `generateAccessToken()` / `refreshAccessToken()`
- `parseTransactionLookup()`
- `lookupAmount()` / `lookupDigitableLine()` / `lookupBarCode()` / `lookupPixCopyPaste()`

### Webhook de liquidação

```php
<?php
$parsed = Yapay::parseSettlementWebhook($payload);
// tid, transactionId, tokenTransaction, paymentMethodCode, statusCode,
// lowDate, occurrenceDate, authorizationCode, nsu, installments
```

## Split (affiliates)

As requests aceitam `affiliates` via objetos `BillAffiliate` (mesma ideia do SDK da Vindi).

```php
<?php
use YapaySdk\BillAffiliate;

$affiliates = [
    new BillAffiliate(accountEmail: 'teste@itarget.com.br', percentage: 50), // 50%
    new BillAffiliate(accountEmail: 'outro@test.com', commissionAmount: 10.0), // R$ 10,00
];
```

Observações:
- Se você precisar enviar a estrutura crua da Yapay, use `metadata['split_rules']`.
- O SDK repassa `split_rules` sem transformar.

## Testes e qualidade

```bash
composer install
composer test
composer phpstan
composer cs-check
composer cs-fix
```

## Docker (opcional)

```bash
make build
make up
make install
make test
make phpstan
make cs-check
make cs-fix
make down
```

## Desenvolvimento

### Estrutura do Projeto

```
yapay-sdk/
├── src/
│   └── Yapay/
│       ├── Environment.php
│       ├── Store.php
│       ├── PaymentStatus.php
│       ├── YapayBaseClient.php
│       ├── Yapay.php
│       ├── Pix/
│       ├── CreditCard/
│       └── Bank/
├── tests/
│   └── Unit/
├── composer.json
├── phpunit.xml
├── env.example
└── README.md
```

### Padrões de Código

- PSR-4 para autoload
- Injeção de dependência (Guzzle Client)
- Testes unitários com mock de HTTP

## Licença

MIT
