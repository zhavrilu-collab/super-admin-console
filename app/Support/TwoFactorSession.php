<?php

namespace App\Support;

final class TwoFactorSession
{
    public const LOGIN_USER_ID = 'login.id';

    public const LOGIN_REMEMBER = 'login.remember';

    public const SETUP_SECRET = 'two_factor.setup_secret';

    /** @var list<string> */
    public const FLASH_RECOVERY_CODES = 'two_factor.recovery_codes';
}
