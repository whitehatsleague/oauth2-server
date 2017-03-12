<?php
/**
 * OAuth 2.0 Refresh token storage interface
 *
 * @package     league/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace Whitehatsleague\OAuth2\Server\Storage;

use Whitehatsleague\OAuth2\Server\Entity\RefreshTokenEntity;

/**
 * Refresh token interface
 */
interface RefreshTokenInterface extends StorageInterface
{
    /**
     * Return a new instance of \Whitehatsleague\OAuth2\Server\Entity\RefreshTokenEntity
     *
     * @param string $token
     *
     * @return \Whitehatsleague\OAuth2\Server\Entity\RefreshTokenEntity | null
     */
    public function get($token);

    /**
     * Create a new refresh token_name
     *
     * @param string  $token
     * @param integer $expireTime
     * @param string  $accessToken
     *
     * @return \Whitehatsleague\OAuth2\Server\Entity\RefreshTokenEntity
     */
    public function create($token, $expireTime, $accessToken);

    /**
     * Delete the refresh token
     *
     * @param \Whitehatsleague\OAuth2\Server\Entity\RefreshTokenEntity $token
     *
     * @return void
     */
    public function delete(RefreshTokenEntity $token);
}
