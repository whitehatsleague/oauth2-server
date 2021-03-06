<?php

/**
 * OAuth 2.0 Access Denied Exception
 *
 * @package     league/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace Whitehatsleague\OAuth2\Server\Exception;

/**
 * Exception class
 */
class AccessDeniedException extends OAuthException
{

    /**
     * {@inheritdoc}
     */
    public $httpStatusCode = 401;

    /**
     * {@inheritdoc}
     */
    public $errorType = 'access_denied';
    public $errorMessage = 'The resource owner or authorization server denied the request.';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct('The resource owner or authorization server denied the request.');
    }

}
