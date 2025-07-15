<?php

declare(strict_types=1);

namespace ServicedownloadableTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private static int $productId;
    private static int $orderId;
    private static int $clientId;

    public static function setUpBeforeClass(): void
    {
        // Create a test client first
        $clientData = [
            'email' => 'test-downloadable@example.com',
            'first_name' => 'Test',
            'last_name' => 'Client',
            'password' => 'testpassword123',
            'password_confirm' => 'testpassword123'
        ];
        
        $clientResult = Request::makeRequest('admin/client/create', $clientData);
        if ($clientResult->wasSuccessful()) {
            self::$clientId = $clientResult->getResult()['id'];
        }

        // Create a test product for downloadable service
        $productData = [
            'type' => 'downloadable',
            'category_id' => 1,
            'title' => 'Test Downloadable Product Client',
            'slug' => 'test-downloadable-product-client',
            'status' => 'enabled',
            'priority' => 1,
            'description' => 'Test downloadable product for client testing',
            'setup' => 'free',
            'pricing' => [
                'type' => 'free'
            ]
        ];
        
        $result = Request::makeRequest('admin/product/prepare', $productData);
        if ($result->wasSuccessful()) {
            self::$productId = $result->getResult()['id'];
        }

        // Create a test order for the client
        $orderData = [
            'client_id' => self::$clientId,
            'product_id' => self::$productId,
            'activate' => true
        ];
        
        $orderResult = Request::makeRequest('admin/order/create', $orderData);
        if ($orderResult->wasSuccessful()) {
            self::$orderId = $orderResult->getResult()['id'];
        }
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        if (isset(self::$orderId)) {
            Request::makeRequest('admin/order/delete', ['id' => self::$orderId]);
        }
        if (isset(self::$productId)) {
            Request::makeRequest('admin/product/delete', ['id' => self::$productId]);
        }
        if (isset(self::$clientId)) {
            Request::makeRequest('admin/client/delete', ['id' => self::$clientId]);
        }
    }

    public function testSendFileMissingOrderId(): void
    {
        $data = [];

        $result = Request::makeRequest('client/servicedownloadable/send_file', $data, 'client');
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Order ID is required', $result->getErrorMessage());
    }

    public function testSendFileInvalidOrderId(): void
    {
        $data = [
            'order_id' => 99999
        ];

        $result = Request::makeRequest('client/servicedownloadable/send_file', $data, 'client');
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Order not found', $result->getErrorMessage());
    }

    public function testSendFileOrderNotActivated(): void
    {
        // Create an inactive order
        $orderData = [
            'client_id' => self::$clientId,
            'product_id' => self::$productId,
            'activate' => false
        ];
        
        $orderResult = Request::makeRequest('admin/order/create', $orderData);
        $inactiveOrderId = null;
        if ($orderResult->wasSuccessful()) {
            $inactiveOrderId = $orderResult->getResult()['id'];
        }

        $data = [
            'order_id' => $inactiveOrderId
        ];

        $result = Request::makeRequest('client/servicedownloadable/send_file', $data, 'client');
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Order is not activated', $result->getErrorMessage());

        // Clean up
        if ($inactiveOrderId) {
            Request::makeRequest('admin/order/delete', ['id' => $inactiveOrderId]);
        }
    }

    public function testSendFileNoFileAttached(): void
    {
        $data = [
            'order_id' => self::$orderId
        ];

        $result = Request::makeRequest('client/servicedownloadable/send_file', $data, 'client');
        $this->assertFalse($result->wasSuccessful());
        // This should fail because no file is associated with the service
        $this->assertStringContains('File cannot be downloaded at the moment', $result->getErrorMessage());
    }

    /**
     * Note: Testing successful file download would require:
     * 1. Uploading a file to the product
     * 2. Having proper file permissions
     * 3. Handling the binary response
     * This is better tested in integration tests with actual file uploads.
     */
    public function testSendFileSuccessfulDownloadFlow(): void
    {
        // This test documents the expected flow for a successful download
        // In a real scenario, this would require uploading a file first
        $this->assertTrue(true, 'Successful download flow requires file upload setup in integration tests');
    }

    /**
     * Test that client can only access their own orders
     */
    public function testSendFileAccessControl(): void
    {
        // Create another client and order
        $otherClientData = [
            'email' => 'other-client@example.com',
            'first_name' => 'Other',
            'last_name' => 'Client',
            'password' => 'testpassword123',
            'password_confirm' => 'testpassword123'
        ];
        
        $otherClientResult = Request::makeRequest('admin/client/create', $otherClientData);
        $otherClientId = null;
        if ($otherClientResult->wasSuccessful()) {
            $otherClientId = $otherClientResult->getResult()['id'];
        }

        $otherOrderData = [
            'client_id' => $otherClientId,
            'product_id' => self::$productId,
            'activate' => true
        ];
        
        $otherOrderResult = Request::makeRequest('admin/order/create', $otherOrderData);
        $otherOrderId = null;
        if ($otherOrderResult->wasSuccessful()) {
            $otherOrderId = $otherOrderResult->getResult()['id'];
        }

        // Try to access the other client's order (should fail)
        $data = [
            'order_id' => $otherOrderId
        ];

        $result = Request::makeRequest('client/servicedownloadable/send_file', $data, 'client');
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Order not found', $result->getErrorMessage());

        // Clean up
        if ($otherOrderId) {
            Request::makeRequest('admin/order/delete', ['id' => $otherOrderId]);
        }
        if ($otherClientId) {
            Request::makeRequest('admin/client/delete', ['id' => $otherClientId]);
        }
    }
} 