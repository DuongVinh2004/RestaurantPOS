<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KitchenStationOutputMode;
use App\Enums\KitchenTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenOrderItemTicket extends Model
{
    protected $table = 'kitchen_order_item_tickets';
    protected $primaryKey = 'ticket_id';

    protected $fillable = [
        'station_id',
        'order_id',
        'reservation_id',
        'order_item_id',
        'item_id',
        'category_id',
        'route_id',
        'route_source',
        'output_mode',
        'printer_target',
        'ticket_status',
        'first_dispatched_at',
        'fired_at',
        'ready_at',
        'completed_at',
        'cancelled_at',
        'last_recalled_at',
        'dispatch_count',
        'recall_count',
        'ticket_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'ticket_id' => 'int',
        'station_id' => 'int',
        'order_id' => 'int',
        'reservation_id' => 'int',
        'order_item_id' => 'int',
        'item_id' => 'int',
        'category_id' => 'int',
        'route_id' => 'int',
        'output_mode' => KitchenStationOutputMode::class,
        'ticket_status' => KitchenTicketStatus::class,
        'dispatch_count' => 'int',
        'recall_count' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
        'first_dispatched_at' => 'datetime',
        'fired_at' => 'datetime',
        'ready_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_recalled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'station_id', 'station_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(KitchenStationCategoryRoute::class, 'route_id', 'route_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ReservationOrder::class, 'order_id', 'order_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(ReservationOrderItem::class, 'order_item_id', 'order_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }
}
