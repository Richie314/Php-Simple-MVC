<?php
namespace Richie314\SimpleMvc\Utils;

class Security
{
    /**
     * Create a bcrypt password hash.
     *
     * @param string $str Password to hash; an empty password returns an empty string.
     * @return string Bcrypt hash, or an empty string for an empty input.
     */
    public static function Hash(#[\SensitiveParameter] string $str) : string
    {
        if (strlen(string: $str) === 0)
            return "";
        return password_hash(password: $str, algo: PASSWORD_BCRYPT);
    }

    /**
     * Verify a password against its stored hash.
     *
     * @param string $password Plaintext password to verify.
     * @param string $hash Stored password hash accepted by password_verify().
     * @return bool True for a match; false for an empty password or hash.
     */
    public static function TestPassword(#[\SensitiveParameter] string $password, string $hash) : bool
    {
        if (strlen(string: $password) === 0 || strlen(string: $hash) === 0)
            return false;
        return password_verify(password: $password, hash: $hash);
    }


    /**
     * Read a client address using the legacy proxy-header precedence.
     *
     * After optional Cloudflare lookup, checks HTTP_CLIENT_IP (except ::1), then
     * HTTP_X_FORWARDED_FOR, then REMOTE_ADDR. Forwarded values are not validated or
     * split into individual addresses. Trust them only behind sanitized proxy headers.
     *
     * @param bool $allowCloudlfare Whether to prefer CF-Connecting-IP; parameter spelling retained for compatibility.
     * @return string Selected header or REMOTE_ADDR, or an empty string when unavailable.
     */
    public static function GetIpAddress(bool $allowCloudlfare = false): string {
        
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers = array_change_key_case($headers, CASE_LOWER);
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $headers['cf-connecting-ip'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if ($allowCloudlfare && array_key_exists(key: 'cf-connecting-ip', array: $headers)) {
            // Cloudflare tunnel forwarding
            return $headers['cf-connecting-ip'];
        }

        if (!empty($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP'] !== '::1') {
            //ip from share internet
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //ip pass from proxy
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Read an environment value directly or from its companion _FILE path.
     *
     * Direct $_ENV values take precedence. Empty files and the string "0" are valid;
     * file contents are not trimmed. This method does not consult getenv().
     * @throws \Exception If the variable name is empty according to PHP empty().
     *
     * @param string $var Key in $_ENV; the companion key is the name followed by _FILE.
     * @param string|null $default Fallback when neither a direct value nor readable file content is available.
     * @return string|null Direct value, verbatim file contents, or the fallback.
     */
    public static function LoadEnvironmentOfFromFile(string $var, ?string $default = null): ?string
    {
        if (empty($var)) {
            throw new \Exception(
                message: 'Varible name can\'t be empty!');
        }

        if (array_key_exists(key: $var, array: $_ENV)) {
            return $_ENV[$var];
        }

        if (!array_key_exists(key: $var . "_FILE", array: $_ENV) ||
            !file_exists(filename: $_ENV[$var . "_FILE"])) {
            return $default;
        }

        $content =  file_get_contents(filename: $_ENV[$var . "_FILE"]);
        if ($content === false)
            return $default;
        return $content;
    }
}