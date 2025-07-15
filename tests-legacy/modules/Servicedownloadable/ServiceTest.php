<?php

namespace Box\Mod\Servicedownloadable;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;
    protected $di;
    protected $tempDir;
    protected $originalUploadsPath;

    public function setUp(): void
    {
        $this->service = new Service();
        $this->di = new \Pimple\Container();
        
        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/fossbilling_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        
        // Store original PATH_UPLOADS if defined
        if (defined('PATH_UPLOADS')) {
            $this->originalUploadsPath = PATH_UPLOADS;
        }
        
        // Define PATH_UPLOADS for tests
        if (!defined('PATH_UPLOADS')) {
            define('PATH_UPLOADS', $this->tempDir . '/');
        }

        $this->setupMockDependencies();
    }

    public function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $filesystem = new Filesystem();
            $filesystem->remove($this->tempDir);
        }
        parent::tearDown();
    }

    private function setupMockDependencies(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        
        $loggerMock = $this->getMockBuilder('\Box_Log')->getMock();

        $this->di['validator'] = $validatorMock;
        $this->di['db'] = $dbMock;
        $this->di['logger'] = $loggerMock;
        
        $this->service->setDi($this->di);
    }

    public function testSetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $this->assertEquals($di, $this->service->getDi());
    }

    public function testAttachOrderConfigSuccess(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"filename": "test_file.txt"}';

        $data = ['additional_data' => 'value'];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['filename' => 'Product is not configured completely.'], ['filename' => 'test_file.txt']);

        $result = $this->service->attachOrderConfig($productModel, $data);
        
        $this->assertIsArray($result);
        $this->assertEquals('test_file.txt', $result['filename']);
        $this->assertEquals('value', $result['additional_data']);
    }

    public function testAttachOrderConfigMissingFilename(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{}';

        $data = [];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Product is not configured completely.'));

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product is not configured completely.');
        
        $this->service->attachOrderConfig($productModel, $data);
    }

    public function testAttachOrderConfigInvalidJson(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = 'invalid_json';

        $data = [];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['filename' => 'Product is not configured completely.'], []);

        $result = $this->service->attachOrderConfig($productModel, $data);
        $this->assertIsArray($result);
    }

    public function testValidateOrderDataSuccess(): void
    {
        $data = ['filename' => 'test_file.txt'];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['filename' => 'Filename is missing in product config'], $data);

        $this->service->validateOrderData($data);
    }

    public function testValidateOrderDataMissingFilename(): void
    {
        $data = [];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Filename is missing in product config'));

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Filename is missing in product config');
        
        $this->service->validateOrderData($data);
    }

    public function testActionCreate(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $clientOrder->id = 1;
        $clientOrder->client_id = 123;
        $clientOrder->config = '{"filename": "test_file.txt"}';

        $serviceModel = new \Model_ServiceDownloadable();
        $serviceModel->loadBean(new \DummyBean());

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray');

        $this->di['db']->expects($this->once())
            ->method('dispense')
            ->with('ServiceDownloadable')
            ->willReturn($serviceModel);

        $this->di['db']->expects($this->once())
            ->method('store')
            ->with($serviceModel)
            ->willReturn(1);

        $result = $this->service->action_create($clientOrder);

        $this->assertInstanceOf('\Model_ServiceDownloadable', $result);
        $this->assertEquals(123, $result->client_id);
        $this->assertEquals('test_file.txt', $result->filename);
        $this->assertEquals(0, $result->downloads);
    }

    public function testActionCreateMissingConfig(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $clientOrder->id = 1;
        $clientOrder->config = null;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order #1 config is missing');
        
        $this->service->action_create($clientOrder);
    }

    public function testActionCreateInvalidConfig(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $clientOrder->loadBean(new \DummyBean());
        $clientOrder->id = 1;
        $clientOrder->config = 'invalid_json';

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order #1 config is missing');
        
        $this->service->action_create($clientOrder);
    }

    public function testActionActivate(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $result = $this->service->action_activate($clientOrder);
        $this->assertTrue($result);
    }

    public function testActionRenew(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $result = $this->service->action_renew($clientOrder);
        $this->assertTrue($result);
    }

    public function testActionSuspend(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $result = $this->service->action_suspend($clientOrder);
        $this->assertTrue($result);
    }

    public function testActionUnsuspend(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $result = $this->service->action_unsuspend($clientOrder);
        $this->assertTrue($result);
    }

    public function testActionCancel(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $result = $this->service->action_cancel($clientOrder);
        $this->assertTrue($result);
    }

    public function testActionUncancel(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $result = $this->service->action_uncancel($clientOrder);
        $this->assertTrue($result);
    }

    public function testActionDelete(): void
    {
        $clientOrder = new \Model_ClientOrder();
        $serviceModel = new \Model_ServiceDownloadable();

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->once())
            ->method('getOrderService')
            ->with($clientOrder)
            ->willReturn($serviceModel);

        $this->di['mod_service'] = $this->di->protect(function () use ($orderServiceMock) {
            return $orderServiceMock;
        });

        $this->di['db']->expects($this->once())
            ->method('trash')
            ->with($serviceModel);

        $this->service->action_delete($clientOrder);
    }

    public function testActionDeleteNoService(): void
    {
        $clientOrder = new \Model_ClientOrder();

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->once())
            ->method('getOrderService')
            ->with($clientOrder)
            ->willReturn(null);

        $this->di['mod_service'] = $this->di->protect(function () use ($orderServiceMock) {
            return $orderServiceMock;
        });

        $this->di['db']->expects($this->never())
            ->method('trash');

        $this->service->action_delete($clientOrder);
    }

    public function testToApiArrayClient(): void
    {
        $serviceModel = new \Model_ServiceDownloadable();
        $serviceModel->loadBean(new \DummyBean());
        $serviceModel->filename = 'test_file.txt';
        $serviceModel->downloads = 5;

        $result = $this->service->toApiArray($serviceModel, false, null);

        $this->assertIsArray($result);
        $this->assertEquals(Path::normalize(PATH_UPLOADS . md5('test_file.txt')), $result['path']);
        $this->assertEquals('test_file.txt', $result['filename']);
        $this->assertArrayNotHasKey('downloads', $result);
    }

    public function testToApiArrayAdmin(): void
    {
        $serviceModel = new \Model_ServiceDownloadable();
        $serviceModel->loadBean(new \DummyBean());
        $serviceModel->filename = 'test_file.txt';
        $serviceModel->downloads = 5;

        $adminIdentity = new \Model_Admin();

        $result = $this->service->toApiArray($serviceModel, false, $adminIdentity);

        $this->assertIsArray($result);
        $this->assertEquals(Path::normalize(PATH_UPLOADS . md5('test_file.txt')), $result['path']);
        $this->assertEquals('test_file.txt', $result['filename']);
        $this->assertEquals(5, $result['downloads']);
    }

    public function testSendFileSuccess(): void
    {
        // Create a test file
        $filename = 'test_download.txt';
        $content = 'Test file content';
        $filePath = PATH_UPLOADS . md5($filename);
        file_put_contents($filePath, $content);

        $serviceModel = new \Model_ServiceDownloadable();
        $serviceModel->loadBean(new \DummyBean());
        $serviceModel->filename = $filename;
        $serviceModel->downloads = 0;

        $this->di['db']->expects($this->once())
            ->method('store')
            ->with($serviceModel);

        $this->di['logger']->expects($this->once())
            ->method('info');

        $result = $this->service->sendFile($serviceModel);
        $this->assertTrue($result);
        $this->assertEquals(1, $serviceModel->downloads);
    }

    public function testSendFileNotFound(): void
    {
        $serviceModel = new \Model_ServiceDownloadable();
        $serviceModel->loadBean(new \DummyBean());
        $serviceModel->filename = 'nonexistent_file.txt';

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('File cannot be downloaded at the moment. Please contact support.');
        
        $this->service->sendFile($serviceModel);
    }

    public function testSendProductFileSuccess(): void
    {
        // Create a test file
        $filename = 'test_product_file.txt';
        $content = 'Test product file content';
        $filePath = PATH_UPLOADS . md5($filename);
        file_put_contents($filePath, $content);

        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->id = 1;
        $productModel->config = json_encode(['filename' => $filename]);

        $this->di['logger']->expects($this->once())
            ->method('info')
            ->with('Downloaded product %s file by admin', 1);

        $result = $this->service->sendProductFile($productModel);
        $this->assertTrue($result);
    }

    public function testSendProductFileNoFile(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{}';

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('No file associated with this product');
        
        $this->service->sendProductFile($productModel);
    }

    public function testSendProductFileNotFound(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = json_encode(['filename' => 'nonexistent.txt']);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('File cannot be downloaded at the moment. Please contact support.');
        
        $this->service->sendProductFile($productModel);
    }

    public function testSaveProductConfig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = '{"existing": "value"}';

        $data = ['update_orders' => true];

        $this->di['db']->expects($this->once())
            ->method('store')
            ->with($productModel);

        $result = $this->service->saveProductConfig($productModel, $data);
        
        $config = json_decode($productModel->config, true);
        $this->assertTrue($config['update_orders']);
        $this->assertEquals('value', $config['existing']);
        $this->assertTrue($result);
    }

    public function testSaveProductConfigEmptyConfig(): void
    {
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
        $productModel->config = null;

        $data = ['update_orders' => false];

        $this->di['db']->expects($this->once())
            ->method('store')
            ->with($productModel);

        $result = $this->service->saveProductConfig($productModel, $data);
        
        $config = json_decode($productModel->config, true);
        $this->assertFalse($config['update_orders']);
        $this->assertTrue($result);
    }
}
