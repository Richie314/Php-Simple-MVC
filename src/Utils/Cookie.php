<?php
namespace Richie314\SimpleMvc\Utils;

class Cookie
{
    /**
     * Queue a cookie using the existing HTTP-only defaults.
     *
     * Uses secure=false, httpOnly=true, and no SameSite attribute. Call before output.
     * The incoming $_COOKIE array is not updated.
     *
     * @param string $name Cookie name; an empty name returns false.
     * @param string $value Cookie value, encoded by PHP for transport.
     * @param int $exp Lifetime in seconds relative to now; negative values expire the cookie.
     * @param string $path Cookie path, which must match when deleting.
     * @param string $domain Cookie domain; empty uses the current host.
     * @return bool Whether the cookie header was successfully queued.
     */
    public static function Set(
        string $name, 
        string $value, 
        int $exp, 
        string $path = '/', 
        string $domain = '',
    ): bool
    {
        return self::SetWithOptions($name, $value, $exp, $path, $domain);
    }

    /**
     * Queue a cookie with explicit transport and browser security options.
     *
     * Call before output. SameSite=None typically requires secure=true in browsers.
     * The incoming $_COOKIE array is not updated.
     * @throws \ValueError If PHP rejects the cookie name or options.
     *
     * @param string $name Cookie name; an empty name returns false.
     * @param string $value Cookie value, encoded by PHP for transport.
     * @param int $exp Lifetime in seconds relative to now; negative values expire the cookie.
     * @param string $path Cookie path, which must match when deleting.
     * @param string $domain Cookie domain; empty uses the current host.
     * @param bool $secure Whether the browser should send the cookie only over HTTPS.
     * @param bool $httpOnly Whether browser scripts are prevented from reading the cookie.
     * @param string $sameSite SameSite value, typically Lax, Strict, or None; empty omits it.
     * @return bool Whether the cookie header was successfully queued.
     */
    public static function SetWithOptions(
        string $name,
        string $value,
        int $exp,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = '',
    ): bool
    {
        if (strlen(string: $name) === 0) 
            return false;

        $options = [
            'expires' => time() + $exp,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
        ];
        if ($sameSite !== '') {
            $options['samesite'] = $sameSite;
        }
        return setcookie($name, $value, $options);
    }
    /**
     * Read a cookie from the incoming request.
     *
     * @param string $name Cookie name.
     * @return mixed Cookie value, or null for an empty or absent name.
     */
    public static function Get(string $name): mixed
    {
        if (strlen(string: $name) === 0)
        {
            return null;
        }
        return $_COOKIE[$name] ?? null;
    }
    /**
     * Expire a cookie using the default path / and current host.
     *
     * Use DeleteWithOptions() for cookies created with a different path or domain.
     * The incoming $_COOKIE array is not updated.
     *
     * @param string $name Cookie name.
     * @return bool Whether the expiration header was queued.
     */
    public static function Delete(string $name): bool
    {
        return self::Set(name: $name, value: "", exp: -3600);
    }
    /**
     * Expire a cookie at its original path and domain.
     *
     * Call before output. The incoming $_COOKIE array is not updated.
     *
     * @param string $name Cookie name.
     * @param string $path Original cookie path.
     * @param string $domain Original cookie domain; empty uses the current host.
     * @return bool Whether the expiration header was queued.
     */
    public static function DeleteWithOptions(string $name, string $path = '/', string $domain = ''): bool
    {
        return self::Set(name: $name, value: "", exp: -3600, path: $path, domain: $domain);
    }
    /**
     * Expire a default-scope cookie only when its incoming value matches.
     *
     * @param string $name Cookie name.
     * @param string $value Value required to trigger deletion.
     * @return bool False if absent; true if different; otherwise the deletion result.
     */
    public static function DeleteIfItIs(string $name, string $value): bool
    {
        if (!self::Exists(name: $name))
            return false;
        if (self::Get(name: $name) !== $value)
            return true;
        return self::Delete(name: $name);
    }
    /**
     * Check whether a non-null cookie exists in the incoming request.
     *
     * @param string $name Cookie name; empty names never exist.
     * @return bool Whether a non-null value is present in $_COOKIE.
     */
    public static function Exists(string $name): bool
    {
        return 
            (strlen(string: $name) !== 0) && 
            array_key_exists(key: $name, array: $_COOKIE) &&
            isset($_COOKIE[$name]);
    }
    /**
     * Expire a default-scope cookie if it exists in the incoming request.
     *
     * @param string $name Cookie name.
     * @return bool True if absent; otherwise the deletion result.
     */
    public static function DeleteIfExists(string $name): bool
    {
        if (!self::Exists(name: $name))
        {
            return true;
        }
        return self::Delete(name: $name);
    }
}