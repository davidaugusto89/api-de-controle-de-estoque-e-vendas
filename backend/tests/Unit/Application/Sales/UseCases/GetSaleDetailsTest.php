<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Sales\UseCases;

use App\Application\Sales\UseCases\GetSaleDetails;
use App\Infrastructure\Persistence\Eloquent\SaleRepository;
use App\Models\Sale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Cenário: recuperar detalhes de uma venda usando o repositório.
 *
 * Quando: a aplicação solicita os detalhes de uma venda existente ou inexistente.
 *
 * Então:
 *  - para venda existente retorna array com campos esperados e items normalizados;
 *  - para venda inexistente lança ModelNotFoundException.
 *
 * Observações:
 *  - teste unitário puro: repositório é mockado;
 *  - formatação de datas e tipos é verificada com asserts estritos quando aplicável.
 */
#[CoversClass(GetSaleDetails::class)]
final class GetSaleDetailsTest extends TestCase
{
    private SaleRepository&MockObject $salesRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesRepo = $this->createMock(SaleRepository::class);
    }

    public static function provider_success_and_variants(): array
    {
        $base = [
            'id'           => 123,
            'total_amount' => 100.5,
            'total_cost'   => 70.25,
            'total_profit' => 30.25,
            'status'       => 'completed',
            'created_at'   => '2025-10-18T12:00:00.000000Z',
            'items'        => [
                [
                    'product_id' => 1,
                    'quantity'   => 2,
                    'unit_price' => 25.25,
                    'unit_cost'  => 17.5,
                ],
            ],
        ];

        // caso padrão
        $case1 = [$base];

        // caso: sem items
        $noItems          = $base;
        $noItems['items'] = [];
        $case2            = [$noItems];

        return [
            'com_items' => $case1,
            'sem_items' => $case2,
        ];
    }

    /**
     * @dataProvider provider_success_and_variants
     */
    #[DataProvider('provider_success_and_variants')]
    public function test_retorna_detalhes_da_venda_quando_existir(array $repoReturn): void
    {
        // Arrange: mock do repositório retornando instância de Model compatível
        // Criar instância de Sale sem invocar o construtor do Eloquent/Container
        $sale = new class extends Sale
        {
            public $id;

            public $total_amount;

            public $total_cost;

            public $total_profit;

            public $status;

            public $created_at;

            public $items;

            // evita chamada ao Model::__construct que depende do container
            public function __construct() {}
        };

        $sale->id           = $repoReturn['id'];
        $sale->total_amount = $repoReturn['total_amount'];
        $sale->total_cost   = $repoReturn['total_cost'];
        $sale->total_profit = $repoReturn['total_profit'];
        $sale->status       = $repoReturn['status'];
        $sale->created_at   = Carbon::parse($repoReturn['created_at']);
        $sale->items        = collect(array_map(fn ($i) => (object) $i, $repoReturn['items']));

        $this->salesRepo
            ->expects($this->once())
            ->method('findWithItems')
            ->with($this->equalTo($repoReturn['id']))
            ->willReturn($sale);

        $useCase = new GetSaleDetails($this->salesRepo);

        // Act
        $result = $useCase->execute($repoReturn['id']);

        // Assert: checagens de estrutura e tipos
        $this->assertSame($repoReturn['id'], $result['id']);
        $this->assertEqualsWithDelta((float) $repoReturn['total_amount'], $result['total_amount'], 0.0001);
        $this->assertEqualsWithDelta((float) $repoReturn['total_cost'], $result['total_cost'], 0.0001);
        $this->assertEqualsWithDelta((float) $repoReturn['total_profit'], $result['total_profit'], 0.0001);
        $this->assertSame($repoReturn['status'], $result['status']);
        $this->assertSame($repoReturn['created_at'], $result['created_at']);

        // items
        $this->assertIsArray($result['items']);
        $this->assertCount(count($repoReturn['items']), $result['items']);

        foreach ($result['items'] as $idx => $it) {
            $expected = $repoReturn['items'][$idx];
            $this->assertSame($expected['product_id'], $it['product_id']);
            $this->assertSame($expected['quantity'], $it['quantity']);
            $this->assertEqualsWithDelta((float) $expected['unit_price'], $it['unit_price'], 0.0001);
            $this->assertEqualsWithDelta((float) $expected['unit_cost'], $it['unit_cost'], 0.0001);
        }
    }

    public function test_lanca_excecao_quando_nao_encontrar_venda(): void
    {
        /**
         * Cenário
         * Dado: repositório que não encontra venda
         * Quando: `execute(id)` é chamado com id inexistente
         * Então: ModelNotFoundException é lançada
         */
        $this->salesRepo
            ->expects($this->once())
            ->method('findWithItems')
            ->with($this->equalTo(999))
            ->willReturn(null);

        $useCase = new GetSaleDetails($this->salesRepo);

        $this->expectException(ModelNotFoundException::class);

        $useCase->execute(999);
    }
}
