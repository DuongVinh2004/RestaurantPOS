<?php

declare(strict_types=1);

return [
    'wildcard_environment' => '*',
    'global_branch_id' => 0,
    'features' => [
        'customer.bill_self_payment' => [
            'description' => 'Customer-facing bill self-payment preview and new payment-session creation.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Customer bill self-payment is disabled for this rollout. Use staff settlement.',
        ],
        'waiting_list.advanced_automation' => [
            'description' => 'Semi-automated waiting-list queue advancement after decline or invite expiry.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Advanced waiting-list automation is disabled for this rollout. Use canonical notify and seat flows.',
        ],
        'staff.kitchen_dispatch' => [
            'description' => 'Staff kitchen dispatch and ticket mutation flows.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Kitchen dispatch features are disabled for this rollout.',
        ],
        'inventory.uplift' => [
            'description' => 'Advanced inventory and purchasing foundation workflows.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Inventory uplift features are disabled for this rollout.',
        ],
        'staff.conversation_inbox' => [
            'description' => 'Staff conversation inbox read and workflow surfaces.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Conversation inbox is disabled for this rollout.',
        ],
        'staff.conversation_ai_assist' => [
            'description' => 'Optional AI-style conversation summary and follow-up hints for the staff inbox detail view.',
            'kill_switch' => true,
            'safe_default' => false,
            'defaults' => [
                '*' => false,
                'local' => true,
                'testing' => true,
            ],
            'disabled_message' => 'Conversation AI assist is disabled for this rollout. Use the canonical timeline instead.',
        ],
    ],
];
