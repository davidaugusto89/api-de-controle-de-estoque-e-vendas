<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ConcurrentSalesTest extends TestCase
{
    public function test_two_concurrent_decrements_only_one_succeeds_when_stock_insufficient(): void
    {
        // garante ambiente limpo
        Artisan::call('migrate:fresh');

        // Verifica disponibilidade de lock tentando criar/acquiri-lo via
        // store redis. Se não for possível adquirir, pulamos o teste.
        $store = null;
        try {
            $cacheManager = app('cache');
            // tenta obter a store redis explicitamente
            $redisStore = $cacheManager->store('redis');

            // o lock é provido pela store subjacente; tentamos criar um lock
            // e bloquear por 0 segundos (não esperar) — apenas para testar.
            if (method_exists($redisStore, 'lock')) {
                $testLock = $redisStore->lock('concurrency_test_lock', 1);
                $acquired = false;
                try {
                    $acquired = $testLock->block(0);
                } catch (\Throwable) {
                    $acquired = false;
                }

                if ($acquired) {
                    // liberamos imediatamente
                    try {
                        $testLock->release();
                    } catch (\Throwable) {
                        // ignore
                    }
                    $store = $redisStore;
                }
            }
        } catch (\Throwable $e) {
            // ignore — vamos pular abaixo se não tivermos lock
            $store = null;
        }

        if (! $store) {
            $this->markTestSkipped('Cache store does not support locks or Redis is unreachable; skipping concurrency test requiring Redis-like locks.');
        }

        $product = Product::factory()->create([
            'cost_price' => 1.00,
            'sale_price' => 2.00,
        ]);

        Inventory::updateOrCreate(['product_id' => $product->id], ['quantity' => 1, 'version' => 1]);

        $this->assertSame(1, (int) Inventory::where('product_id', $product->id)->first()->quantity);

        // Cria um script temporário que fará o bootstrap do app e executará o job diretamente
        $tmp = sys_get_temp_dir().'/concurrent_job_'.uniqid().'.php';

        $script = <<<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$productId = %d;
$qty = %d;

try {
    $job = new \App\Infrastructure\Jobs\UpdateInventoryJob(999, [['product_id' => $productId, 'quantity' => $qty]]);

    $job->handle(
        $app->make(\App\Support\Database\Transactions::class),
        $app->make(\App\Domain\Inventory\Services\InventoryLockService::class),
        $app->make(\App\Domain\Inventory\Services\StockPolicy::class),
        $app->make(\App\Infrastructure\Persistence\Eloquent\InventoryRepository::class),
        $app->make(\App\Infrastructure\Cache\InventoryCache::class)
    );

    echo "OK\n";
    exit(0);
} catch (\Throwable $e) {
    echo "ERR:" . get_class($e) . ':' . $e->getMessage() . "\n";
    exit(1);
}
PHP;

        // escreve script com product id e qty
        $script = sprintf($script, $product->id, 1);
        file_put_contents($tmp, $script);

        // executa dois processos paralelos
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $p1 = proc_open("php -d xdebug.mode=off $tmp", $descriptors, $pipes1);
        $p2 = proc_open("php -d xdebug.mode=off $tmp", $descriptors, $pipes2);

        $outs = [];
        $codes = [];

        if (is_resource($p1)) {
            $outs[] = stream_get_contents($pipes1[1]);
            fclose($pipes1[1]);
            $codes[] = proc_close($p1);
        }
        if (is_resource($p2)) {
            $outs[] = stream_get_contents($pipes2[1]);
            fclose($pipes2[1]);
            $codes[] = proc_close($p2);
        }

        // limpa arquivo temporário
        @unlink($tmp);

        // ao menos uma falha esperada (uma execução não deve conseguir decrementar pois estoque insuficiente)
        $this->assertContains(0, $codes, 'One process should succeed (exit code 0)');
        $this->assertContains(1, $codes, 'One process should fail due to insufficient stock (exit code 1)');

        // estoque final deve ser 0
        $this->assertSame(0, (int) Inventory::where('product_id', $product->id)->first()->quantity);
    }
}
