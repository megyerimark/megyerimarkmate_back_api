<?php

namespace App\Support;

use RuntimeException;

/** Az előleg már ki van fizetve (vagy a Stripe épp feldolgozza) – új fizetési oldal nem nyitható. */
class DepositPaymentPending extends RuntimeException
{
}
