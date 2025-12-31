<?php
namespace Richie314\SimpleMvc\Utils;

class Security
{
    public static function Hash(#[\SensitiveParameter] string $str) : string
    {
        if (strlen(string: $str) === 0)
            return "";
        return password_hash(password: $str, algo: PASSWORD_BCRYPT);
    }

    public static function TestPassword(#[\SensitiveParameter] string $password, string $hash) : bool
    {
        if (strlen(string: $password) === 0 || strlen(string: $hash) === 0)
            return false;
        return password_verify(password: $password, hash: $hash);
    }


    public static function GetIpAddress(bool $allowCloudlfare = false): string {
        
        $headers = getallheaders();
        if ($allowCloudlfare && array_key_exists(key: 'Cf-Connecting-Ip', array: $headers)) {
            // Cloudflare tunnel forwarding
            return $headers['Cf-Connecting-Ip'];
        }

        if (!empty($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP'] !== '::1') {
            //ip from share internet
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //ip pass from proxy
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    public static function LoadEnvironmentOfFromFile(string $var, ?string $default = null): ?string
    {
        if (empty($var)) {
            throw new \Exception(
                message: 'Varible name can\'t be empty!');
        }

        if (array_key_exists(key: $var, array: $_ENV)) {
            return $_ENV[$var];
        }

        if (
            !array_key_exists(key: $var . "_FILE", array: $_ENV) || 
            !file_exists(filename: $_ENV[$var . "_FILE"])) {
            return $default;
        }

        $content =  file_get_contents(filename: $_ENV[$var . "_FILE"]);
        if (!$content) 
            return $default;
        return $content;
    }
}