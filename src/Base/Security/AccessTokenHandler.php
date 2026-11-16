<?php
    
namespace Base\Security;

use Base\Repository\User\Tokens;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccessTokenHandler implements AccessTokenHandlerInterface
{
    /**
     * @var Tokens
     */
    protected $tokenRepository;
    public function __construct(Tokens $tokenRepository) {
    
        $this->tokenRepository = $tokenRepository;
    }

    public function getUserBadgeFrom(string $token): UserBadge
    {
        // e.g. query the "access token" database to search for this token
        $token = $this->tokenRepository->oneSigningInByValue($token);
        if (null === $token || !$token->isValid() || null === $token->getUser()) {
            throw new BadCredentialsException('Invalid credentials.');
        }

        // and return a UserBadge object containing the user identifier from the found token
        $user = $token->getUser();
        return new UserBadge((string) $user->getId());
    }
}