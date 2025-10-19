<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BigSalesSeeder extends Seeder
{
    private const PRODUCTS = 1_000;   // qtd de produtos

    private const SALES = 500_000; // qtd de vendas

    private const SALES_CHUNK = 5_000;   // chunk de insert em sales

    private const ITEMS_SUBCHUNK = 5_000;   // sub-chunk para sale_items

    private const ITEMS_PER_SALE_MIN = 1;

    private const ITEMS_PER_SALE_MAX = 4;

    public function run(): void
    {
        DB::disableQueryLog();

        // opcional: desabilitar FKs durante a seed (MySQL)
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        }

        $this->seedProductsIfNeeded();
        [$productIds, $productMap] = $this->preloadProducts();

        // loop principal de vendas
        for ($offset = 0; $offset < self::SALES; $offset += self::SALES_CHUNK) {
            $count = min(self::SALES_CHUNK, self::SALES - $offset);

            DB::transaction(function () use ($count, $productMap) {
                // 1) gera linhas de sales (sem id)
                $salesRows = [];
                for ($i = 0; $i < $count; $i++) {
                    $created = now()
                        ->subDays(random_int(0, 30))
                        ->setTime(random_int(0, 23), random_int(0, 59), random_int(0, 59));

                    $salesRows[] = [
                        'total_amount' => '0.00',
                        'total_cost'   => '0.00',
                        'total_profit' => '0.00',
                        'status'       => 'completed',
                        'created_at'   => $created,
                        'updated_at'   => $created,
                    ];
                }

                // 2) insere e recupera IDs de forma portável
                $saleIds = $this->insertReturningIds('sales', $salesRows, 'id');

                // 3) gera items e totais em memória
                $items  = [];
                $totals = []; // sale_id => ['total_amount','total_cost','total_profit']

                $prodIdList = array_keys($productMap);
                $prodCount  = count($prodIdList);

                foreach ($saleIds as $saleId) {
                    $nItems = random_int(self::ITEMS_PER_SALE_MIN, self::ITEMS_PER_SALE_MAX);

                    $amount = '0.00';
                    $cost   = '0.00';

                    for ($k = 0; $k < $nItems; $k++) {
                        $pid = $prodIdList[random_int(0, $prodCount - 1)];
                        $qty = random_int(1, 5);

                        $unitCost  = $productMap[$pid]['cost'];
                        $unitPrice = $productMap[$pid]['price'];

                        $items[] = [
                            'sale_id'    => $saleId,
                            'product_id' => $pid,
                            'quantity'   => $qty,
                            'unit_price' => $unitPrice,
                            'unit_cost'  => $unitCost,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $amount = bcadd($amount, bcmul((string) $qty, $unitPrice, 2), 2);
                        $cost   = bcadd($cost, bcmul((string) $qty, $unitCost, 2), 2);
                    }

                    $totals[$saleId] = [
                        'total_amount' => $amount,
                        'total_cost'   => $cost,
                        'total_profit' => bcsub($amount, $cost, 2),
                    ];
                }

                // 4) bulk insert dos items em sub-chunks (evita max packet)
                foreach (array_chunk($items, self::ITEMS_SUBCHUNK) as $chunk) {
                    DB::table('sale_items')->insert($chunk);
                }

                // 5) atualiza totais (1 UPDATE por sale)
                foreach ($totals as $saleId => $t) {
                    DB::table('sales')->where('id', $saleId)->update([
                        'total_amount' => $t['total_amount'],
                        'total_cost'   => $t['total_cost'],
                        'total_profit' => $t['total_profit'],
                        'updated_at'   => now(),
                    ]);
                }
            });
        }

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /** Cria os produtos caso não existam o suficiente. */
    private function seedProductsIfNeeded(): void
    {
        $current = (int) DB::table('products')->count();
        if ($current >= self::PRODUCTS) {
            return;
        }

        $toCreate = self::PRODUCTS - $current;
        $rows     = [];
        $now      = now();

        for ($i = 1; $i <= $toCreate; $i++) {
            $cost  = random_int(500, 5000)         / 100;               // 5.00–50.00
            $price = $cost + random_int(100, 2500) / 100;       // margin positiva

            $rows[] = [
                'sku'         => 'SKU-'.Str::padLeft((string) ($current + $i), 6, '0'),
                'name'        => 'Product '.($current + $i),
                'description' => null,
                'cost_price'  => number_format($cost, 2, '.', ''),
                'sale_price'  => number_format($price, 2, '.', ''),
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        foreach (array_chunk($rows, 5_000) as $chunk) {
            DB::table('products')->insert($chunk);
        }
    }

    /**
     * Pré-carrega:
     * - $productIds: lista simples de IDs (array<int>)
     * - $productMap: [id => ['cost' => string, 'price' => string]]
     */
    private function preloadProducts(): array
    {
        $products = DB::table('products')
            ->select('id', 'cost_price', 'sale_price')
            ->get();

        $ids = [];
        $map = [];
        foreach ($products as $p) {
            $ids[]             = (int) $p->id;
            $map[(int) $p->id] = [
                'cost'  => (string) $p->cost_price,
                'price' => (string) $p->sale_price,
            ];
        }

        return [$ids, $map];
    }

    /**
     * Insere várias linhas em $table e retorna os IDs gerados.
     * - pgsql/sqlsrv: usa INSERT ... RETURNING id (um round-trip)
     * - mysql: usa lastInsertId() para obter faixa [first..last]
     *
     * @return array<int,int> IDs
     */
    private function insertReturningIds(string $table, array $rows, string $idCol = 'id'): array
    {
        DB::table($table)->insert($rows);

        $lastId  = (int) DB::getPdo()->lastInsertId();
        $count   = count($rows);
        $firstId = $lastId - $count + 1;

        // segurança básica: se algo estiver estranho, não assuma faixa
        if ($firstId < 1) {
            // fallback (raro): consultar últimos N IDs
            return DB::table($table)
                ->orderByDesc($idCol)
                ->limit($count)
                ->pluck($idCol)
                ->map(fn ($v) => (int) $v)
                ->sort()
                ->values()
                ->all();
        }

        return range($firstId, $lastId);
    }

    /** Quota identificadores de forma simples por driver. */
    private function quoteIdent(string $ident, string $driver): string
    {
        // suporta schema.nome.tab -> quebra e quota cada parte
        $parts = explode('.', $ident);

        // mysql
        return implode('.', array_map(fn ($p) => '`'.str_replace('`', '``', $p).'`', $parts));
    }
}
