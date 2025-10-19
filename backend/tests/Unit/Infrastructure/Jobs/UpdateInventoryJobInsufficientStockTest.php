<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Jobs;

use App\Domain\Inventory\Services\InventoryLockService;
use App\Domain\Inventory\Services\StockPolicy;
use App\Infrastructure\Cache\InventoryCache;
use App\Infrastructure\Jobs\UpdateInventoryJob;
use App\Infrastructure\Persistence\Eloquent\InventoryRepository;
use App\Support\Database\Transactions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateInventoryJob::class)]
final class UpdateInventoryJobInsufficientStockTest extends TestCase
{
    public function test_job_throws_when_inventory_insufficient(): void
    {
        $items = [['product_id' => 42, 'quantity' => 1000]];

        $tx = $this->getMockBuilder(Transactions::class)
            ->onlyMethods(['run'])
            ->getMock();

        $locks = $this->getMockBuilder(InventoryLockService::class)
            ->onlyMethods(['lock'])
            ->getMock();

        $policy = $this->getMockBuilder(StockPolicy::class)
            ->onlyMethods(['decrease'])
            ->getMock();

        $inventoryRepo = $this->getMockBuilder(InventoryRepository::class)
            ->onlyMethods(['decrementIfEnough'])
            ->getMock();

        $cacheStore = $this->getMockBuilder(\Illuminate\Contracts\Cache\Repository::class)
            ->getMock();
        $cache = new InventoryCache($cacheStore);

        $tx->expects($this->once())
            ->method('run')
            ->with($this->isType('callable'))
            ->willReturnCallback(function (callable $cb) {
                $cb();
            });

        $locks->expects($this->once())
            ->method('lock')
            ->willReturnCallback(function (int $productId, callable $cb) {
                $cb();
            });

        $inventoryRepo->expects($this->once())
            ->method('decrementIfEnough')
            ->willReturn(false);

        $this->expectException(\DomainException::class);

        $job = new UpdateInventoryJob(5, $items);

        $job->handle($tx, $locks, $policy, $inventoryRepo, $cache);
    }
}
