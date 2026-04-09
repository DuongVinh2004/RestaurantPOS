<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationChannel: string
{
    case WebChat = 'WebChat';

    // Tuỳ hệ thống có thể phát sinh các kênh khác, giữ sẵn để dùng
    case Facebook = 'Facebook';
    case Zalo = 'Zalo';
    case Whatsapp = 'Whatsapp';
    case Instagram = 'Instagram';
    case Line = 'Line';
    case Other = 'Other';
}
