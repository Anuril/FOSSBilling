<?php

namespace Box\Mod\Servicedownloadable\Api;

class ClientTest extends \BBTestCase
{
    /**
     * @var Client
     */
    protected $api;
    protected $di;
    protected $tempDir;

    public function setUp(): void
    {
        $this->api = new Client();
        $this->di = new \Pimple\Container();
        
        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/fossbilling_client_test_' . uniqid();
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
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $this->di['db'] = $dbMock;
        $this->api->setDi($this->di);
    }

    public function testSetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $this->assertEquals($di, $this->api->getDi());
    }

    public function testSendFileMissingOrderId(): void
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order ID is required');
        
        $this->api->send_file($data);
    }

    public function testSendFileInvalidOrderId(): void
    {
        $data = ['order_id' => 'invalid'];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $this->api->setIdentity($client);

        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => 'invalid', ':client_id' => 123])
            ->willReturn(null);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        
        $this->api->send_file($data);
    }

    public function testSendFileOrderNotFound(): void
    {
        $data = ['order_id' => 999];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $this->api->setIdentity($client);

        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => 999, ':client_id' => 123])
            ->willReturn(null);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        
        $this->api->send_file($data);
    }

    public function testSendFileOrderBelongsToOtherClient(): void
    {
        $data = ['order_id' => 1];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $this->api->setIdentity($client);

        // Order exists but belongs to different client
        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => 1, ':client_id' => 123])
            ->willReturn(null);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        
        $this->api->send_file($data);
    }

    public function testSendFileOrderNotActivated(): void
    {
        $data = ['order_id' => 1];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 1;
        $order->status = 'pending'; // Not active

        $this->api->setIdentity($client);

        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => 1, ':client_id' => 123])
            ->willReturn($order);

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->once())
            ->method('getOrderService')
            ->with($order)
            ->willReturn(null); // Service not found

        $this->di['mod_service'] = $this->di->protect(function () use ($orderServiceMock) {
            return $orderServiceMock;
        });

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        
        $this->api->send_file($data);
    }

    public function testSendFileOrderInactiveStatus(): void
    {
        $data = ['order_id' => 1];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 1;
        $order->status = 'cancelled'; // Inactive status

        $serviceDownloadable = new \Model_ServiceDownloadable();
        $serviceDownloadable->loadBean(new \DummyBean());

        $this->api->setIdentity($client);

        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => 1, ':client_id' => 123])
            ->willReturn($order);

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->once())
            ->method('getOrderService')
            ->with($order)
            ->willReturn($serviceDownloadable);

        $this->di['mod_service'] = $this->di->protect(function () use ($orderServiceMock) {
            return $orderServiceMock;
        });

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        
        $this->api->send_file($data);
    }

    public function testSendFileWrongServiceType(): void
    {
        $data = ['order_id' => 1];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 1;
        $order->status = 'active';

        // Wrong service type (not ServiceDownloadable)
        $serviceOther = new \Model_ServiceHosting(); // Different service type

        $this->api->setIdentity($client);

        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => 1, ':client_id' => 123])
            ->willReturn($order);

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->once())
            ->method('getOrderService')
            ->with($order)
            ->willReturn($serviceOther);

        $this->di['mod_service'] = $this->di->protect(function () use ($orderServiceMock) {
            return $orderServiceMock;
        });

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        
        $this->api->send_file($data);
    }

    public function testSendFileSuccess(): void
    {
        $data = ['order_id' => 1];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 1;
        $order->status = 'active';

        $serviceDownloadable = new \Model_ServiceDownloadable();
        $serviceDownloadable->loadBean(new \DummyBean());
        $serviceDownloadable->filename = 'test_file.txt';

        $this->api->setIdentity($client);

        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => 1, ':client_id' => 123])
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
            ->method('sendFile')
            ->with($serviceDownloadable)
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $result = $this->api->send_file($data);
        $this->assertTrue($result);
    }

    public function testSendFileServiceThrowsException(): void
    {
        $data = ['order_id' => 1];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 1;
        $order->status = 'active';

        $serviceDownloadable = new \Model_ServiceDownloadable();
        $serviceDownloadable->loadBean(new \DummyBean());
        $serviceDownloadable->filename = 'nonexistent_file.txt';

        $this->api->setIdentity($client);

        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => 1, ':client_id' => 123])
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
            ->method('sendFile')
            ->with($serviceDownloadable)
            ->willThrowException(new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support.', null, 404));

        $this->api->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('File cannot be downloaded at the moment. Please contact support.');
        
        $this->api->send_file($data);
    }

    public function testSendFileWithValidFile(): void
    {
        // Create a test file
        $filename = 'client_test_file.txt';
        $content = 'Test file content for client download';
        $filePath = \Symfony\Component\Filesystem\Path::normalize(PATH_UPLOADS . md5($filename));
        file_put_contents($filePath, $content);

        $data = ['order_id' => 1];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $order = new \Model_ClientOrder();
        $order->loadBean(new \DummyBean());
        $order->id = 1;
        $order->status = 'active';

        $serviceDownloadable = new \Model_ServiceDownloadable();
        $serviceDownloadable->loadBean(new \DummyBean());
        $serviceDownloadable->filename = $filename;
        $serviceDownloadable->downloads = 5;

        $this->api->setIdentity($client);

        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => 1, ':client_id' => 123])
            ->willReturn($order);

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->once())
            ->method('getOrderService')
            ->with($order)
            ->willReturn($serviceDownloadable);

        $this->di['mod_service'] = $this->di->protect(function () use ($orderServiceMock) {
            return $orderServiceMock;
        });

        // Use real service for this test
        $realService = new \Box\Mod\Servicedownloadable\Service();
        $realService->setDi($this->di);
        $this->api->setService($realService);

        // Mock database store for download count
        $this->di['db']->expects($this->once())
            ->method('store')
            ->with($serviceDownloadable);

        // Mock logger
        $loggerMock = $this->getMockBuilder('\Box_Log')->getMock();
        $this->di['logger'] = $loggerMock;

        $result = $this->api->send_file($data);
        $this->assertTrue($result);
        
        // Verify download count was incremented
        $this->assertEquals(6, $serviceDownloadable->downloads);
    }

    public function testSendFileClientIdentityNotSet(): void
    {
        $data = ['order_id' => 1];

        // Don't set client identity
        $this->expectException(\Exception::class); // This will likely cause a different exception when getIdentity is called
        
        $this->api->send_file($data);
    }

    /**
     * Test data validation edge cases
     */
    public function testSendFileNullOrderId(): void
    {
        $data = ['order_id' => null];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order ID is required');
        
        $this->api->send_file($data);
    }

    public function testSendFileEmptyOrderId(): void
    {
        $data = ['order_id' => ''];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $this->api->setIdentity($client);

        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => '', ':client_id' => 123])
            ->willReturn(null);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        
        $this->api->send_file($data);
    }

    public function testSendFileZeroOrderId(): void
    {
        $data = ['order_id' => 0];

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 123;

        $this->api->setIdentity($client);

        $this->di['db']->expects($this->once())
            ->method('findOne')
            ->with('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => 0, ':client_id' => 123])
            ->willReturn(null);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        
        $this->api->send_file($data);
    }
}
