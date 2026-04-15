<?php

declare(strict_types=1);

namespace App\Modules\AdminMasterDataBulk\Application\Services;

use App\Modules\AdminMasterDataBulk\Domain\Contracts\MasterDataBulkDomain;
use App\Modules\AdminMasterDataBulk\Domain\Domains\BranchesBulkDomain;
use App\Modules\AdminMasterDataBulk\Domain\Domains\LoyaltyTiersBulkDomain;
use App\Modules\AdminMasterDataBulk\Domain\Domains\MenuCategoriesBulkDomain;
use App\Modules\AdminMasterDataBulk\Domain\Domains\MenuItemsBulkDomain;
use App\Modules\AdminMasterDataBulk\Domain\Domains\MenuPricesBulkDomain;
use App\Modules\AdminMasterDataBulk\Domain\Domains\RestaurantTablesBulkDomain;
use App\Modules\AdminMasterDataBulk\Domain\Domains\VouchersBulkDomain;
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
