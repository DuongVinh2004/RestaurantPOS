# State Machines

This document outlines the core state transitions for major entities in RestaurantPOS. These state machines govern the lifecycle of core business operations.

## Reservation Lifecycle

Reservations track a customer's intent to dine at a future time.

```mermaid
stateDiagram-v2
    [*] --> Pending: Created (e.g. requires deposit)
    [*] --> Confirmed: Created (no deposit required)
    Pending --> Confirmed: Deposit Paid
    Pending --> Cancelled: Deposit Timeout / User Cancelled
    Confirmed --> Cancelled: User/Staff Cancelled
    Confirmed --> CheckedIn: Customer Arrived
    CheckedIn --> Seated: Table Assigned & Service Session Started
    Seated --> [*]
    Cancelled --> [*]
```

## Table Hold Lifecycle

Table holds temporarily block a table from being booked by someone else while a customer or staff member is actively completing a booking flow.

```mermaid
stateDiagram-v2
    [*] --> Active: Hold Placed
    Active --> Converted: Booking Completed (Reservation Created)
    Active --> Expired: Time Limit Reached (e.g. 5 mins)
    Active --> Released: User Abandoned
    Converted --> [*]
    Expired --> [*]
    Released --> [*]
```

## Order Lifecycle

An order represents a physical bill/check for a table or walk-in.

```mermaid
stateDiagram-v2
    [*] --> Open: Order Created / Items Added
    Open --> Locked: Bill Printed / Staff Locked for Checkout
    Locked --> Open: Items Appended / Bill Unlocked
    Locked --> Paid: Full Payment Captured
    Paid --> Refunded: Full or Partial Refund Applied
    Paid --> [*]
    Refunded --> [*]
    Open --> Voided: Order Cancelled (No Payments)
    Voided --> [*]
```

## Kitchen Ticket Lifecycle

Kitchen tickets represent the instructions sent to a specific KDS (Kitchen Display System) station.

```mermaid
stateDiagram-v2
    [*] --> Received: Sent to Kitchen
    Received --> Preparing: Cook Starts Ticket
    Preparing --> Ready: Items Plated / Bumped
    Ready --> Delivered: Run to Table
    Received --> Voided: Staff Cancelled (e.g. mistake)
    Preparing --> Voided: Staff Cancelled (Waste logged)
    Delivered --> [*]
    Voided --> [*]
```

## Payment Lifecycle

Individual payment capture events.

```mermaid
stateDiagram-v2
    [*] --> Pending: Intent Created
    Pending --> Captured: Successful Charge
    Pending --> Failed: Declined or Error
    Captured --> RefundRequested: Refund Initiated
    RefundRequested --> Refunded: Refund Confirmed
    RefundRequested --> Captured: Refund Failed
    Failed --> [*]
    Refunded --> [*]
```

## Cashier Shift Lifecycle

Tracks a cashier's till/drawer responsibility.

```mermaid
stateDiagram-v2
    [*] --> Opened: Shift Started (Float Declared)
    Opened --> Active: Shift In Progress
    Active --> PendingReconciliation: Shift Ended (Cash Counted)
    PendingReconciliation --> Reconciled: Manager Approved (Overage/Shortage Logged)
    Reconciled --> [*]
```
