# SDK PHP Yapay

SDK de integração com Yapay (Boleto, Pix, Cartão de Crédito), com consulta de transações e parser de webhook de liquidação.

## Funcionalidades

- Pix: geração e consulta de status
- Cartão de crédito: criação e cobrança (inclui parcelamento)
- Boleto: emissão e consulta de dados
- Consulta de transação por `token_transaction` / `transaction_id`
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
    environment: Environment::sandbox() // ou Environment::production()
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
$lookup = $yapay->getTransactionByToken('cb22c716c80ddbaa16f8b8dbc49302a2');
$parsed = Yapay::parseTransactionLookup($lookup);
```

### Facade (Cartão e Boleto)

O facade também expõe:

- `createCreditCardPayment()`
- `processInstallmentCreditCardPayment()`
- `generateBank()`
- `getBankData()`
- `checkPixStatus()`
- `getPixPayload()`
- `checkPaymentStatus()`

### Webhook de liquidação

```php
<?php
$parsed = Yapay::parseSettlementWebhook($payload);
// tid, transactionId, tokenTransaction, paymentMethodCode, statusCode, lowDate, occurrenceDate
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
