<?php

require_once __DIR__ . '/vendor/autoload.php';

use YapaySdk\Address;
use YapaySdk\BillAffiliate;
use YapaySdk\Customer;
use YapaySdk\Environment;
use YapaySdk\Pix\PixRequest;
use YapaySdk\Store;
use YapaySdk\Yapay;

$token = 'cdeb670eb32f393';
$environment = Environment::sandbox();
$store = new Store($token, $environment);
$yapay = new Yapay($store);

$customer = new Customer(
    id: "15192",
    name: "TESTE AFILIADO",
    email: "teste@webia.com",
    document: "04272274376",
    phone: "11999999999",
    address: new Address(
        street: "Rua Teste",
        number: "123",
        zipCode: "01234567",
        neighborhood: "Bairro",
        city: "Cidade",
        state: "SP"
    )
);

// Criando afiliados com o novo formato
$affiliates = [
    new BillAffiliate(accountEmail: 'teste@itarget.com.br', percentage: 50),
    new BillAffiliate(accountEmail: 'outro@test.com', commissionAmount: 10.0),
];

$pixRequest = new PixRequest(
    amount: 100.0,
    currency: "BRL",
    customer: $customer,
    description: "Teste de Afiliados",
    number: (string)time(),
    metadata: [],
    affiliates: $affiliates
);

try {
    echo "Verificando se os afiliados estão indo no payload...\n";
    $yapay->createPixCharge($pixRequest);
} catch (Exception $e) {
    // Silencia erro de API pois só queremos ver o dump do payload
}
