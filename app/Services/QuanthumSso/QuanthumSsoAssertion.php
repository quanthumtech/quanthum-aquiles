<?php

namespace App\Services\QuanthumSso;

/**
 * O que o app aceita do SSO depois de validar o id_token. Nada aqui é usado
 * como chave além de issuer + sub; o e-mail só serve para o vínculo inicial.
 */
final readonly class QuanthumSsoAssertion
{
    public function __construct(
        public string $issuer,
        public string $sub,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
        public string $idToken,
    ) {}
}
