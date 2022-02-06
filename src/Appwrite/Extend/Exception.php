<?php

namespace Appwrite\Extend;

use Utopia\Config\Config;

class Exception extends \Exception
{
    /**
     * Error Codes
     *  
     * Naming the error types based on the following convention 
     * <ENTITY>_<ERROR_TYPE>
     * 
     * Appwrite has the follwing entities:
     * - Users
     * - Projects
     * - Sessions
     * - Teams
     * - Memberships
     * - Files
     * - Functions
     * - Deployments
     * - Executions
     */

    /** Users */
    const USER_COUNT_EXCEEDED               = 'user_count_exceeded';
    const USER_ALREADY_EXISTS               = 'user_already_exists';
    const USER_BLOCKED                      = 'user_blocked';
    const USER_CREATION_FAILED              = 'user_creation_failed';
    const USER_INVALID_TOKEN                = 'user_invalid_token';
    const USER_NOT_FOUND                    = 'user_not_found';
    const USER_INVALID_CREDENTIALS          = 'user_invalid_credentials';
    const USER_EMAIL_ALREADY_EXISTS         = 'user_email_already_exists';
    const USER_PASSWORD_MISMATCH            = 'user_password_mismatch';
    const USER_AUTH_METHOD_UNSUPPORTED      = 'user_auth_method_unsupported';
    const USER_PASSWORD_RESET_REQUIRED      = 'user_password_reset_required';
    const USER_EMAIL_NOT_WHITELISTED        = 'user_email_not_whitelisted';
    const USER_IP_NOT_WHITELISTED           = 'user_ip_not_whitelisted';
    const USER_SESSION_ALREADY_EXISTS       = 'user_session_already_exists';
    const USER_SESSION_NOT_FOUND            = 'user_session_not_found';
    const USER_ANONYMOUS_CONSOLE_PROHIBITED = 'user_anonymous_console_prohibited';

    /** OAuth **/
    const OAUTH_PROVIDER_DISABLED          = 'oauth_provider_disabled';
    const OAUTH_PROVIDER_UNSUPPORTED       = 'oauth_provider_unsupported';
    const OAUTH_INVALID_LOGIN_STATE_PARAMS = 'oauth_invalid_login_state_params';
    const OAUTH_INVALID_SUCCESS_URL        = 'oauth_invalid_success_url';
    const OAUTH_INVALID_FAILURE_URL        = 'oauth_invalid_failure_url';
    const OAUTH_ACCESS_TOKEN_FAILED        = 'oauth_access_token_failed';
    const OAUTH_MISSING_USER_ID            = 'oauth_missing_user_id';

    /** Avatars */
    const AVATAR_SET_NOT_FOUND             = 'avatar_set_not_found';
    const AVATAR_NOT_FOUND                 = 'avatar_not_found';
    const IMAGIC_EXTENSION_MISSING         = 'imagic_extension_missing';
    const AVATAR_IMAGE_NOT_FOUND           = 'avatar_image_not_found';
    const AVATAR_CANNOT_PARSE_IMAGE        = 'avatar_cannot_parse_image';

    /** Files */
    const FILE_NOT_FOUND                   = 'file_not_found';
    const FILE_NOT_READABLE                = 'file_not_readable';

    /** Projects */
    const PROJECT_NOT_FOUND       = 'project_not_found';
    const PROJECT_UNKNOWN         = 'project_unknown';

    /** API */
    const UNKNOWN                 = 'unknown';
    const UNKNOWN_ORIGIN          = 'unknown_origin';
    const SERVICE_DISABLED        = 'service_disabled';
    const UNAUTHORIZED_SCOPE      = 'unauthorized_scope';
    const STORAGE_ERROR           = 'storage_error';
    const RATE_LIMIT_EXCEEDED     = 'rate_limit_exceeded';
    const SMTP_DISABLED           = 'smtp_disabled';


    private $errorCode = '';

    public function __construct(string $message, int $code = 0, string $errorCode = Exception::UNKNOWN, \Throwable $previous = null)
    {
        $this->errorCode = $errorCode;

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */ 
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}