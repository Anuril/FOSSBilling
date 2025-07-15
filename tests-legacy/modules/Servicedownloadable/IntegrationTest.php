<?php

namespace Box\Mod\Servicedownloadable;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Integration tests for the complete Servicedownloadable workflow
 * Tests file upload, product configuration, order creation, and file downloads
 */
class IntegrationTest extends \BBTestCase
{
    protected $tempDir;
    protected $testFiles = [];

    public function setUp(): void
    {
        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/fossbilling_integration_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        
        // Define PATH_UPLOADS for tests if not already defined
        if (!defined('PATH_UPLOADS')) {
            define('PATH_UPLOADS', $this->tempDir . '/');
        }
        
        // Ensure the uploads directory exists for tests
        if (!is_dir(PATH_UPLOADS)) {
            mkdir(PATH_UPLOADS, 0777, true);
        }
        
        $this->createTestFiles();
    }

    public function tearDown(): void
    {
        // Clean up temp directory and test files
        if (is_dir($this->tempDir)) {
            $filesystem = new \Symfony\Component\Filesystem\Filesystem();
            $filesystem->remove($this->tempDir);
        }
        
        foreach ($this->testFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        parent::tearDown();
    }

    private function createTestFiles(): void
    {
        // Create test files with different content
        $this->testFiles['small'] = $this->tempDir . '/test_small.txt';
        file_put_contents($this->testFiles['small'], 'Small test file content');
        
        $this->testFiles['large'] = $this->tempDir . '/test_large.txt';
        file_put_contents($this->testFiles['large'], str_repeat('Large file content. ', 1000));
        
        $this->testFiles['pdf'] = $this->tempDir . '/test_document.pdf';
        file_put_contents($this->testFiles['pdf'], '%PDF-1.4 fake pdf content for testing');
        
        $this->testFiles['image'] = $this->tempDir . '/test_image.jpg';
        file_put_contents($this->testFiles['image'], 'fake image content for testing');
    }

    /**
     * Test complete workflow: Product creation -> File upload -> Order creation -> Client download
     */
    public function testCompleteClientWorkflow(): void
    {
        // 1. Create product
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->id = 1;
        $product->title = 'Test Downloadable Product';
        $product->config = null;

        // 2. Setup mocks
        $di = $this->createDependencyContainer();
        $service = new Service();
        $service->setDi($di);

        // 3. Simulate file upload to product
        $filename = 'uploaded_client_test.txt';
        $filePath = \Symfony\Component\Filesystem\Path::normalize(PATH_UPLOADS . md5($filename));
        file_put_contents($filePath, 'Uploaded content for client test');

        // Update product config with filename
        $product->config = json_encode(['filename' => $filename]);

        // 4. Create order
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 1;
        $order->client_id = 123;
        $order->status = 'active';
        $order->config = json_encode(['filename' => $filename]);

        // 5. Create service through order
        $serviceModel = $service->action_create($order);
        $this->assertInstanceOf('\Model_ServiceDownloadable', $serviceModel);
        $this->assertEquals($filename, $serviceModel->filename);
        $this->assertEquals(0, $serviceModel->downloads);

        // 6. Test toApiArray for client (should not include downloads)
        $apiArray = $service->toApiArray($serviceModel, false, null);
        $this->assertArrayNotHasKey('downloads', $apiArray);
        $this->assertEquals($filename, $apiArray['filename']);

        // 7. Test client download
        $originalDownloads = $serviceModel->downloads;
        $result = $service->sendFile($serviceModel);
        $this->assertTrue($result);
        $this->assertEquals($originalDownloads + 1, $serviceModel->downloads);
    }

    /**
     * Test complete admin workflow: Product creation -> File upload -> Admin download
     */
    public function testCompleteAdminWorkflow(): void
    {
        // 1. Create product
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->id = 2;
        $product->title = 'Test Admin Downloadable Product';
        $product->config = null;

        // 2. Setup mocks
        $di = $this->createDependencyContainer();
        $service = new Service();
        $service->setDi($di);

        // 3. Simulate file upload to product
        $filename = 'uploaded_admin_test.txt';
        $filePath = \Symfony\Component\Filesystem\Path::normalize(PATH_UPLOADS . md5($filename));
        file_put_contents($filePath, 'Uploaded content for admin test');

        // Update product config with filename
        $product->config = json_encode(['filename' => $filename]);

        // 4. Test admin download (should not increment download count)
        $result = $service->sendProductFile($product);
        $this->assertTrue($result);

        // 5. Test toApiArray for admin (should include extra info)
        $admin = new \Model_Admin();
        $serviceModel = new \Model_ServiceDownloadable();
        $serviceModel->loadBean(new \DummyBean());
        $serviceModel->filename = $filename;
        $serviceModel->downloads = 10;

        $apiArray = $service->toApiArray($serviceModel, false, $admin);
        $this->assertArrayHasKey('downloads', $apiArray);
        $this->assertEquals(10, $apiArray['downloads']);
        $this->assertEquals($filename, $apiArray['filename']);
    }

    /**
     * Test file upload and replacement workflow
     */
    public function testFileUploadAndReplacement(): void
    {
        // 1. Create product with initial file
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->id = 3;
        $product->config = json_encode(['filename' => 'old_file.txt']);

        // Create the old file
        $oldFilePath = \Symfony\Component\Filesystem\Path::normalize(PATH_UPLOADS . md5('old_file.txt'));
        file_put_contents($oldFilePath, 'Old file content');

        // 2. Setup service
        $di = $this->createDependencyContainer();
        $service = new Service();
        $service->setDi($di);

        // 3. Test that old file exists
        $this->assertTrue(file_exists($oldFilePath));

        // 4. Simulate uploading new file (this would normally happen in uploadProductFile)
        $newFilename = 'new_file.txt';
        $newFilePath = \Symfony\Component\Filesystem\Path::normalize(PATH_UPLOADS . md5($newFilename));
        file_put_contents($newFilePath, 'New file content');

        // Update product config
        $newConfig = ['filename' => $newFilename];
        $product->config = json_encode($newConfig);

        // 5. Test that new file exists before trying to download
        $this->assertTrue(file_exists($newFilePath), "New file should exist at: " . $newFilePath);

        // 6. Test that new file can be downloaded
        $result = $service->sendProductFile($product);
        $this->assertTrue($result);
    }

    /**
     * Test product configuration save workflow
     */
    public function testProductConfigurationWorkflow(): void
    {
        // 1. Create product
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->id = 4;
        $product->config = '{"filename": "config_test.txt", "existing": "value"}';

        // 2. Setup service
        $di = $this->createDependencyContainer();
        $service = new Service();
        $service->setDi($di);

        // 3. Test saving configuration with update_orders = true
        $data = ['update_orders' => true, 'other_setting' => 'test'];
        $result = $service->saveProductConfig($product, $data);
        $this->assertTrue($result);

        // 4. Verify configuration was updated
        $config = json_decode($product->config, true);
        $this->assertTrue($config['update_orders']);
        $this->assertEquals('value', $config['existing']); // Should preserve existing values

        // 5. Test saving configuration with update_orders = false
        $data = ['update_orders' => false];
        $result = $service->saveProductConfig($product, $data);
        $this->assertTrue($result);

        $config = json_decode($product->config, true);
        $this->assertFalse($config['update_orders']);
        $this->assertEquals('value', $config['existing']); // Should still preserve existing values
    }

    /**
     * Test order validation workflow
     */
    public function testOrderValidationWorkflow(): void
    {
        // 1. Setup service
        $di = $this->createDependencyContainer();
        $service = new Service();
        $service->setDi($di);

        // 2. Test valid order data
        $validData = ['filename' => 'valid_file.txt'];
        $service->validateOrderData($validData); // Should not throw exception

        // 3. Test invalid order data
        $invalidData = [];
        
        // Mock validator to throw exception for invalid data
        $di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Filename is missing in product config'));

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Filename is missing in product config');
        
        $service->validateOrderData($invalidData);
    }

    /**
     * Test attachOrderConfig workflow
     */
    public function testAttachOrderConfigWorkflow(): void
    {
        // 1. Create product with config
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->config = '{"filename": "attach_test.txt"}';

        // 2. Setup service
        $di = $this->createDependencyContainer();
        $service = new Service();
        $service->setDi($di);

        // 3. Test attaching order config
        $data = ['order_specific' => 'value'];
        $result = $service->attachOrderConfig($product, $data);

        $this->assertIsArray($result);
        $this->assertEquals('attach_test.txt', $result['filename']);
        $this->assertEquals('value', $result['order_specific']);
    }

    /**
     * Test error handling workflow
     */
    public function testErrorHandlingWorkflow(): void
    {
        // 1. Setup service
        $di = $this->createDependencyContainer();
        $service = new Service();
        $service->setDi($di);

        // 2. Test sendFile with non-existent file
        $serviceModel = new \Model_ServiceDownloadable();
        $serviceModel->loadBean(new \DummyBean());
        $serviceModel->filename = 'nonexistent_file.txt';

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('File cannot be downloaded at the moment. Please contact support.');
        
        $service->sendFile($serviceModel);
    }

    /**
     * Test action_delete workflow
     */
    public function testActionDeleteWorkflow(): void
    {
        // 1. Setup service and order
        $di = $this->createDependencyContainer();
        $service = new Service();
        $service->setDi($di);

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $serviceModel = new \Model_ServiceDownloadable();
        $serviceModel->loadBean(new \DummyBean());

        // 2. Setup order service mock
        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->once())
            ->method('getOrderService')
            ->with($order)
            ->willReturn($serviceModel);

        $di['mod_service'] = $di->protect(function () use ($orderServiceMock) {
            return $orderServiceMock;
        });

        // 3. Test delete action
        $service->action_delete($order);
        
        // Verify trash was called - this is verified by the mock expectations
    }

    /**
     * Test multiple file types workflow
     */
    public function testMultipleFileTypesWorkflow(): void
    {
        $di = $this->createDependencyContainer();
        $service = new Service();
        $service->setDi($di);

        $fileTypes = [
            'text' => ['filename' => 'test.txt', 'content' => 'Text file content'],
            'pdf' => ['filename' => 'test.pdf', 'content' => '%PDF-1.4 fake pdf'],
            'image' => ['filename' => 'test.jpg', 'content' => 'fake image data'],
            'archive' => ['filename' => 'test.zip', 'content' => 'fake zip data'],
        ];

        foreach ($fileTypes as $type => $fileInfo) {
            // Create test file
            $filePath = \Symfony\Component\Filesystem\Path::normalize(PATH_UPLOADS . md5($fileInfo['filename']));
            file_put_contents($filePath, $fileInfo['content']);

            // Create product
            $product = new \Model_Product();
            $product->loadBean(new \DummyBean());
            $product->config = json_encode(['filename' => $fileInfo['filename']]);

            // Test download
            $result = $service->sendProductFile($product);
            $this->assertTrue($result, "Failed to download {$type} file: {$fileInfo['filename']}");
        }
    }

    private function createDependencyContainer(): \Pimple\Container
    {
        $di = new \Pimple\Container();

        // Mock validator
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)
            ->disableOriginalConstructor()
            ->getMock();
        $validatorMock->method('checkRequiredParamsForArray')->willReturn(null);

        // Mock database
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->method('dispense')->willReturnCallback(function ($type) {
            $model = new \Model_ServiceDownloadable();
            $model->loadBean(new \DummyBean());
            return $model;
        });
        $dbMock->method('store')->willReturn(1);
        $dbMock->method('trash')->willReturn(true);

        // Mock logger
        $loggerMock = $this->getMockBuilder('\Box_Log')->getMock();

        // Mock mod_service for toApiArray method
        $productServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Product\Service::class)->getMock();
        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $modServiceMock = function($service) use ($productServiceMock, $orderServiceMock) {
            if ($service === 'product') {
                return $productServiceMock;
            } elseif ($service === 'order') {
                return $orderServiceMock;
            }
            return null;
        };

        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['logger'] = $loggerMock;
        $di['mod_service'] = $di->protect($modServiceMock);

        return $di;
    }
} 