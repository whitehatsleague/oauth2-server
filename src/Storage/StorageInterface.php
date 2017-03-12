<?php
/**
 * OAuth 2.0 Storage interface
 *
 * @package     league/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace Whitehatsleague\OAuth2\Server\Storage;

use Whitehatsleague\OAuth2\Server\AbstractServer;

/**
 * Storage interface
 */
interface StorageInterface
{
    /**
     * Set the server
     *
     * @param \Whitehatsleague\OAuth2\Server\AbstractServer $server
     */
    public function setServer(AbstractServer $server);
}
