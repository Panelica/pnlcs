<?php

namespace App\Enums;

/**
 * What a delivery found when it tried to take a webhook event.
 *
 * Three states, and the whole point of naming them is that two of them used to
 * share an answer. A claim that came back "no" meant either "this event is
 * finished" or "another delivery has it in hand this minute", and those two
 * need opposite replies to the gateway: the first is a repeat that should stop
 * being sent, the second is a delivery that must come back — because the one
 * holding the event may be the one that crashed, and a gateway that has been
 * told 2xx never knocks again.
 */
enum GatewayEventClaim: string
{
    /** This delivery holds the event and does the work: a fresh claim, or a lease taken over. */
    case Taken = 'taken';

    /** Another delivery is inside the lease. Nothing to do here, and nothing settled either. */
    case Held = 'held';

    /** The work behind this event is finished and will not be done again. */
    case Finished = 'finished';

    /**
     * May the delivery holding this outcome do the work?
     */
    public function mayProceed(): bool
    {
        return $this === self::Taken;
    }
}
