<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingPHPStanExtension\Analyser\Model;

enum SubscriberType: string
{
    case Subscriber = 'subscriber';
    case Processor = 'processor';
    case Projector = 'projector';
}
