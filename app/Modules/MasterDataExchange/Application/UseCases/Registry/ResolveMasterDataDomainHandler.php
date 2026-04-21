<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Application\UseCases\Registry;

use App\Modules\MasterDataExchange\Domain\Contracts\MasterDataDomain;
use App\Modules\MasterDataExchange\Domain\Registries\BranchesMasterDataDomain;
use App\Modules\MasterDataExchange\Domain\Registries\LoyaltyTiersMasterDataDomain;
use App\Modules\MasterDataExchange\Domain\Registries\MenuCategoriesMasterDataDomain;
use App\Modules\MasterDataExchange\Domain\Registries\MenuItemsMasterDataDomain;
use App\Modules\MasterDataExchange\Domain\Registries\MenuPricesMasterDataDomain;
use App\Modules\MasterDataExchange\Domain\Registries\RestaurantTablesMasterDataDomain;
use App\Modules\MasterDataExchange\Domain\Registries\VouchersMasterDataDomain;
use InvalidArgumentException;

final class ResolveMasterDataDomainHandler
{
    /**
     * @var array<string,MasterDataDomain>
     */
    private array $domains;

    public function __construct(
        BranchesMasterDataDomain $branches,
        RestaurantTablesMasterDataDomain $restaurantTables,
        MenuCategoriesMasterDataDomain $menuCategories,
        MenuItemsMasterDataDomain $menuItems,
        MenuPricesMasterDataDomain $menuPrices,
        VouchersMasterDataDomain $vouchers,
        LoyaltyTiersMasterDataDomain $loyaltyTiers,
    ) {
        $this->domains = [
            $branches->key() => $branches,
            $restaurantTables->key() => $restaurantTables,
            $menuCategories->key() => $menuCategories,
            $menuItems->key() => $menuItems,
            $menuPrices->key() => $menuPrices,
            $vouchers->key() => $vouchers,
            $loyaltyTiers->key() => $loyaltyTiers,
        ];
    }

    public function handle(string $key): MasterDataDomain
    {
        if (! isset($this->domains[$key])) {
            throw new InvalidArgumentException('Unsupported master-data exchange domain ['.$key.'].');
        }

        return $this->domains[$key];
    }
}
