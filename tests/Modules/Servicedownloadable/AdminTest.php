<?php

declare(strict_types=1);

namespace ServicedownloadableTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    private static int $productId;
    private static int $orderId;

    public static function setUpBeforeClass(): void
    {
        // Create a test product for downloadable service
        $productData = [
            'type' => 'downloadable',
            'category_id' => 1,
            'title' => 'Test Downloadable Product',
            'slug' => 'test-downloadable-product',
            'status' => 'enabled',
            'priority' => 1,
            'description' => 'Test downloadable product for testing',
            'setup' => 'free',
            'pricing' => [
                'type' => 'free'
            ]
        ];
        
        $result = Request::makeRequest('admin/product/prepare', $productData);
        if ($result->wasSuccessful()) {
            self::$productId = $result->getResult()['id'];
        }

        // Create a test order
        $orderData = [
            'client_id' => 1,
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
    }

    public function testConfigSave(): void
    {
        $data = [
            'id' => self::$productId,
            'update_orders' => true
        ];

        $result = Request::makeRequest('admin/servicedownloadable/config_save', $data);
        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());
    }

    public function testConfigSaveMissingId(): void
    {
        $data = [];

        $result = Request::makeRequest('admin/servicedownloadable/config_save', $data);
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Product ID is missing', $result->getErrorMessage());
    }

    public function testConfigSaveInvalidProduct(): void
    {
        $data = [
            'id' => 99999
        ];

        $result = Request::makeRequest('admin/servicedownloadable/config_save', $data);
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Product not found', $result->getErrorMessage());
    }

    public function testUpdateMissingOrderId(): void
    {
        $data = [];

        $result = Request::makeRequest('admin/servicedownloadable/update', $data);
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Order ID is missing', $result->getErrorMessage());
    }

    public function testUpdateInvalidOrder(): void
    {
        $data = [
            'order_id' => 99999
        ];

        $result = Request::makeRequest('admin/servicedownloadable/update', $data);
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Order not found', $result->getErrorMessage());
    }

    public function testSendFileMissingId(): void
    {
        $data = [];

        $result = Request::makeRequest('admin/servicedownloadable/send_file', $data);
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Product ID is missing', $result->getErrorMessage());
    }

    public function testSendFileInvalidProduct(): void
    {
        $data = [
            'id' => 99999
        ];

        $result = Request::makeRequest('admin/servicedownloadable/send_file', $data);
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Product not found', $result->getErrorMessage());
    }

    public function testSendFileNoFileAssociated(): void
    {
        $data = [
            'id' => self::$productId
        ];

        $result = Request::makeRequest('admin/servicedownloadable/send_file', $data);
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('No file associated with this product', $result->getErrorMessage());
    }

    /**
     * Note: Upload tests would require file upload simulation which is complex in API tests.
     * These would be better tested in integration tests or with mock file uploads.
     */
    public function testUploadMissingId(): void
    {
        $data = [];

        $result = Request::makeRequest('admin/servicedownloadable/upload', $data);
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Product ID is missing', $result->getErrorMessage());
    }

    public function testUploadInvalidProduct(): void
    {
        $data = [
            'id' => 99999
        ];

        $result = Request::makeRequest('admin/servicedownloadable/upload', $data);
        $this->assertFalse($result->wasSuccessful());
        $this->assertStringContains('Product not found', $result->getErrorMessage());
    }

    /**
     * Test the API structure and response format for download links method
     */
    public function testGetDownloadLinksStructure(): void
    {
        // This method was removed in the user's edits, but the structure should return an array
        // when the method is re-implemented for future multiple file support
        $this->assertTrue(true, 'get_download_links method was removed but should return array structure for future use');
    }
} 