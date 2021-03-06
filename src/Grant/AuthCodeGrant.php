<?php

/**
 * OAuth 2.0 Auth code grant
 *
 * @package     league/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace Whitehatsleague\OAuth2\Server\Grant;

use Whitehatsleague\OAuth2\Server\Entity\AccessTokenEntity;
use Whitehatsleague\OAuth2\Server\Entity\AuthCodeEntity;
use Whitehatsleague\OAuth2\Server\Entity\ClientEntity;
use Whitehatsleague\OAuth2\Server\Entity\RefreshTokenEntity;
use Whitehatsleague\OAuth2\Server\Entity\SessionEntity;
use Whitehatsleague\OAuth2\Server\Event;
use Whitehatsleague\OAuth2\Server\Exception;
use Whitehatsleague\OAuth2\Server\Util\SecureKey;

/**
 * Auth code grant class
 */
class AuthCodeGrant extends AbstractGrant
{

    /**
     * Grant identifier
     *
     * @var string
     */
    protected $identifier = 'authorization_code';

    /**
     * Response type
     *
     * @var string
     */
    protected $responseType = 'code';

    /**
     * AuthServer instance
     *
     * @var \Whitehatsleague\OAuth2\Server\AuthorizationServer
     */
    protected $server = null;

    /**
     * Access token expires in override
     *
     * @var int
     */
    protected $accessTokenTTL = null;

    /**
     * The TTL of the auth token
     *
     * @var integer
     */
    protected $authTokenTTL = 600;

    /**
     * Whether to require the client secret when
     * completing the flow.
     *
     * @var boolean
     */
    protected $requireClientSecret = true;

    /**
     * Override the default access token expire time
     *
     * @param int $authTokenTTL
     *
     * @return void
     */
    public function setAuthTokenTTL($authTokenTTL)
    {
        $this->authTokenTTL = $authTokenTTL;
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
     * Check authorize parameters
     *
     * @return array Authorize request parameters
     *
     * @throws
     */
    public function checkAuthorizeParams()
    {
        // Get required params
        $clientId = $this->server->getRequest()->query->get('clientId', null);
        if (is_null($clientId)) {
            $InvalidRequestException = new Exception\InvalidRequestException('clientId');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        $redirectUri = $this->server->getRequest()->query->get('redirectUri', null);
        if (is_null($redirectUri)) {
            $InvalidRequestException = new Exception\InvalidRequestException('redirectUri');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        // Validate client ID and redirect URI
        $client = $this->server->getClientStorage()->get(
                $clientId, null, $redirectUri, $this->getIdentifier()
        );

        if (($client instanceof ClientEntity) === false) {
            $this->server->getEventEmitter()->emit(new Event\ClientAuthenticationFailedEvent($this->server->getRequest()));
            $InvalidClientException = new Exception\InvalidClientException();
            abort($InvalidClientException->httpStatusCode, $InvalidClientException->errorMessage);
        }

        $state = $this->server->getRequest()->query->get('state', null);
        if ($this->server->stateParamRequired() === true && is_null($state)) {
            $InvalidRequestException = new Exception\InvalidRequestException('state', $redirectUri);
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        $responseType = $this->server->getRequest()->query->get('responseType', null);
        if (is_null($responseType)) {
            $InvalidRequestException = new Exception\InvalidRequestException('responseType', $redirectUri);
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        // Ensure response type is one that is recognised
        if (!in_array($responseType, $this->server->getResponseTypes())) {
            $UnsupportedResponseTypeException = new Exception\UnsupportedResponseTypeException($responseType, $redirectUri);
            abort($UnsupportedResponseTypeException->httpStatusCode, $UnsupportedResponseTypeException->errorMessage);
        }

        // Validate any scopes that are in the request
        $scopeParam = $this->server->getRequest()->query->get('scope', '');
        $scopes = $this->validateScopes($scopeParam, $client, $redirectUri);

        return [
            'client' => $client,
            'redirectUri' => $redirectUri,
            'state' => $state,
            'responseType' => $responseType,
            'scopes' => $scopes
        ];
    }

    /**
     * Parse a new authorize request
     *
     * @param string $type       The session owner's type
     * @param string $typeId     The session owner's ID
     * @param array  $authParams The authorize request $_GET parameters
     *
     * @return string An authorisation code
     */
    public function newAuthorizeRequest($type, $typeId, $authParams = [])
    {
        // Create a new session
        $session = new SessionEntity($this->server);
        $session->setOwner($type, $typeId);
        $session->associateClient($authParams['client']);

        // Create a new auth code
        $authCode = new AuthCodeEntity($this->server);
        $authCode->setId(SecureKey::generate());
        $authCode->setRedirectUri($authParams['redirectUri']);
        $authCode->setExpireTime(time() + $this->authTokenTTL);

        foreach ($authParams['scopes'] as $scope) {
            $authCode->associateScope($scope);
            $session->associateScope($scope);
        }

        $session->save();
        $authCode->setSession($session);
        $authCode->save();

        return $authCode->generateRedirectUri($authParams['state']);
    }

    /**
     * Complete the auth code grant
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
        if ($this->shouldRequireClientSecret() && is_null($clientSecret)) {
            $InvalidRequestException = new Exception\InvalidRequestException('clientSecret');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        $redirectUri = $this->server->getRequest()->request->get('redirectUri', null);
        if (is_null($redirectUri)) {
            $InvalidRequestException = new Exception\InvalidRequestException('redirectUri');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        // Validate client ID and client secret
        $client = $this->server->getClientStorage()->get(
                $clientId, $clientSecret, $redirectUri, $this->getIdentifier()
        );

        if (($client instanceof ClientEntity) === false) {
            $this->server->getEventEmitter()->emit(new Event\ClientAuthenticationFailedEvent($this->server->getRequest()));
            $InvalidClientException = new Exception\InvalidClientException();
            abort($InvalidClientException->httpStatusCode, $InvalidClientException->errorMessage);
        }

        // Validate the auth code
        $authCode = $this->server->getRequest()->request->get('code', null);
        if (is_null($authCode)) {
            $InvalidRequestException = new Exception\InvalidRequestException('code');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        $code = $this->server->getAuthCodeStorage()->get($authCode);
        if (($code instanceof AuthCodeEntity) === false) {
            $InvalidRequestException = new Exception\InvalidRequestException('code');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        // Ensure the auth code hasn't expired
        if ($code->isExpired() === true) {
            $InvalidRequestException = new Exception\InvalidRequestException('code');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        // Check redirect URI presented matches redirect URI originally used in authorize request
        if ($code->getRedirectUri() !== $redirectUri) {
            $InvalidRequestException = new Exception\InvalidRequestException('redirectUri');
            abort($InvalidRequestException->httpStatusCode, $InvalidRequestException->errorMessage);
        }

        $session = $code->getSession();
        $session->associateClient($client);

        $authCodeScopes = $code->getScopes();

        // Generate the access token
        $accessToken = new AccessTokenEntity($this->server);
        $accessToken->setId(SecureKey::generate());
        $accessToken->setExpireTime($this->getAccessTokenTTL() + time());

        foreach ($authCodeScopes as $authCodeScope) {
            $session->associateScope($authCodeScope);
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

        // Expire the auth code
        $code->expire();

        // Save all the things
        $accessToken->setSession($session);
        $accessToken->save();

        if (isset($refreshToken) && $this->server->hasGrantType('refreshToken')) {
            $refreshToken->setAccessToken($accessToken);
            $refreshToken->save();
        }

        return $this->server->getTokenType()->generateResponse();
    }

}
