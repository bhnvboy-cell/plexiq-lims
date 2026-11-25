<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Erp\SapHanaConnector;
use App\Services\Erp\OracleCloudConnector;
use App\Services\Erp\DynamicsConnector;
use App\Services\Erp\ErpConnectorFactory;
use App\Services\Auth\LdapService;
use App\Services\Auth\SamlService;

class EnterpriseIdentityAndErpTest extends TestCase
{
    public function testSapHanaConnectorWorkflow(): void
    {
        $connector = new SapHanaConnector('https://sap-gateway.internal:44300/sap/opu/odata/sap', 'oauth2_client_credentials', [
            'sys_id' => 'S4H',
            'plant' => '1010'
        ]);

        $ping = $connector->ping();
        $this->assertTrue($ping['success']);
        $this->assertSame('S4H', $ping['system_id']);

        $lots = $connector->pullInspectionLots(['limit' => 3]);
        $this->assertCount(3, $lots);
        $this->assertSame('sap_hana', $lots[0]['erp_type']);
        $this->assertNotEmpty($lots[0]['InspectionLot']);
        $this->assertNotEmpty($lots[0]['InspectionSpecifications']);

        $push = $connector->pushInspectionResults('10000000001', [
            ['code' => '0010', 'numeric_value' => 99.8, 'status' => 'pass', 'analyst_name' => 'Dr. Jones']
        ], 'A');
        $this->assertTrue($push['success']);
        $this->assertStringStartsWith('SAP-UD-', $push['remote_reference']);
        $this->assertSame('A', $push['usage_decision']['InspLotUsageDecisionCode']);

        $sync = $connector->syncMasterData('materials');
        $this->assertGreaterThan(0, $sync['synced_count']);
        $this->assertNotEmpty($sync['details']['items']);
    }

    public function testOracleCloudConnectorWorkflow(): void
    {
        $connector = new OracleCloudConnector('https://oracle-fusion.internal/fscmRestApi', 'basic', [
            'org_code' => 'M1'
        ]);

        $ping = $connector->ping();
        $this->assertTrue($ping['success']);
        $this->assertSame('M1', $ping['organization_code']);

        $inspections = $connector->pullInspectionLots(['limit' => 2]);
        $this->assertCount(2, $inspections);
        $this->assertSame('oracle_cloud', $inspections[0]['erp_type']);

        $push = $connector->pushInspectionResults(2000001, [
            ['test_name' => 'Endotoxin LAL', 'result_value' => 0.15, 'status' => 'pass']
        ], 'A');
        $this->assertTrue($push['success']);
        $this->assertSame('ACCEPT', $push['disposition']);

        $sync = $connector->syncMasterData('materials');
        $this->assertGreaterThan(0, $sync['synced_count']);
    }

    public function testDynamicsConnectorWorkflow(): void
    {
        $connector = new DynamicsConnector('https://api.businesscentral.dynamics.com/v2.0', 'oauth2_client_credentials', [
            'company_name' => 'CRONUS USA, Inc.'
        ]);

        $ping = $connector->ping();
        $this->assertTrue($ping['success']);
        $this->assertSame('CRONUS USA, Inc.', $ping['company']);

        $orders = $connector->pullInspectionLots(['limit' => 2]);
        $this->assertCount(2, $orders);
        $this->assertSame('dynamics_365', $orders[0]['erp_type']);

        $push = $connector->pushInspectionResults('QC-2026-0001', [
            ['code' => 'DENSITY', 'numeric_value' => 1.018, 'status' => 'pass']
        ], 'A');
        $this->assertTrue($push['success']);
        $this->assertSame('Accepted', $push['status']);
    }

    public function testErpConnectorFactory(): void
    {
        $sap = ErpConnectorFactory::create(['erp_type' => 'sap_hana', 'base_url' => 'https://sap.local']);
        $this->assertInstanceOf(SapHanaConnector::class, $sap);

        $oracle = ErpConnectorFactory::create(['erp_type' => 'oracle_cloud', 'base_url' => 'https://oracle.local']);
        $this->assertInstanceOf(OracleCloudConnector::class, $oracle);

        $dynamics = ErpConnectorFactory::create(['erp_type' => 'dynamics_365', 'base_url' => 'https://dynamics.local']);
        $this->assertInstanceOf(DynamicsConnector::class, $dynamics);
    }

    public function testLdapRoleMappingAndAuth(): void
    {
        $ldapService = new LdapService();

        $this->assertSame('Admin', $ldapService->mapLdapGroupsToRole(['CN=LIMS_Administrators,OU=Security,DC=corp,DC=internal']));
        $this->assertSame('QA Manager', $ldapService->mapLdapGroupsToRole(['CN=QA_Reviewers,DC=internal']));
        $this->assertSame('Lab Manager', $ldapService->mapLdapGroupsToRole(['CN=LAB_MANAGERS,DC=internal']));
        $this->assertSame('Analyst', $ldapService->mapLdapGroupsToRole(['CN=Chemists_TeamA,DC=internal']));
        $this->assertSame('Guest', $ldapService->mapLdapGroupsToRole(['CN=General_Employees,DC=internal']));

        // Test authentication simulation
        $auth = $ldapService->authenticate('analyst.test', 'Password123!', [
            'ldap_host' => '127.0.0.1',
            'ldap_port' => 389
        ]);
        $this->assertTrue($auth['success']);
        $this->assertSame('analyst.test', $auth['user']['username']);
        $this->assertSame('Analyst', $auth['user']['role']);
    }

    public function testSamlAuthnRequestAndResponseProcessing(): void
    {
        $samlService = new SamlService();

        // 1. Generate AuthnRequest
        $req = $samlService->generateAuthnRequest('https://lims.local/saml/metadata', 'https://lims.local/saml/acs', 'https://idp.local/sso');
        $this->assertNotEmpty($req['request_id']);
        $this->assertStringContainsString('samlp:AuthnRequest', $req['xml']);
        $this->assertStringContainsString('SAMLRequest=', $req['redirect_url']);

        // 2. Process valid mock SAMLResponse XML
        $samlResponseXml = <<<XML
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                ID="_resp_12345" Version="2.0">
    <samlp:Status>
        <samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/>
    </samlp:Status>
    <saml:Assertion ID="_assert_12345" Version="2.0">
        <saml:Issuer>https://idp.example.com</saml:Issuer>
        <saml:Subject>
            <saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">qa.lead@example.com</saml:NameID>
        </saml:Subject>
        <saml:AttributeStatement>
            <saml:Attribute Name="email"><saml:AttributeValue>qa.lead@example.com</saml:AttributeValue></saml:Attribute>
            <saml:Attribute Name="givenName"><saml:AttributeValue>Jane</saml:AttributeValue></saml:Attribute>
            <saml:Attribute Name="surname"><saml:AttributeValue>Doe</saml:AttributeValue></saml:Attribute>
            <saml:Attribute Name="role"><saml:AttributeValue>QA_Specialist</saml:AttributeValue></saml:Attribute>
        </saml:AttributeStatement>
    </saml:Assertion>
</samlp:Response>
XML;

        $parsed = $samlService->processResponse(base64_encode($samlResponseXml));
        $this->assertTrue($parsed['success']);
        $this->assertSame('qa.lead@example.com', $parsed['name_id']);
        $this->assertSame('Jane', $parsed['attributes']['givenName']);
        $this->assertSame('QA Manager', $parsed['mapped_role']);
    }
}
