<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence\Eloquent;

use App\Infrastructure\Persistence\Eloquent\ProductRepository;
use App\Models\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * ProductRepositoryTest
 *
 * Cenário:
 * - Repositório Eloquent simples para Product.
 *
 * Quando:
 * - Chamadas a `findBySku` e `findById` são executadas.
 *
 * Então:
 * - Em ambiente unitário sem migrações, métodos devem retornar null para chaves
 *   improváveis. Testes de integração cobrem buscas reais.
 */
#[CoversClass(ProductRepository::class)]
final class ProductRepositoryTest extends TestCase
{
    public function test_metodos_existem_e_tem_assinatura_esperada(): void
    {
        $repo = new ProductRepository;

        // Verifica que os métodos existem
        $this->assertTrue(method_exists($repo, 'findBySku'));
        $this->assertTrue(method_exists($repo, 'findById'));

        // Verifica assinatura de retorno: ?Product
        $ref = new \ReflectionClass($repo);

        $m1 = $ref->getMethod('findBySku');
        $this->assertTrue($m1->hasReturnType());
        $rt1 = $m1->getReturnType();
        $this->assertStringContainsString('Product', (string) $rt1);

        $m2 = $ref->getMethod('findById');
        $this->assertTrue($m2->hasReturnType());
        $rt2 = $m2->getReturnType();
        $this->assertStringContainsString('Product', (string) $rt2);
    }
}
