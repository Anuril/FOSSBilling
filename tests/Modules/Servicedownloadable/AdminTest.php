<?php

declare(strict_types=1);

namespace ServicedownloadableTests;

use APIHelper\Request;
use PHPUnit\Framework\TestCase;

final class AdminTest extends TestCase
{
    private static int $productId = 0;
    private static string $testFileName = 'test_download_file.txt';
    private static string $testFileContent = 'This is a test file for downloadable service testing.';

    public static function setUpBeforeClass(): void
    {
        // Create a test product for our tests
        $result = Request::makeRequest('admin/product/prepare');
        self::assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        
        $productData = $result->getResult();
        $productData['title'] = 'Test Downloadable Product';
        $productData['type'] = 'downloadable';
        $productData['slug'] = 'test-downloadable-product';
        
        $result = Request::makeRequest('admin/product/create', $productData);
        self::assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        self::$productId = (int) $result->getResult();

        // Create a test file for uploading
        $testFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::$testFileName;
        file_put_contents($testFilePath, self::$testFileContent);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up the test product
        if (self::$productId > 0) {
            Request::makeRequest('admin/product/delete', ['id' => self::$productId]);
        }

        // Clean up test file
        $testFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::$testFileName;
        if (file_exists($testFilePath)) {
            unlink($testFilePath);
        }
    }

    public function testConfigSave(): void
    {
        // Test saving product configuration
        $result = Request::makeRequest('admin/servicedownloadable/config_save', [
            'id' => self::$productId,
            'update_orders' => true,
        ]);

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertTrue($result->getResult());
    }

    public function testConfigSaveMissingProductId(): void
    {
        // Test config save without product ID
        $result = Request::makeRequest('admin/servicedownloadable/config_save', [
            'update_orders' => true,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Product ID is missing', $result->getErrorMessage());
    }

    public function testConfigSaveInvalidProductId(): void
    {
        // Test config save with invalid product ID
        $result = Request::makeRequest('admin/servicedownloadable/config_save', [
            'id' => 99999,
            'update_orders' => true,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Product not found', $result->getErrorMessage());
    }

    public function testUploadFileToProduct(): void
    {
        // Note: File upload testing via API is complex and would require multipart/form-data
        // This test validates the API endpoint exists and handles missing file gracefully
        $result = Request::makeRequest('admin/servicedownloadable/upload', [
            'id' => self::$productId,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('File was not uploaded.', $result->getErrorMessage());
    }

    public function testUploadMissingProductId(): void
    {
        $result = Request::makeRequest('admin/servicedownloadable/upload', []);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Product ID is missing', $result->getErrorMessage());
    }

    public function testUploadInvalidProductId(): void
    {
        $result = Request::makeRequest('admin/servicedownloadable/upload', [
            'id' => 99999,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Product not found', $result->getErrorMessage());
    }

    public function testSendFileWithoutUploadedFile(): void
    {
        // Test sending file when no file has been uploaded
        $result = Request::makeRequest('admin/servicedownloadable/send_file', [
            'id' => self::$productId,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('No file associated with this product', $result->getErrorMessage());
    }

    public function testSendFileMissingProductId(): void
    {
        $result = Request::makeRequest('admin/servicedownloadable/send_file', []);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Product ID is missing', $result->getErrorMessage());
    }

    public function testSendFileInvalidProductId(): void
    {
        $result = Request::makeRequest('admin/servicedownloadable/send_file', [
            'id' => 99999,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Product not found', $result->getErrorMessage());
    }

    public function testGetDownloadLinksWithoutFile(): void
    {
        // Test getting download links when no file has been uploaded
        $result = Request::makeRequest('admin/servicedownloadable/get_download_links', [
            'id' => self::$productId,
        ]);

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
        $this->assertEmpty($result->getResult());
    }

    public function testGetDownloadLinksMissingProductId(): void
    {
        $result = Request::makeRequest('admin/servicedownloadable/get_download_links', []);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Product ID is missing', $result->getErrorMessage());
    }

    public function testGetDownloadLinksInvalidProductId(): void
    {
        $result = Request::makeRequest('admin/servicedownloadable/get_download_links', [
            'id' => 99999,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Product not found', $result->getErrorMessage());
    }

    public function testUpdateOrderWithoutOrderId(): void
    {
        $result = Request::makeRequest('admin/servicedownloadable/update', []);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Order ID is missing', $result->getErrorMessage());
    }

    public function testUpdateOrderInvalidOrderId(): void
    {
        $result = Request::makeRequest('admin/servicedownloadable/update', [
            'order_id' => 99999,
        ]);

        $this->assertFalse($result->wasSuccessful());
        $this->assertEquals('Order not found', $result->getErrorMessage());
    }

    /**
     * This test simulates the scenario where a product has a file uploaded,
     * which would be tested in integration tests with actual file uploads.
     * Here we're testing the API structure and validation.
     */
    public function testGetDownloadLinksStructure(): void
    {
        // First, we need to manually set a filename in the product config to simulate an uploaded file
        $product = Request::makeRequest('admin/product/get', ['id' => self::$productId]);
        $this->assertTrue($product->wasSuccessful(), $product->generatePHPUnitMessage());
        
        $productData = $product->getResult();
        
        // Simulate having a file by updating the product config
        $updateResult = Request::makeRequest('admin/product/update', [
            'id' => self::$productId,
            'title' => $productData['title'],
            'type' => $productData['type'],
            'config' => json_encode(['filename' => self::$testFileName]),
        ]);
        $this->assertTrue($updateResult->wasSuccessful(), $updateResult->generatePHPUnitMessage());

        // Now test get_download_links with a simulated file
        $result = Request::makeRequest('admin/servicedownloadable/get_download_links', [
            'id' => self::$productId,
        ]);

        $this->assertTrue($result->wasSuccessful(), $result->generatePHPUnitMessage());
        $this->assertIsArray($result->getResult());
        $this->assertNotEmpty($result->getResult());
        
        $downloadInfo = $result->getResult()[0];
        $this->assertArrayHasKey('filename', $downloadInfo);
        $this->assertArrayHasKey('download_url', $downloadInfo);
        $this->assertArrayHasKey('direct_path', $downloadInfo);
        $this->assertEquals(self::$testFileName, $downloadInfo['filename']);
        $this->assertStringContains('servicedownloadable/get-file', $downloadInfo['download_url']);
    }
} 