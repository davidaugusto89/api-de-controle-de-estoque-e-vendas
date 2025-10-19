<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para serialização consistente do relatório de vendas.
 *
 * @property-read array $resource
 */
final class ReportResource extends JsonResource
{
    /**
     * @return array{
     *   period: array{from:string,to:string},
     *   totals: array{
     *     total_sales:int,total_amount:float,total_cost:float,total_profit:float,avg_ticket:float
     *   },
     *   series: array<int, array{date:string,total_amount:float,total_profit:float,orders:int}>,
     *   top_products: array<int, array{product_id:int,sku:?string,name:?string,quantity:int,amount:float,profit:float}>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'period'       => $this->resource['period'],
            'totals'       => $this->resource['totals'],
            'series'       => $this->resource['series'],
            'top_products' => $this->resource['top_products'],
        ];
    }
}
