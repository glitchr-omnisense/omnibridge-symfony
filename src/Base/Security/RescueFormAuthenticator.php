<?php

namespace Base\Security;

class RescueFormAuthenticator extends LoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'security_rescue';
    public const PENDING_ROUTE = 'security_pending';
}
