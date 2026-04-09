<?php

declare(strict_types=1);

namespace App\Services\Admin\MasterDataBulk;

use App\Services\Admin\MasterDataBulk\Contracts\MasterDataBulkDomain;
use App\Services\Admin\MasterDataBulk\Domains\BranchesBulkDomain;
use App\Services\Admin\MasterDataBulk\Domains\LoyaltyTiersBulkDomain;
use App\Services\Admin\MasterDataBulk\Domains\MenuCategoriesBulkDomain;
use App\Services\Admin\MasterDataBulk\Domains\MenuItemsBulkDomain;
use App\Services\Admin\MasterDataBulk\Domains\MenuPricesBulkDomain;
use App\Services\Admin\MasterDataBulk\Domains\RestaurantTablesBulkDomain;
use App\Services\Admin\MasterDataBulk\Domains\VouchersBulkDomain;
use InvalidArgumentException;

final class MasterDataBulkRegistry
{
    /**
     * @var array<string,MasterDataBulkDomain>
     */
    private array $domains;

    public function __construct(
        BranchesBulkDomain $branches,
        RestaurantTablesBulkDomain $restaurantTables,
        MenuCategoriesBulkDomain $menuCategories,
        MenuItemsBulkDomain $menuItems,
        MenuPricesBulkDomain $menuPrices,
        VouchersBulkDomain $vouchers,
        LoyaltyTiersBulkDomain $loyaltyTiers,
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

    public function for(string $key): MasterDataBulkDomain
    {
        if (! isset($this->domains[$key])) {
            throw new InvalidArgumentException('Unsupported master-data bulk domain [' . $key . '].');
        }

        return $this->domains[$key];
    }
}
