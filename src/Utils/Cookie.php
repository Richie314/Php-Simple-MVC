<?php
namespace Richie314\SimpleMvc\Utils;

class Cookie
{
    public static function Set(
        string $name, 
        string $value, 
        int $exp, 
        string $path = '/', 
        string $domain = '',
    ): bool
    {
        if (strlen(string: $name) === 0) 
            return false;

        return setcookie(
            name: $name, 
            value: $value, 
            expires_or_options: time() + $exp, 
            path: $path, 
            domain: $domain, 
            secure: false, 
            httponly: true
        );
    }
    public static function Get(string $name): mixed
    {
        if (strlen(string: $name) === 0)
        {
            return null;
        }
        return $_COOKIE[$name];
    }
    public static function Delete(string $name): bool
    {
        return self::Set(name: $name, value: "", exp: -3600);
    }
    public static function DeleteIfItIs(string $name, string $value): bool
    {
        if (!self::Exists(name: $name))
            return false;
        if (self::Get(name: $name) !== $value)
            return true;
        return self::Delete(name: $name);
    }
    public static function Exists(string $name): bool
    {
        return 
            (strlen(string: $name) !== 0) && 
            array_key_exists(key: $name, array: $_COOKIE) &&
            isset($_COOKIE[$name]);
    }
    public static function DeleteIfExists(string $name): bool
    {
        if (!self::Exists(name: $name))
        {
            return true;
        }
        return self::Delete(name: $name);
    }
}