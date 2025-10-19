<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_cria_usuario_valido(): void
    {
        /**
         * Cenário
         * Dado: factory do User
         * Quando: create é invocado
         * Então: usuário é persistido e possui id e email
         */
        $user = User::factory()->create();

        $this->assertNotNull($user->id);
        $this->assertNotNull($user->email);
    }

    public function test_password_esta_hidden_na_serializacao(): void
    {
        /**
         * Cenário
         * Dado: instância de User gerada por factory (não persistida)
         * Quando: toArray é chamado
         * Então: campo password não está presente na serialização
         */
        $user = User::factory()->make();

        $arr = $user->toArray();

        $this->assertArrayNotHasKey('password', $arr);
    }
}
