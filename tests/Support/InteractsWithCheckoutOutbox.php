<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\CheckoutPayments\Application\Services\StaffCheckoutService;
use Illuminate\Support\Facades\DB;

trait InteractsWithCheckoutOutbox
{
    protected function makeCheckoutServiceWithOutbox(): StaffCheckoutService
    {
        config()->set('notifications.outbox.enabled', true);

        return $this->makeCheckoutService(new NotificationOutboxService());
    }

    protected function outboxTemplateCount(int $reservationId, string $templateKey): int
    {
        return (int) DB::table('notification_outbox')
            ->where('related_reservation_id', $reservationId)
            ->where('template_key', $templateKey)
            ->count();
    }

    /**
     * @param array<string,int> $expectedCounts
     */
    protected function assertOutboxTemplateCounts(int $reservationId, array $expectedCounts): void
    {
        foreach ($expectedCounts as $templateKey => $expectedCount) {
            \PHPUnit\Framework\Assert::assertSame(
                $expectedCount,
                $this->outboxTemplateCount($reservationId, $templateKey),
                sprintf('Unexpected outbox count for [%s] on reservation [%d].', $templateKey, $reservationId)
            );
        }
    }
}
