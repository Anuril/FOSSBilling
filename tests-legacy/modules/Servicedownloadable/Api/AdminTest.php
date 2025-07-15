<?php

namespace Box\Mod\Servicedownloadable\Api;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Request;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $api;
    protected $di;
    protected $tempDir;

    public function setUp(): void
    {
        $this->api = new Admin();
        $this->di = new \Pimple\Container();
        
        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/fossbilling_admin_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        
        // Define PATH_UPLOADS for tests if not already defined
        if (!defined('PATH_UPLOADS')) {
            define('PATH_UPLOADS', $this->tempDir . '/');
        }

        $this->setupMockDependencies();
    }

    public function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $filesystem = new \Symfony\Component\Filesystem\Filesystem();
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
        
        $urlMock = $this->getMockBuilder('\Box_Url')->getMock();

        $this->di['validator'] = $validatorMock;
        $this->di['db'] = $dbMock;
        $this->di['url'] = $urlMock;
        
        $this->api->setDi($this->di);
    }

    public function testSetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $this->assertEquals($di, $this->api->getDi());
    }

    public function testUploadMissingProductId(): void
    {
        $data = [];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['id' => 'Product ID is missing'], $data)
            ->willThrowException(new \FOSSBilling\Exception('Product ID is missing'));

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product ID is missing');
        
        $this->api->upload($data);
    }

    public function testUploadProductNotFound(): void
    {
        $data = ['id' => 999];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['id' => 'Product ID is missing'], $data);

        $this->di['db']->expects($this->once())
            ->method('getExistingModelById')
            ->with('Product', 999, 'Product not found')
            ->willThrowException(new \FOSSBilling\Exception('Product not found'));

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product not found');
        
        $this->api->upload($data);
    }

    public function testUploadNoFileUploaded(): void
    {
        $data = ['id' => 1];
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['id' => 'Product ID is missing'], $data);

        $this->di['db']->expects($this->once())
            ->method('getExistingModelById')
            ->with('Product', 1, 'Product not found')
            ->willReturn($product);

        // Mock request with no files
        $requestMock = $this->getMockBuilder(Request::class)->getMock();
        $requestMock->files = new FileBag();
        
        $this->di['request'] = $requestMock;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('File was not uploaded.');
        
        $this->api->upload($data);
    }

    public function testUploadSuccess(): void
    {
        $data = ['id' => 1];
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['id' => 'Product ID is missing'], $data);

        $this->di['db']->expects($this->once())
            ->method('getExistingModelById')
            ->with('Product', 1, 'Product not found')
            ->willReturn($product);

        // Mock uploaded file
        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $fileBag = new FileBag(['file_data' => $uploadedFile]);
        $requestMock = $this->getMockBuilder(Request::class)->getMock();
        $requestMock->files = $fileBag;
        
        $this->di['request'] = $requestMock;

        // Mock service
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedownloadable\Service::class)->getMock();
        $serviceMock->expects($this->once())
            ->method('uploadProductFile')
            ->with($product)
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->upload($data);
        $this->assertTrue($result);
    }

    public function testUpdateMissingOrderId(): void
    {
        $data = [];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['order_id' => 'Order ID is missing'], $data)
            ->willThrowException(new \FOSSBilling\Exception('Order ID is missing'));

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order ID is missing');
        
        $this->api->update($data);
    }

    public function testUpdateOrderNotFound(): void
    {
        $data = ['order_id' => 999];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['order_id' => 'Order ID is missing'], $data);

        $this->di['db']->expects($this->once())
            ->method('getExistingModelById')
            ->with('ClientOrder', 999, 'Order not found')
            ->willThrowException(new \FOSSBilling\Exception('Order not found'));

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        
        $this->api->update($data);
    }

    public function testUpdateOrderNotActivated(): void
    {
        $data = ['order_id' => 1];
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['order_id' => 'Order ID is missing'], $data);

        $this->di['db']->expects($this->once())
            ->method('getExistingModelById')
            ->with('ClientOrder', 1, 'Order not found')
            ->willReturn($order);

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->once())
            ->method('getOrderService')
            ->with($order)
            ->willReturn(null); // Not activated

        $this->di['mod_service'] = $this->di->protect(function () use ($orderServiceMock) {
            return $orderServiceMock;
        });

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        
        $this->api->update($data);
    }

    public function testUpdateSuccess(): void
    {
        $data = ['order_id' => 1];
        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        
        $serviceDownloadable = new \Model_ServiceDownloadable();
        $serviceDownloadable->loadBean(new \DummyBean());

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['order_id' => 'Order ID is missing'], $data);

        $this->di['db']->expects($this->once())
            ->method('getExistingModelById')
            ->with('ClientOrder', 1, 'Order not found')
            ->willReturn($order);

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->once())
            ->method('getOrderService')
            ->with($order)
            ->willReturn($serviceDownloadable);

        $this->di['mod_service'] = $this->di->protect(function () use ($orderServiceMock) {
            return $orderServiceMock;
        });

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedownloadable\Service::class)->getMock();
        $serviceMock->expects($this->once())
            ->method('updateProductFile')
            ->with($serviceDownloadable, $order)
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->update($data);
        $this->assertTrue($result);
    }

    public function testConfigSaveMissingProductId(): void
    {
        $data = [];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['id' => 'Product ID is missing'], $data)
            ->willThrowException(new \FOSSBilling\Exception('Product ID is missing'));

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product ID is missing');
        
        $this->api->config_save($data);
    }

    public function testConfigSaveSuccess(): void
    {
        $data = ['id' => 1, 'update_orders' => true];
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['id' => 'Product ID is missing'], $data);

        $this->di['db']->expects($this->once())
            ->method('getExistingModelById')
            ->with('Product', 1, 'Product not found')
            ->willReturn($product);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedownloadable\Service::class)->getMock();
        $serviceMock->expects($this->once())
            ->method('saveProductConfig')
            ->with($product, $data)
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->config_save($data);
        $this->assertTrue($result);
    }



    public function testSendFileMissingProductId(): void
    {
        $data = [];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['id' => 'Product ID is missing'], $data)
            ->willThrowException(new \FOSSBilling\Exception('Product ID is missing'));

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product ID is missing');
        
        $this->api->send_file($data);
    }

    public function testSendFileProductNotFound(): void
    {
        $data = ['id' => 999];

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['id' => 'Product ID is missing'], $data);

        $this->di['db']->expects($this->once())
            ->method('getExistingModelById')
            ->with('Product', 999, 'Product not found')
            ->willThrowException(new \FOSSBilling\Exception('Product not found'));

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product not found');
        
        $this->api->send_file($data);
    }

    public function testSendFileSuccess(): void
    {
        $data = ['id' => 1];
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->id = 1;
        $product->config = '{"filename": "test_file.txt"}';

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['id' => 'Product ID is missing'], $data);

        $this->di['db']->expects($this->once())
            ->method('getExistingModelById')
            ->with('Product', 1, 'Product not found')
            ->willReturn($product);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedownloadable\Service::class)->getMock();
        $serviceMock->expects($this->once())
            ->method('sendProductFile')
            ->with($product)
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->send_file($data);
        $this->assertTrue($result);
    }

    public function testSendFileServiceThrowsException(): void
    {
        $data = ['id' => 1];
        $product = new \Model_Product();
        $product->loadBean(new \DummyBean());
        $product->id = 1;
        $product->config = '{}'; // No filename

        $this->di['validator']->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['id' => 'Product ID is missing'], $data);

        $this->di['db']->expects($this->once())
            ->method('getExistingModelById')
            ->with('Product', 1, 'Product not found')
            ->willReturn($product);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedownloadable\Service::class)->getMock();
        $serviceMock->expects($this->once())
            ->method('sendProductFile')
            ->with($product)
            ->willThrowException(new \FOSSBilling\Exception('No file associated with this product'));

        $this->api->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('No file associated with this product');
        
        $this->api->send_file($data);
    }
}
