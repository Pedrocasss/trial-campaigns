<?php

namespace App\Enums;

enum CampaignSendStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
