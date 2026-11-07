<?php

namespace App\Contracts;

/**
 * A registrar module that has no transfer-lock control at the registry.
 *
 * The base interface still requires getLockStatus()/toggleLock() so nothing
 * breaks, but for a module that cannot actually change the lock those two are
 * a lie: they answer "locked" and report success whatever happens. The panel
 * reads this marker and hides the Lock/Unlock control instead of showing a
 * button whose success means nothing.
 *
 * A registry without a clientTransferProhibited equivalent (a ccTLD like .pl)
 * and the manual registrar (no registry at all) implement it.
 */
interface CannotManageTransferLock {}
