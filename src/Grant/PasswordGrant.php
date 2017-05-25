<?php

/**
 * OAuth 2.0 Password grant
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
use Whitehatsleague\OAuth2\Server\Entity\SessionEntity;
use Whitehatsleague\OAuth2\Server\Event;
use Whitehatsleague\OAuth2\Server\Exception;
use Whitehatsleague\OAuth2\Server\Util\SecureKey;

/**
 * Password grant class
 */
class PasswordGrant extends AbstractGrant
{

    /**
     * Grant identifier
     *
     * @var string
     */
    protected $identifier = 'password';

    /**
     * Response type
     *
     * @var string
     */
    protected $responseType;

    /**
     * Callback to authenticate a user's name and password
     *
     * @var callable
     */
    protected $callback;

    /**
     * Access token expires in override
     *
     * @var int
     */
    protected $accessTokenTTL;

    /**
     * Set the callback to verify a user's username and password
     *
     * @param callable $callback The callback function
     *
     * @return void
     */
    public function setVerifyCredentialsCallback(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Return the callback function
     *
     * @return callable
     *
     * @throws
     */
    protected function getVerifyCredentialsCallback()
    {
        if (is_null($this->callback) || !is_callable($this->callback)) {
            $ServerErrorException = new Exception\ServerErrorException('Null or non-callable callback set on Password grant');
            abort($ServerErrorException->httpStatusCode, $ServerErrorException->errorMessage);
        }

        return $this->callback;
    }

    /**
     * Complete the password grant
     *
     * @return array
     *
     * @throws
     */
    public function completeFlow()
    {
        // Get the required params
        $clientId = $this->server->getRequest()->request->get('clientId', $this->server->getRequest()->getUser());
        if (is_null($clientId)) {
            $InvalidRequestException = new Exception\InvalidRequestException('clientId');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        $clientSecret = $this->server->getRequest()->request->get('clientSecret', $this->server->getRequest()->getPassword());
        if (is_null($clientSecret)) {
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

        $username = $this->server->getRequest()->request->get('username', null);
        if (is_null($username)) {
            $InvalidRequestException = new Exception\InvalidRequestException('username');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        $password = $this->server->getRequest()->request->get('password', null);
        if (is_null($password)) {
            $InvalidRequestException = new Exception\InvalidRequestException('password');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        // Check if user's username and password are correct
        $userId = call_user_func($this->getVerifyCredentialsCallback(), $username, $password, $this->server->getRequest()->request->all());
        if ($userId === false) {
            $this->server->getEventEmitter()->emit(new Event\UserAuthenticationFailedEvent($this->server->getRequest()));
            $InvalidCredentialsException = new Exception\InvalidCredentialsException();
            abort($InvalidCredentialsException->httpStatusCode, $InvalidCredentialsException->errorMessage);
        }

        // Validate any clientId that are in the request
        $clientId = $this->server->getRequest()->request->get('clientId');
        // Validate any scopes against spesific client
        $scopeParam = \App\Models\ClientScopesSettingsModel::getClientScopes($clientId);


        $scopes = $this->validateScopes($scopeParam, $client);

        // Create a new session
        $session = new SessionEntity($this->server);
        $session->setOwner('user', $userId);
        $session->associateClient($client);

        // Generate an access token
        $accessToken = new AccessTokenEntity($this->server);
        $accessToken->setId(SecureKey::generate());
        $accessToken->setExpireTime($this->getAccessTokenTTL() + time());

        // Associate scopes with the session and access token
        foreach ($scopes as $scope) {
            $session->associateScope($scope);
        }

        foreach ($session->getScopes() as $scope) {
            $accessToken->associateScope($scope);
        }

        $this->server->getTokenType()->setSession($session);
        $this->server->getTokenType()->setParam('accessToken', $accessToken->getId());
        $this->server->getTokenType()->setParam('expiresIn', $this->getAccessTokenTTL());

        // Associate a refresh token if set
        if ($this->server->hasGrantType('refreshToken')) {
            $refreshToken = new RefreshTokenEntity($this->server);
            $refreshToken->setId(SecureKey::generate());
            $refreshToken->setExpireTime($this->server->getGrantType('refreshToken')->getRefreshTokenTTL() + time());
            $this->server->getTokenType()->setParam('refreshToken', $refreshToken->getId());
        }

        // Save everything
        $session->save();
        $accessToken->setSession($session);
        $accessToken->save();

        if ($this->server->hasGrantType('refreshToken')) {
            $refreshToken->setAccessToken($accessToken);
            $refreshToken->save();
        }

        return $this->server->getTokenType()->generateResponse();
    }

}
