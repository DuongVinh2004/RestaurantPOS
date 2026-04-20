[CmdletBinding()]
param(
    [ValidateSet(
        'all',
        'availability-hold-reservation',
        'deposit-self-pay',
        'dine-in-checkout',
        'refund-partial',
        'refund-cancel',
        'waiting-list-lifecycle',
        'benefits',
        'admin-master-data',
        'conversation-inbox'
    )]
    [string[]]$Scenario = @('all'),
    [string]$ManifestPath = 'storage/app/uat/scenario-pack.json',
    [string]$BaseUrl,
    [switch]$PassThru
)

$ErrorActionPreference = 'Stop'

function Get-RepoRoot {
    param([string]$ScriptRoot)

    return [System.IO.Path]::GetFullPath((Join-Path $ScriptRoot '..\..'))
}

function Resolve-ManifestPath {
    param(
        [string]$RepoRoot,
        [string]$PathValue
    )

    if ([string]::IsNullOrWhiteSpace($PathValue)) {
        return Join-Path $RepoRoot 'storage\app\uat\scenario-pack.json'
    }

    if ([System.IO.Path]::IsPathRooted($PathValue)) {
        return $PathValue
    }

    return [System.IO.Path]::GetFullPath((Join-Path $RepoRoot $PathValue))
}

function ConvertTo-ApiJson {
    param([object]$Value)

    return ($Value | ConvertTo-Json -Depth 30)
}

function New-UatIdempotencyKey {
    param([string]$Prefix)

    return '{0}-{1}-{2}' -f $Prefix, ([DateTime]::UtcNow.ToString('yyyyMMddHHmmssfff')), (Get-Random -Minimum 1000 -Maximum 9999)
}

function Merge-Headers {
    param(
        [hashtable]$BaseHeaders,
        [hashtable]$ExtraHeaders
    )

    $merged = @{}
    foreach ($entry in $BaseHeaders.GetEnumerator()) {
        $merged[$entry.Key] = $entry.Value
    }
    foreach ($entry in $ExtraHeaders.GetEnumerator()) {
        $merged[$entry.Key] = $entry.Value
    }

    return $merged
}

function Invoke-UatApi {
    param(
        [string]$Method,
        [string]$Path,
        [hashtable]$Headers,
        [object]$Body = $null
    )

    $uri = if ($Path.StartsWith('http')) { $Path } else { ($script:ResolvedBaseUrl.TrimEnd('/') + $Path) }
    $invokeParams = @{
        Method      = $Method
        Uri         = $uri
        Headers     = $Headers
        ErrorAction = 'Stop'
    }

    if ($null -ne $Body) {
        $invokeParams['ContentType'] = 'application/json'
        $invokeParams['Body'] = ConvertTo-ApiJson $Body
    }

    try {
        return Invoke-RestMethod @invokeParams
    }
    catch {
        $detail = $_.Exception.Message

        if ($_.Exception.Response -and $_.Exception.Response.GetResponseStream()) {
            $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
            $bodyText = $reader.ReadToEnd()
            if (-not [string]::IsNullOrWhiteSpace($bodyText)) {
                $detail = "$detail`n$bodyText"
            }
        }

        throw "HTTP $Method $uri failed.`n$detail"
    }
}

function Get-StaffHeaders {
    param([string]$ActorKey)

    $token = $script:Manifest.auth.$ActorKey.api_key
    if ([string]::IsNullOrWhiteSpace($token)) {
        throw "Manifest auth.$ActorKey.api_key is missing."
    }

    return @{
        'Accept' = 'application/json'
        'X-Staff-Key' = $token
    }
}

function Get-CustomerHeaders {
    param(
        [string]$ActorKey,
        [string]$SessionLabel = 'uat-scenario-pack'
    )

    $auth = $script:Manifest.auth.$ActorKey
    $login = Invoke-UatApi -Method 'POST' -Path '/api/v1/auth/customer/login' -Headers @{ Accept = 'application/json' } -Body @{
        identifier = $auth.username
        password = $auth.password
        session_label = $SessionLabel
    }

    $token = $login.data.access_token
    if ([string]::IsNullOrWhiteSpace($token)) {
        throw "Customer login for [$ActorKey] did not return access_token."
    }

    return @{
        'Accept' = 'application/json'
        'X-Customer-Token' = $token
    }
}

function Invoke-AvailabilityHoldReservationScenario {
    $scenario = $script:Manifest.scenarios.availability_hold_reservation
    $customerHeaders = Get-CustomerHeaders -ActorKey 'customer_primary' -SessionLabel 'uat-availability-hold'
    $query = '/api/v1/tables/available?branch_id={0}&from={1}&to={2}&guest_count={3}&session_id={4}&suggest=1' -f `
        $scenario.branch_id, `
        [uri]::EscapeDataString($scenario.from_utc), `
        [uri]::EscapeDataString($scenario.to_utc), `
        $scenario.guest_count, `
        [uri]::EscapeDataString($scenario.session_id)

    $availability = Invoke-UatApi -Method 'GET' -Path $query -Headers @{ Accept = 'application/json' }
    $hold = Invoke-UatApi -Method 'POST' -Path '/api/v1/table-holds' -Headers @{
        Accept = 'application/json'
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-hold-create'
    } -Body @{
        branch_id = [int]$scenario.branch_id
        session_id = $scenario.session_id
        start_time = $scenario.from_utc
        end_time = $scenario.to_utc
        table_ids = @($scenario.preferred_table_ids)
        hold_minutes = 5
    }

    $reservation = Invoke-UatApi -Method 'POST' -Path '/api/v1/reservations' -Headers (Merge-Headers $customerHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-reservation-create'
    }) -Body @{
        hold_id = $hold.data.hold_id
        session_id = $scenario.session_id
        start_time = $scenario.from_utc
        end_time = $scenario.to_utc
        guest_count = [int]$scenario.guest_count
        notes = 'UAT scenario pack availability -> hold -> reservation flow'
    }

    return [pscustomobject]@{
        scenario = 'availability-hold-reservation'
        available_table_count = @($availability.data).Count
        hold_id = $hold.data.hold_id
        hold_status = $hold.data.hold_status
        reservation_id = $reservation.data.reservation_id
        reservation_code = $reservation.data.reservation_code
        reservation_status = $reservation.data.status
    }
}

function Invoke-DepositSelfPayScenario {
    $scenario = $script:Manifest.scenarios.deposit_self_pay
    $reservation = $script:Manifest.reservations.deposit_pending
    $headers = Get-CustomerHeaders -ActorKey 'customer_primary' -SessionLabel 'uat-deposit-self-pay'

    $preview = Invoke-UatApi -Method 'GET' -Path ("/api/v1/reservations/{0}/deposit-preview" -f $scenario.reservation_id) -Headers $headers
    $ack = Invoke-UatApi -Method 'POST' -Path ("/api/v1/reservations/{0}/deposit/acknowledge" -f $scenario.reservation_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-deposit-ack'
    }) -Body @{
        row_version = [int]$reservation.row_version
    }
    $ackReservationRowVersion = if ($null -ne $ack.data.reservation -and $null -ne $ack.data.reservation.row_version) {
        [int]$ack.data.reservation.row_version
    } else {
        [int]$ack.data.row_version
    }
    $intent = Invoke-UatApi -Method 'POST' -Path ("/api/v1/reservations/{0}/deposit/intent" -f $scenario.reservation_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-deposit-intent'
    }) -Body @{
        row_version = $ackReservationRowVersion
    }
    $intentReservationRowVersion = if ($null -ne $intent.data.reservation -and $null -ne $intent.data.reservation.row_version) {
        [int]$intent.data.reservation.row_version
    } else {
        [int]$intent.data.row_version
    }
    $session = Invoke-UatApi -Method 'POST' -Path ("/api/v1/reservations/{0}/deposit/payment-sessions" -f $scenario.reservation_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-deposit-session-create'
    }) -Body @{
        row_version = $intentReservationRowVersion
        amount = [decimal]$scenario.payment_amount
        provider_code = $scenario.provider_code
        currency = $script:Manifest.branch.currency
    }
    $confirm = Invoke-UatApi -Method 'POST' -Path ("/api/v1/reservations/{0}/deposit/payment-sessions/{1}/confirm" -f $scenario.reservation_id, $session.data.payment_session.deposit_payment_session_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-deposit-session-confirm'
    }) -Body @{
        row_version = [int]$session.data.payment_session.row_version
        simulation_outcome = 'succeeded'
    }

    return [pscustomobject]@{
        scenario = 'deposit-self-pay'
        reservation_id = [int]$scenario.reservation_id
        preview_status = $preview.data.deposit.status
        intent_status = $intent.data.deposit.self_service.intent_status
        payment_session_id = [int]$session.data.payment_session.deposit_payment_session_id
        payment_session_status = $confirm.data.payment_session.session_status
        settlement_status = $confirm.data.payment_session.settlement_status
        deposit_paid_amount = $confirm.data.deposit.paid_amount
    }
}

function Invoke-DineInCheckoutScenario {
    $scenario = $script:Manifest.scenarios.dine_in_checkout
    $reservation = $script:Manifest.reservations.dine_in_checkin
    $staffHeaders = Get-StaffHeaders -ActorKey 'staff'
    $customerHeaders = Get-CustomerHeaders -ActorKey 'customer_primary' -SessionLabel 'uat-dine-in-checkout'
    $branchId = [int]$script:Manifest.branch.branch_id
    $checkedInAt = [DateTime]::UtcNow.ToString('o')

    $checkIn = Invoke-UatApi -Method 'POST' -Path ("/api/v1/staff/reservations/{0}/check-in" -f $scenario.reservation_id) -Headers (Merge-Headers $staffHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-check-in'
    }) -Body @{
        table_ids = @([int]$scenario.table_id)
        checked_in_at = $checkedInAt
        row_version = [int]$reservation.row_version
    }

    $createOrder = Invoke-UatApi -Method 'POST' -Path ("/api/v1/staff/tables/{0}/orders" -f $scenario.table_id) -Headers (Merge-Headers $staffHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-order-create'
    }) -Body @{
        reservation_id = [int]$scenario.reservation_id
        row_version = [int]$checkIn.data.row_version
        items = @(
            @{
                menu_item_id = [int]$scenario.menu_item_ids[0]
                qty = 1
            }
        )
    }

    $addItems = Invoke-UatApi -Method 'POST' -Path ("/api/v1/staff/orders/{0}/items" -f $createOrder.data.order_id) -Headers (Merge-Headers $staffHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-order-items'
    }) -Body @{
        row_version = [int]$createOrder.data.row_version
        items = @(
            @{
                menu_item_id = [int]$scenario.menu_item_ids[1]
                qty = 1
            }
        )
    }

    $activeOrder = Invoke-UatApi -Method 'GET' -Path ("/api/v1/reservations/{0}/active-order" -f $scenario.reservation_id) -Headers $customerHeaders
    $billSnapshot = Invoke-UatApi -Method 'POST' -Path ("/api/v1/staff/orders/{0}/bill-snapshot" -f $createOrder.data.order_id) -Headers (Merge-Headers $staffHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-bill-snapshot'
    }) -Body @{
        row_version = [int]$addItems.data.row_version
        notes = 'UAT scenario pack staff bill snapshot'
    }
    $billPreview = Invoke-UatApi -Method 'GET' -Path ("/api/v1/reservations/{0}/bill-preview" -f $scenario.reservation_id) -Headers $customerHeaders
    $orderShow = Invoke-UatApi -Method 'GET' -Path ("/api/v1/staff/orders/{0}" -f $createOrder.data.order_id) -Headers $staffHeaders
    $cashierShift = $null
    try {
        $cashierShift = Invoke-UatApi -Method 'GET' -Path ("/api/v1/staff/cashier/shifts/current?branch_id={0}" -f $branchId) -Headers $staffHeaders
    } catch {
        $detail = $_.Exception.Message
        if ($detail -notmatch '"error_code":"not_found"' -and $detail -notmatch '\(404\)') {
            throw
        }
    }

    if ($null -eq $cashierShift) {
        $cashierShift = Invoke-UatApi -Method 'POST' -Path '/api/v1/staff/cashier/shifts/open' -Headers (Merge-Headers $staffHeaders @{
            'Idempotency-Key' = New-UatIdempotencyKey 'uat-cashier-open'
        }) -Body @{
            branch_id = $branchId
            opening_float_amount = '100000.00'
            currency = $script:Manifest.branch.currency
            terminal_code = 'UAT-POS-01'
            notes = 'UAT dine-in checkout scenario shift'
        }
    }

    $cashierShiftId = if ($null -ne $cashierShift.data.cashier_shift_id) {
        [int]$cashierShift.data.cashier_shift_id
    } elseif ($null -ne $cashierShift.data.shift -and $null -ne $cashierShift.data.shift.cashier_shift_id) {
        [int]$cashierShift.data.shift.cashier_shift_id
    } else {
        0
    }

    $finalizeRowVersion = if ($null -ne $orderShow.data.order -and $null -ne $orderShow.data.order.row_version) {
        [int]$orderShow.data.order.row_version
    } else {
        [int]$orderShow.data.row_version
    }
    $finalize = Invoke-UatApi -Method 'POST' -Path ("/api/v1/staff/orders/{0}/settlement/finalize" -f $createOrder.data.order_id) -Headers (Merge-Headers $staffHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-settlement-finalize'
    }) -Body @{
        payment_method = 'Cash'
        payment_provider = 'Cash'
        paid_amount = [decimal]$billPreview.data.bill_preview.outstanding_amount
        currency = $script:Manifest.branch.currency
        transaction_code = New-UatIdempotencyKey 'uat-final-payment'
        row_version = $finalizeRowVersion
    }

    return [pscustomobject]@{
        scenario = 'dine-in-checkout'
        reservation_id = [int]$scenario.reservation_id
        check_in_status = $checkIn.data.status
        order_id = [int]$createOrder.data.order_id
        cashier_shift_id = $cashierShiftId
        active_order_total_due = $activeOrder.data.active_order.totals.total_due
        bill_snapshot_action = $billSnapshot.meta.action
        bill_preview_due = $billPreview.data.bill_preview.total_due_amount
        final_payment_status = $finalize.data.payment_status
        final_reservation_status = if (-not [string]::IsNullOrWhiteSpace([string]$finalize.data.reservation_status)) {
            $finalize.data.reservation_status
        } else {
            $finalize.data.status
        }
    }
}

function Invoke-RefundPartialScenario {
    $scenario = $script:Manifest.scenarios.refund_partial
    $reservation = $script:Manifest.reservations.refund_partial_ready
    $headers = Get-StaffHeaders -ActorKey 'staff'

    $previewPath = '/api/v1/staff/reservations/{0}/refund-preview?refund_scope={1}&refund_amount={2}&cancel_after_payment=0' -f `
        $scenario.reservation_id, `
        $scenario.refund_scope, `
        $scenario.refund_amount
    $preview = Invoke-UatApi -Method 'GET' -Path $previewPath -Headers $headers
    $refund = Invoke-UatApi -Method 'POST' -Path ("/api/v1/staff/reservations/{0}/refund" -f $scenario.reservation_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-refund-partial'
    }) -Body @{
        payment_method = 'Cash'
        payment_provider = 'Cash'
        refund_scope = $scenario.refund_scope
        refund_amount = [decimal]$scenario.refund_amount
        currency = $script:Manifest.branch.currency
        transaction_code = New-UatIdempotencyKey 'uat-refund-payment'
        row_version = [int]$reservation.row_version
    }

    return [pscustomobject]@{
        scenario = 'refund-partial'
        reservation_id = [int]$scenario.reservation_id
        preview_scope = $preview.data.refund.refund_scope
        refund_scope = $refund.data.refund.refund_scope
        deposit_status = $refund.data.reservation.deposit_status
        deposit_paid_amount = $refund.data.reservation.deposit_paid_amount
    }
}

function Invoke-RefundCancelScenario {
    $scenario = $script:Manifest.scenarios.refund_cancel
    $reservation = $script:Manifest.reservations.refund_cancel_ready
    $headers = Get-StaffHeaders -ActorKey 'staff'

    $preview = Invoke-UatApi -Method 'GET' -Path ("/api/v1/staff/reservations/{0}/refund-preview" -f $scenario.reservation_id) -Headers $headers
    $refundCancel = Invoke-UatApi -Method 'POST' -Path ("/api/v1/staff/reservations/{0}/refund-cancel" -f $scenario.reservation_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-refund-cancel'
    }) -Body @{
        payment_method = 'Cash'
        payment_provider = 'Cash'
        refund_scope = $scenario.refund_scope
        currency = $script:Manifest.branch.currency
        transaction_code = New-UatIdempotencyKey 'uat-refund-cancel-payment'
        reason = 'customer_request'
        cancel_reason = 'customer_request'
        row_version = [int]$reservation.row_version
    }

    return [pscustomobject]@{
        scenario = 'refund-cancel'
        reservation_id = [int]$scenario.reservation_id
        preview_scope = $preview.data.refund.refund_scope
        refund_scope = $refundCancel.data.refund.refund_scope
        reservation_status = $refundCancel.data.reservation.status
        deposit_status = $refundCancel.data.reservation.deposit_status
    }
}

function Invoke-WaitingListLifecycleScenario {
    $scenario = $script:Manifest.scenarios.waiting_list_lifecycle
    $customerHeaders = Get-CustomerHeaders -ActorKey 'customer_primary' -SessionLabel 'uat-waiting-list'
    $staffHeaders = Get-StaffHeaders -ActorKey 'staff'

    $create = Invoke-UatApi -Method 'POST' -Path '/api/v1/waiting-list' -Headers (Merge-Headers $customerHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-waiting-create'
    }) -Body @{
        branch_id = [int]$scenario.branch_id
        guest_count = 2
        notes = 'UAT scripted waiting-list scenario'
    }
    $waitingId = [int]$create.data.waiting_id

    $notify = Invoke-UatApi -Method 'POST' -Path ("/api/v1/staff/waiting-list/{0}/notify" -f $waitingId) -Headers (Merge-Headers $staffHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-waiting-notify'
    }) -Body @{
        table_id = [int]$scenario.table_id
        hold_minutes = 10
        row_version = [int]$create.data.row_version
    }

    $accept = Invoke-UatApi -Method 'POST' -Path ("/api/v1/waiting-list/{0}/accept" -f $waitingId) -Headers (Merge-Headers $customerHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-waiting-accept'
    }) -Body @{
        row_version = [int]$notify.data.row_version
    }

    $confirmArrival = Invoke-UatApi -Method 'POST' -Path ("/api/v1/waiting-list/{0}/confirm-arrival" -f $waitingId) -Headers (Merge-Headers $customerHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-waiting-confirm-arrival'
    }) -Body @{
        row_version = [int]$accept.data.row_version
    }

    $seat = Invoke-UatApi -Method 'POST' -Path ("/api/v1/staff/waiting-list/{0}/seat" -f $waitingId) -Headers (Merge-Headers $staffHeaders @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-waiting-seat'
    }) -Body @{
        user_id = [int]$scenario.customer_user_id
        service_minutes = 90
        row_version = [int]$confirmArrival.data.row_version
    }

    return [pscustomobject]@{
        scenario = 'waiting-list-lifecycle'
        waiting_id = $waitingId
        notified_status = $notify.data.status
        accepted_response_status = $accept.data.window.customer_response_status
        confirmed_arrival_at = $confirmArrival.data.window.confirmed_arrival_at
        seated_status = $seat.data.waiting_list.status
        reservation_id = [int]$seat.data.reservation.reservation_id
    }
}

function Invoke-BenefitsScenario {
    $scenario = $script:Manifest.scenarios.benefits
    $reservation = $script:Manifest.reservations.benefits_pending
    $headers = Get-CustomerHeaders -ActorKey 'customer_primary' -SessionLabel 'uat-benefits'

    $preview = Invoke-UatApi -Method 'GET' -Path ("/api/v1/reservations/{0}/benefits-preview" -f $scenario.reservation_id) -Headers $headers
    $apply = Invoke-UatApi -Method 'POST' -Path ("/api/v1/reservations/{0}/voucher/apply" -f $scenario.reservation_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-voucher-apply'
    }) -Body @{
        user_voucher_id = [int]$scenario.user_voucher_id
        row_version = [int]$reservation.row_version
    }
    $remove = Invoke-UatApi -Method 'POST' -Path ("/api/v1/reservations/{0}/voucher/remove" -f $scenario.reservation_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-voucher-remove'
    }) -Body @{
        row_version = [int]$apply.data.reservation.row_version
    }
    $redeem = Invoke-UatApi -Method 'POST' -Path ("/api/v1/reservations/{0}/loyalty/redeem" -f $scenario.reservation_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-loyalty-redeem'
    }) -Body @{
        points = [int]$scenario.loyalty_points
        reason = 'UAT scripted loyalty redeem'
        row_version = [int]$remove.data.reservation.row_version
    }
    $release = Invoke-UatApi -Method 'POST' -Path ("/api/v1/reservations/{0}/loyalty/redeem/release" -f $scenario.reservation_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-loyalty-release'
    }) -Body @{
        reason = 'UAT scripted loyalty release'
        row_version = [int]$redeem.data.reservation.row_version
    }

    return [pscustomobject]@{
        scenario = 'benefits'
        reservation_id = [int]$scenario.reservation_id
        preview_voucher_count = @($preview.data.available_vouchers).Count
        applied_voucher_id = [int]$apply.data.voucher.user_voucher_id
        removed_voucher_id = [int]$remove.data.removed_voucher.user_voucher_id
        redeemed_points = [int]$redeem.data.reservation.loyalty.redeemed_points
        released_points = [int]$release.data.reservation.loyalty.redeemed_points
    }
}

function Invoke-AdminMasterDataScenario {
    $scenario = $script:Manifest.scenarios.admin_master_data
    $headers = Get-StaffHeaders -ActorKey 'admin'
    $suffix = [DateTime]::UtcNow.ToString('yyyyMMddHHmmss')

    $templates = Invoke-UatApi -Method 'GET' -Path '/api/v1/admin/restaurant/table-templates' -Headers $headers
    $table = Invoke-UatApi -Method 'POST' -Path '/api/v1/admin/restaurant/tables' -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-admin-table'
    }) -Body @{
        branch_id = [int]$scenario.branch_id
        table_code = "UAT-SCRIPT-$suffix"
        template_id = [int]$scenario.template_id
        zone = 'UAT Script Zone'
        pos_x = 99
        pos_y = 99
        status = 'Available'
        description = 'Scenario pack scripted table'
        price = '0.00'
    }
    $category = Invoke-UatApi -Method 'POST' -Path '/api/v1/admin/menu/categories' -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-admin-category'
    }) -Body @{
        name = "$($scenario.menu_category_name) $suffix"
        description = 'Scenario pack scripted menu category'
        sort_order = 250
        is_deleted = $false
    }
    $item = Invoke-UatApi -Method 'POST' -Path '/api/v1/admin/menu/items' -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-admin-item'
    }) -Body @{
        category_id = [int]$category.data.category_id
        code = "$($scenario.menu_item_code)-$suffix"
        name = "UAT Script Item $suffix"
        description = 'Scenario pack scripted menu item'
        is_available = $true
        is_preorder_enabled = $false
        preorder_quota_per_day = $null
        preorder_cutoff_minutes = 0
    }
    $price = Invoke-UatApi -Method 'POST' -Path ("/api/v1/admin/menu/items/{0}/prices" -f $item.data.item_id) -Headers (Merge-Headers $headers @{
        'Idempotency-Key' = New-UatIdempotencyKey 'uat-admin-price'
    }) -Body @{
        price = '123000.00'
        currency = $script:Manifest.branch.currency
        effective_from = [DateTime]::UtcNow.ToString('o')
    }

    return [pscustomobject]@{
        scenario = 'admin-master-data'
        template_count = @($templates.data).Count
        created_table_id = [int]$table.data.table_id
        created_category_id = [int]$category.data.category_id
        created_item_id = [int]$item.data.item_id
        created_price_id = [int]$price.data.price_id
    }
}

function Invoke-ConversationInboxScenario {
    $scenario = $script:Manifest.scenarios.conversation_inbox
    $headers = Get-StaffHeaders -ActorKey 'staff'

    $list = Invoke-UatApi -Method 'GET' -Path ("/api/v1/staff/conversations?branch_id={0}&status=Open&per_page=20" -f $scenario.branch_id) -Headers $headers
    $detail = Invoke-UatApi -Method 'GET' -Path ("/api/v1/staff/conversations/{0}?message_limit=10&event_limit=10" -f $scenario.conversation_id) -Headers $headers

    return [pscustomobject]@{
        scenario = 'conversation-inbox'
        listed_count = [int]$list.meta.total
        conversation_id = $detail.data.conversation.conversation_id
        linked_reservation_id = [int]$detail.data.conversation.linked_reservation.reservation_id
        message_count = [int]$detail.meta.returned_counts.messages
        assignment_history_count = @($detail.data.assignment_history).Count
    }
}

$repoRoot = Get-RepoRoot -ScriptRoot $PSScriptRoot
$resolvedManifestPath = Resolve-ManifestPath -RepoRoot $repoRoot -PathValue $ManifestPath

if (-not (Test-Path -LiteralPath $resolvedManifestPath)) {
    throw "Manifest file [$resolvedManifestPath] was not found. Run scripts/uat/Bootstrap-UatPack.ps1 first."
}

$script:Manifest = Get-Content -LiteralPath $resolvedManifestPath -Raw | ConvertFrom-Json
$script:ResolvedBaseUrl = if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
    $script:Manifest.pack.base_url
} else {
    $BaseUrl
}

if ([string]::IsNullOrWhiteSpace($script:ResolvedBaseUrl)) {
    throw 'Base URL is empty. Pass -BaseUrl or regenerate the manifest with a valid base URL.'
}

$requestedScenarios = if ($Scenario -contains 'all') {
    @(
        'availability-hold-reservation',
        'deposit-self-pay',
        'dine-in-checkout',
        'refund-partial',
        'refund-cancel',
        'waiting-list-lifecycle',
        'benefits',
        'admin-master-data',
        'conversation-inbox'
    )
} else {
    $Scenario
}

$results = foreach ($name in $requestedScenarios) {
    switch ($name) {
        'availability-hold-reservation' { Invoke-AvailabilityHoldReservationScenario }
        'deposit-self-pay' { Invoke-DepositSelfPayScenario }
        'dine-in-checkout' { Invoke-DineInCheckoutScenario }
        'refund-partial' { Invoke-RefundPartialScenario }
        'refund-cancel' { Invoke-RefundCancelScenario }
        'waiting-list-lifecycle' { Invoke-WaitingListLifecycleScenario }
        'benefits' { Invoke-BenefitsScenario }
        'admin-master-data' { Invoke-AdminMasterDataScenario }
        'conversation-inbox' { Invoke-ConversationInboxScenario }
        default { throw "Unsupported scenario [$name]." }
    }
}

if ($PassThru) {
    $results
    return
}

Write-Host "UAT scenario run completed against $script:ResolvedBaseUrl" -ForegroundColor Green
$results | Format-Table -AutoSize
