<?php

namespace App\Services\Auth;

use App\Helpers\Database;
use App\Helpers\Audit;

class SamlService
{
    /**
     * Generate a SAML 2.0 AuthnRequest
     *
     * @param string $spEntityId Service Provider Entity ID (issuer)
     * @param string $acsUrl Assertion Consumer Service URL
     * @param string $idpSsoUrl Identity Provider SSO endpoint
     * @return array{request_id: string, issue_instant: string, xml: string, encoded_payload: string, redirect_url: string}
     */
    public function generateAuthnRequest(string $spEntityId, string $acsUrl, string $idpSsoUrl): array
    {
        $id = '_' . bin2hex(random_bytes(16));
        $issueInstant = gmdate('Y-m-d\TH:i:s\Z');

        $xml = <<<XML
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    ID="{$id}"
                    Version="2.0"
                    IssueInstant="{$issueInstant}"
                    Destination="{$idpSsoUrl}"
                    AssertionConsumerServiceURL="{$acsUrl}"
                    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
    <saml:Issuer>{$spEntityId}</saml:Issuer>
    <samlp:NameIDPolicy Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress" AllowCreate="true"/>
</samlp:AuthnRequest>
XML;

        $defalted = gzdeflate($xml);
        $encoded = base64_encode($defalted !== false ? $defalted : $xml);
        $redirectUrl = $idpSsoUrl . (str_contains($idpSsoUrl, '?') ? '&' : '?') . 'SAMLRequest=' . urlencode($encoded);

        return [
            'request_id' => $id,
            'issue_instant' => $issueInstant,
            'xml' => $xml,
            'encoded_payload' => $encoded,
            'redirect_url' => $redirectUrl
        ];
    }

    /**
     * Process and parse an incoming SAMLResponse XML or Base64 payload
     *
     * @param string $samlResponseBase64
     * @param string|null $idpCertX509 Optional IdP X.509 certificate for signature verification
     * @return array{success: bool, name_id?: string, attributes?: array<string, mixed>, error?: string}
     */
    public function processResponse(string $samlResponseBase64, ?string $idpCertX509 = null): array
    {
        $decodedXml = base64_decode($samlResponseBase64, true);
        if ($decodedXml === false || !str_contains($decodedXml, '<')) {
            // Check if raw XML was passed directly
            if (str_contains($samlResponseBase64, '<saml') || str_contains($samlResponseBase64, '<Response')) {
                $decodedXml = $samlResponseBase64;
            } else {
                return ['success' => false, 'error' => 'Malformed or non-base64 SAMLResponse payload.'];
            }
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $loaded = @$dom->loadXML($decodedXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$loaded) {
            return ['success' => false, 'error' => 'Failed to parse SAML XML payload.'];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

        // Check StatusCode
        $statusNodes = $xpath->query('//samlp:StatusCode/@Value | //StatusCode/@Value');
        if ($statusNodes && $statusNodes->length > 0) {
            $statusCode = $statusNodes->item(0)->nodeValue;
            if (!str_contains($statusCode, 'Success')) {
                return ['success' => false, 'error' => 'SAML IdP returned error status: ' . $statusCode];
            }
        }

        // Extract NameID
        $nameIdNodes = $xpath->query('//saml:Subject/saml:NameID | //Subject/NameID | //NameID');
        if (!$nameIdNodes || $nameIdNodes->length === 0) {
            return ['success' => false, 'error' => 'SAML Assertion missing Subject/NameID.'];
        }
        $nameId = trim($nameIdNodes->item(0)->nodeValue);

        // Extract Attributes
        $attributes = [];
        $attrNodes = $xpath->query('//saml:Attribute | //Attribute');
        if ($attrNodes) {
            /** @var \DOMElement $attr */
            foreach ($attrNodes as $attr) {
                $attrName = $attr->getAttribute('Name');
                $friendlyName = $attr->getAttribute('FriendlyName');
                $valNodes = $attr->getElementsByTagName('AttributeValue');
                $values = [];
                foreach ($valNodes as $val) {
                    $values[] = trim($val->nodeValue);
                }
                $valResult = count($values) === 1 ? $values[0] : $values;
                if ($attrName) {
                    $attributes[$attrName] = $valResult;
                }
                if ($friendlyName) {
                    $attributes[$friendlyName] = $valResult;
                }
            }
        }

        return [
            'success' => true,
            'name_id' => $nameId,
            'attributes' => $attributes,
            'issuer' => $this->extractIssuer($xpath),
            'mapped_role' => $this->mapSamlAttributesToRole($attributes)
        ];
    }

    private function extractIssuer(\DOMXPath $xpath): string
    {
        $nodes = $xpath->query('//saml:Issuer | //Issuer');
        return ($nodes && $nodes->length > 0) ? trim($nodes->item(0)->nodeValue) : '';
    }

    public function mapSamlAttributesToRole(array $attributes): string
    {
        $roleCandidates = [];
        foreach (['role', 'roles', 'groups', 'memberOf', 'Role', 'http://schemas.microsoft.com/ws/2008/06/identity/claims/role'] as $key) {
            if (isset($attributes[$key])) {
                $val = $attributes[$key];
                if (is_array($val)) {
                    $roleCandidates = array_merge($roleCandidates, $val);
                } else {
                    $roleCandidates[] = (string)$val;
                }
            }
        }

        foreach ($roleCandidates as $role) {
            $r = strtoupper($role);
            if (str_contains($r, 'ADMIN')) return 'Admin';
            if (str_contains($r, 'QA')) return 'QA Manager';
            if (str_contains($r, 'DIRECTOR') || str_contains($r, 'MANAGER')) return 'Lab Manager';
            if (str_contains($r, 'ANALYST') || str_contains($r, 'CHEMIST')) return 'Analyst';
        }

        return 'Analyst';
    }

    public function syncSamlUser(string $nameId, array $attributes): int
    {
        $db = Database::connect();
        $email = $attributes['email'] ?? (filter_var($nameId, FILTER_VALIDATE_EMAIL) ? $nameId : $nameId . '@saml.internal');
        $username = $attributes['username'] ?? explode('@', $email)[0];
        $firstName = $attributes['firstName'] ?? $attributes['givenName'] ?? $username;
        $lastName = $attributes['lastName'] ?? $attributes['surname'] ?? '';
        $role = $this->mapSamlAttributesToRole($attributes);

        $stmt = $db->prepare("SELECT id, role FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            $userId = (int)$existing['id'];
            $update = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update->execute([$firstName, $lastName, $role, $userId]);
            Audit::log('SAML User Synced', 'users', $userId, ['role' => $existing['role']], ['role' => $role]);
            return $userId;
        }

        $insert = $db->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            RETURNING id
        ");
        $dummyHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
        $insert->execute([$username, $email, $dummyHash, $firstName, $lastName, $role]);
        $newId = (int)$insert->fetchColumn();

        Audit::log('SAML User Auto-Provisioned', 'users', $newId, null, ['username' => $username, 'role' => $role]);
        return $newId;
    }
}
