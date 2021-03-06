<?php

/**
 * OAuth 2.0 Client credentials grant
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
use Whitehatsleague\OAuth2\Server\Entity\SessionEntity;
use Whitehatsleague\OAuth2\Server\Event;
use Whitehatsleague\OAuth2\Server\Exception;
use Whitehatsleague\OAuth2\Server\Util\SecureKey;

/**
 * Client credentials grant class
 */
class ClientCredentialsGrant extends AbstractGrant
{

    /**
     * Grant identifier
     *
     * @var string
     */
    protected $identifier = 'client_credentials';

    /**
     * Response type
     *
     * @var string
     */
    protected $responseType = null;

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
     * Complete the client credentials grant
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

        // Validate any scopes that are in the request
        $scopeParam = $this->server->getRequest()->request->get('scope', '');
        $scopes = $this->validateScopes($scopeParam, $client);

        // Create a new session
        $session = new SessionEntity($this->server);
        $session->setOwner('client', $client->getId());
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

        // Save everything
        $session->save();
        $accessToken->setSession($session);
        $accessToken->save();

        $this->server->getTokenType()->setSession($session);
        $this->server->getTokenType()->setParam('accessToken', $accessToken->getId());
        $this->server->getTokenType()->setParam('expiresIn', $this->getAccessTokenTTL());

        return $this->server->getTokenType()->generateResponse();
    }

}
