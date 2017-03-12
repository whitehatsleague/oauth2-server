<?php
/**
 * OAuth 2.0 Access token storage interface
 *
 * @package     league/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace Whitehatsleague\OAuth2\Server\Storage;

use Whitehatsleague\OAuth2\Server\Entity\AccessTokenEntity;
use Whitehatsleague\OAuth2\Server\Entity\ScopeEntity;

/**
 * Access token interface
 */
interface AccessTokenInterface extends StorageInterface
{
    /**
     * Get an instance of Entity\AccessTokenEntity
     *
     * @param string $token The access token
     *
     * @return \Whitehatsleague\OAuth2\Server\Entity\AccessTokenEntity | null
     */
    public function get($token);

    /**
     * Get the scopes for an access token
     *
     * @param \Whitehatsleague\OAuth2\Server\Entity\AccessTokenEntity $token The access token
     *
     * @return \Whitehatsleague\OAuth2\Server\Entity\ScopeEntity[] Array of \Whitehatsleague\OAuth2\Server\Entity\ScopeEntity
     */
    public function getScopes(AccessTokenEntity $token);

    /**
     * Creates a new access token
     *
     * @param string         $token      The access token
     * @param integer        $expireTime The expire time expressed as a unix timestamp
     * @param string|integer $sessionId  The session ID
     *
     * @return void
     */
    public function create($token, $expireTime, $sessionId);

    /**
     * Associate a scope with an acess token
     *
     * @param \Whitehatsleague\OAuth2\Server\Entity\AccessTokenEntity $token The access token
     * @param \Whitehatsleague\OAuth2\Server\Entity\ScopeEntity       $scope The scope
     *
     * @return void
     */
    public function associateScope(AccessTokenEntity $token, ScopeEntity $scope);

    /**
     * Delete an access token
     *
     * @param \Whitehatsleague\OAuth2\Server\Entity\AccessTokenEntity $token The access token to delete
     *
     * @return void
     */
    public function delete(AccessTokenEntity $token);
}
