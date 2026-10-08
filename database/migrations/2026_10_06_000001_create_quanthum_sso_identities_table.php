<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quanthum_sso_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // issuer + sub é a chave externa estável (o e-mail pode mudar e
            // nunca é chave). VARBINARY: `sub` é opaco, então a comparação
            // tem que ser byte a byte. Nenhuma colação do MySQL serve: até
            // utf8mb4_bin ignora espaços à direita (PAD SPACE).
            $table->binary('issuer', 255);
            $table->binary('sub', 255);
            $table->timestamps();

            $table->unique(['issuer', 'sub']);
            // Um usuário tem no máximo UMA identidade por issuer: é o que
            // impede dois callbacks concorrentes (subs distintos, mesmo
            // e-mail verificado) de gravarem duas identidades.
            $table->unique(['user_id', 'issuer']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quanthum_sso_identities');
    }
};
