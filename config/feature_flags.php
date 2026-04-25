<?php

declare(strict_types=1);

return [
    'wildcard_environment' => '*',
    'global_branch_id' => 0,
    'features' => [
        'customer.bill_self_payment' => [
            'description' => 'Customer-facing bill self-payment session creation. Contract-visible, but outside the day-1 launch promise.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Customer bill self-payment is disabled for day 1. Keep bill preview and active-order reads only, and use staff settlement.',
        ],
        'waiting_list.advanced_automation' => [
            'description' => 'Semi-automated waiting-list queue advancement after decline or invite expiry. Day 1 keeps waiting-list work manual.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Advanced waiting-list automation is disabled for day 1. Use canonical manual notify and seat flows.',
        ],
        'staff.kitchen_dispatch' => [
            'description' => 'Staff kitchen dispatch and ticket mutation flows. Read-only kitchen visibility may remain, but KDS mutation rollout is held back on day 1.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Kitchen dispatch and ticket mutations are disabled for day 1.',
        ],
        'inventory.uplift' => [
            'description' => 'Advanced inventory and purchasing workflows outside the day-1 launch promise.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Inventory uplift features are disabled for day 1.',
        ],
        'staff.conversation_inbox' => [
            'description' => 'Staff conversation inbox read and workflow surfaces. Contract-visible, but held back from day-1 launch.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Conversation inbox is disabled for day 1.',
        ],
        'staff.conversation_ai_assist' => [
            'description' => 'Optional AI-style conversation summary and follow-up hints for the staff inbox detail view. Never part of the day-1 default launch posture.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Conversation AI assist is disabled for day 1. Use the canonical timeline instead.',
        ],
    ],
];
