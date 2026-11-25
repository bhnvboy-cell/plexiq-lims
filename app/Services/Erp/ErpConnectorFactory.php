<?php

namespace App\Services\Erp;

use App\Helpers\Database;

class ErpConnectorFactory
{
    /**
     * Create an ERP connector instance by configuration array or ERP type
     *
     * @param array<string, mixed>|int $configOrId
     * @return ErpConnectorInterface
     */
    public static function create(array|int $configOrId): ErpConnectorInterface
    {
        $config = [];
        if (is_int($configOrId)) {
            $db = Database::connect();
            $stmt = $db->prepare("SELECT * FROM erp_connectors WHERE id = ?");
            $stmt->execute([$configOrId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \InvalidArgumentException("ERP connector with ID {$configOrId} not found.");
            }
            $config = $row;
            if (is_string($config['auth_credentials'])) {
                $config['auth_credentials'] = json_decode($config['auth_credentials'], true) ?? [];
            }
        } else {
            $config = $configOrId;
        }

        $type = $config['erp_type'] ?? 'sap_hana';
        $baseUrl = $config['base_url'] ?? '';
        $authType = $config['auth_type'] ?? 'basic';
        $credentials = $config['auth_credentials'] ?? [];

        return match ($type) {
            'sap_hana' => new SapHanaConnector($baseUrl, $authType, $credentials),
            'oracle_cloud' => new OracleCloudConnector($baseUrl, $authType, $credentials),
            'dynamics_365' => new DynamicsConnector($baseUrl, $authType, $credentials),
            default => throw new \InvalidArgumentException("Unsupported ERP connector type: {$type}")
        };
    }
}
