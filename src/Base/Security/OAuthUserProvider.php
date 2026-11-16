<?php

namespace Base\Security;

use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;

/**
 * Base\Security\UserProvider declared OAuth-aware, for hwi/oauth-bundle's
 * firewalls (an `oauth` provider asks for its interface). The service
 * Base\Security\UserProvider is this class when hwi/oauth-bundle is installed,
 * UserProvider otherwise (config/services/form.php): the sites' security.yaml
 * name the service, not the class.
 */
class OAuthUserProvider extends UserProvider implements OAuthAwareUserProviderInterface
{
}
