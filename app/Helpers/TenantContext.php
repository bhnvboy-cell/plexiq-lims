<?php

namespace App\Helpers;

class TenantContext
{
    private static ?int $currentSiteId = null;

    /**
     * Set the current tenant / site facility context ID
     */
    public static function setSiteId(?int $siteId): void
    {
        self::$currentSiteId = $siteId;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['active_site_id'] = $siteId;
        }
    }

    /**
     * Get the active site facility context ID
     */
    public static function getSiteId(): ?int
    {
        if (self::$currentSiteId !== null) {
            return self::$currentSiteId;
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['active_site_id'])) {
            return (int)$_SESSION['active_site_id'];
        }

        // Default to user's assigned site if authenticated
        $user = Auth::user();
        if ($user && !empty($user['site_id'])) {
            return (int)$user['site_id'];
        }

        return null;
    }

    /**
     * Apply site-scoping where clause to SQL queries
     *
     * @param string $sql
     * @param array<int|string, mixed> $params
     * @param string $column (e.g. 'site_id' or 's.site_id')
     * @return array{sql: string, params: array<int|string, mixed>}
     */
    public static function scopeQuery(string $sql, array $params = [], string $column = 'site_id'): array
    {
        $siteId = self::getSiteId();
        if ($siteId === null) {
            return ['sql' => $sql, 'params' => $params];
        }

        $connector = str_contains(strtoupper($sql), 'WHERE') ? ' AND ' : ' WHERE ';
        $scopedSql = $sql . $connector . "{$column} = ?";
        $params[] = $siteId;

        return ['sql' => $scopedSql, 'params' => $params];
    }
}
