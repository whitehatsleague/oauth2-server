<?php

/**
 * OAuth 2.0 Refresh token grant
 *
 * @package     league/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace Whitehatsleague\OAuth2\Server\Grant;

use Whitehatsleague\OAuth2\Server\Entity\AccessTokenEntity;
use Whitehatsleague\OAuth2\Server\Entity\ClientEntity;
use Whitehatsleague\OAuth2\Server\Entity\RefreshTokenEntity;
use Whitehatsleague\OAuth2\Server\Event;
use Whitehatsleague\OAuth2\Server\Exception;
use Whitehatsleague\OAuth2\Server\Util\SecureKey;

/**
 * Refresh token grant
 */
class RefreshTokenGrant extends AbstractGrant
{

    /**
     * {@inheritdoc}
     */
    protected $identifier = 'refresh_token';

    /**
     * Refresh token TTL (default = 604800 | 1 week)
     *
     * @var integer
     */
    protected $refreshTokenTTL = 604800;

    /**
     * Rotate token (default = true)
     *
     * @var integer
     */
    protected $refreshTokenRotate = true;

    /**
     * Whether to require the client secret when
     * completing the flow.
     *
     * @var boolean
     */
    protected $requireClientSecret = true;

    /**
     * Set the TTL of the refresh token
     *
     * @param int $refreshTokenTTL
     *
     * @return void
     */
    public function setRefreshTokenTTL($refreshTokenTTL)
    {
        $this->refreshTokenTTL = $refreshTokenTTL;
    }

    /**
     * Get the TTL of the refresh token
     *
     * @return int
     */
    public function getRefreshTokenTTL()
    {
        return $this->refreshTokenTTL;
    }

    /**
     * Set the rotation boolean of the refresh token
     * @param bool $refreshTokenRotate
     */
    public function setRefreshTokenRotation($refreshTokenRotate = true)
    {
        $this->refreshTokenRotate = $refreshTokenRotate;
    }

    /**
     * Get rotation boolean of the refresh token
     *
     * @return bool
     */
    public function shouldRotateRefreshTokens()
    {
        return $this->refreshTokenRotate;
    }

    /**
     *
     * @param bool $required True to require client secret during access
     *                       token request. False if not. Default = true
     */
    public function setRequireClientSecret($required)
    {
        $this->requireClientSecret = $required;
    }

    /**
     * True if client secret is required during
     * access token request. False if it isn't.
     *
     * @return bool
     */
    public function shouldRequireClientSecret()
    {
        return $this->requireClientSecret;
    }

    /**
     * {@inheritdoc}
     */
    public function completeFlow()
    {
        $clientId = $this->server->getRequest()->request->get('clientId', $this->server->getRequest()->getUser());
        if (is_null($clientId)) {
            $InvalidRequestException = new Exception\InvalidRequestException('clientId');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        $clientSecret = $this->server->getRequest()->request->get('clientSecret', $this->server->getRequest()->getPassword());
        if ($this->shouldRequireClientSecret() && is_null($clientSecret)) {
            $InvalidRequestException = new Exception\InvalidRequestException('clientSecret');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        // Validate client ID and client secret
        $client = $this->server->getClientStorage()->get(
                $clientId, $clientSecret, null, $this->getIdentifier()
        );

        if (($client instanceof ClientEntity) === false) {
            $this->server->getEventEmitter()->emit(new Event\ClientAuthenticationFailedEvent($this->server->getRequest()));
            $InvalidClientException = new Exception\InvalidClientException();
            abort($InvalidClientException->httpStatusCode, $InvalidClientException->errorMessage);
        }

        $oldRefreshTokenParam = $this->server->getRequest()->request->get('refreshToken', null);
        if ($oldRefreshTokenParam === null) {
            $InvalidRequestException = new Exception\InvalidRequestException('refreshToken');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        // Validate refresh token
        $oldRefreshToken = $this->server->getRefreshTokenStorage()->get($oldRefreshTokenParam);

        if (($oldRefreshToken instanceof RefreshTokenEntity) === false) {
            $InvalidRefreshException = new Exception\InvalidRefreshException();
            abort($InvalidRefreshException->httpStatusCode, $InvalidRefreshException->errorMessage);
        }

        // Ensure the old refresh token hasn't expired
        if ($oldRefreshToken->isExpired() === true) {
            $InvalidRefreshException = new Exception\InvalidRefreshException();
            abort($InvalidRefreshException->httpStatusCode, $InvalidRefreshException->errorMessage);
        }

        $oldAccessToken = $oldRefreshToken->getAccessToken();

        // Get the scopes for the original session
        $session = $oldAccessToken->getSession();
        $scopes = $this->formatScopes($session->getScopes());

        // Get and validate any requested scopes
        $requestedScopesString = $this->server->getRequest()->request->get('scope', '');
        $requestedScopes = $this->validateScopes($requestedScopesString, $client);

        // If no new scopes are requested then give the access token the original session scopes
        if (count($requestedScopes) === 0) {
            $newScopes = $scopes;
        } else {
            // The OAuth spec says that a refreshed access token can have the original scopes or fewer so ensure
            //  the request doesn't include any new scopes
            foreach ($requestedScopes as $requestedScope) {
                if (!isset($scopes[$requestedScope->getId()])) {
                    $InvalidScopeException = new Exception\InvalidScopeException($requestedScope->getId());
                    abort($InvalidScopeException->httpStatusCode, $InvalidScopeException->errorMessage);
                }
            }

            $newScopes = $requestedScopes;
        }

        // Generate a new access token and assign it the correct sessions
        $newAccessToken = new AccessTokenEntity($this->server);
        $newAccessToken->setId(SecureKey::generate());
        $newAccessToken->setExpireTime($this->getAccessTokenTTL() + time());
        $newAccessToken->setSession($session);

        foreach ($newScopes as $newScope) {
            $newAccessToken->associateScope($newScope);
        }

        // Expire the old token and save the new one
        $oldAccessToken->expire();
        $newAccessToken->save();

        $this->server->getTokenType()->setSession($session);
        $this->server->getTokenType()->setParam('accessToken', $newAccessToken->getId());
        $this->server->getTokenType()->setParam('expiresIn', $this->getAccessTokenTTL());

        if ($this->shouldRotateRefreshTokens()) {
            // Expire the old refresh token
            $oldRefreshToken->expire();

            // Generate a new refresh token
            $newRefreshToken = new RefreshTokenEntity($this->server);
            $newRefreshToken->setId(SecureKey::generate());
            $newRefreshToken->setExpireTime($this->getRefreshTokenTTL() + time());
            $newRefreshToken->setAccessToken($newAccessToken);
            $newRefreshToken->save();

            $this->server->getTokenType()->setParam('refreshToken', $newRefreshToken->getId());
        } else {
            $this->server->getTokenType()->setParam('refreshToken', $oldRefreshToken->getId());
        }

        return $this->server->getTokenType()->generateResponse();
    }

}
