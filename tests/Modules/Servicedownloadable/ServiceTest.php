<?php

declare(strict_types=1);

namespace ServicedownloadableTests;

use Box\Mod\Servicedownloadable\Service;
use FOSSBilling\Environment;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;

final class ServiceTest extends TestCase
{
    private Service $service;
    private MockObject $di;
    private MockObject $db;
    private MockObject $validator;
    private MockObject $logger;

    protected function setUp(): void
    {
        $this->service = new Service();
        
        // Create mock DI container
        $this->di = $this->createMock(\Pimple\Container::class);
        $this->db = $this->createMock(\Box_Database::class);
        $this->validator = $this->createMock(\FOSSBilling\Validate::class);
        $this->logger = $this->createMock(\Box_Log::class);
        
        $this->service->setDi($this->di);
    }

    public function testSetAndGetDi(): void
    {
        $di = $this->createMock(\Pimple\Container::class);
        $this->service->setDi($di);
        $this->assertSame($di, $this->service->getDi());
    }

    public function testAttachOrderConfig(): void
    {
        $product = $this->createMock(\Model_Product::class);
        $product->config = '{"filename": "test.txt", "update_orders": true}';
        
        $this->validator
            ->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['filename' => 'Product is not configured completely.']);

        $this->di
            ->expects($this->once())
            ->method('offsetGet')
            ->with('validator')
            ->willReturn($this->validator);

        $data = ['additional' => 'data'];
        $result = $this->service->attachOrderConfig($product, $data);

        $expected = [
            'filename' => 'test.txt',
            'update_orders' => true,
            'additional' => 'data'
        ];

        $this->assertEquals($expected, $result);
        $this->assertEquals('test.txt', $data['filename']);
    }

    public function testAttachOrderConfigMissingConfig(): void
    {
        $product = $this->createMock(\Model_Product::class);
        $product->config = null;
        
        $this->validator
            ->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->willThrowException(new \FOSSBilling\Exception('Product is not configured completely.'));

        $this->di
            ->expects($this->once())
            ->method('offsetGet')
            ->with('validator')
            ->willReturn($this->validator);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product is not configured completely.');

        $data = [];
        $this->service->attachOrderConfig($product, $data);
    }

    public function testValidateOrderData(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('checkRequiredParamsForArray')
            ->with(['filename' => 'Filename is missing in product config']);

        $this->di
            ->expects($this->once())
            ->method('offsetGet')
            ->with('validator')
            ->willReturn($this->validator);

        $data = ['filename' => 'test.txt'];
        $this->service->validateOrderData($data);
        
        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testActionCreate(): void
    {
        $order = $this->createMock(\Model_ClientOrder::class);
        $order->config = '{"filename": "test.txt"}';
        $order->client_id = 1;
        $order->id = 1;

        $serviceModel = $this->createMock(\Model_ServiceDownloadable::class);

        $this->validator
            ->expects($this->once())
            ->method('checkRequiredParamsForArray');

        $this->db
            ->expects($this->once())
            ->method('dispense')
            ->with('ServiceDownloadable')
            ->willReturn($serviceModel);

        $this->db
            ->expects($this->once())
            ->method('store')
            ->with($serviceModel)
            ->willReturn(1);

        $this->di
            ->method('offsetGet')
            ->willReturnMap([
                ['validator', $this->validator],
                ['db', $this->db]
            ]);

        $result = $this->service->action_create($order);
        $this->assertInstanceOf(\Model_ServiceDownloadable::class, $result);
    }

    public function testActionCreateInvalidConfig(): void
    {
        $order = $this->createMock(\Model_ClientOrder::class);
        $order->config = 'invalid json';
        $order->id = 1;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order #1 config is missing');

        $this->service->action_create($order);
    }

    public function testActionDelete(): void
    {
        $order = $this->createMock(\Model_ClientOrder::class);
        $serviceModel = $this->createMock(\Model_ServiceDownloadable::class);
        
        $orderService = $this->createMock(\Box\Mod\Order\Service::class);
        $orderService
            ->expects($this->once())
            ->method('getOrderService')
            ->with($order)
            ->willReturn($serviceModel);

        $this->db
            ->expects($this->once())
            ->method('trash')
            ->with($serviceModel);

        $this->di
            ->method('offsetGet')
            ->willReturnMap([
                ['mod_service', function() use ($orderService) { return $orderService; }],
                ['db', $this->db]
            ]);

        $this->service->action_delete($order);
        
        // If no exception is thrown, the test passes
        $this->assertTrue(true);
    }

    public function testToApiArray(): void
    {
        $model = $this->createMock(\Model_ServiceDownloadable::class);
        $model->filename = 'test.txt';
        $model->downloads = 5;

        $admin = $this->createMock(\Model_Admin::class);

        $productService = $this->createMock(\Box\Mod\Product\Service::class);

        $this->di
            ->expects($this->once())
            ->method('offsetGet')
            ->with('mod_service')
            ->willReturn(function() use ($productService) { return $productService; });

        $result = $this->service->toApiArray($model, false, $admin);

        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('downloads', $result);
        $this->assertEquals('test.txt', $result['filename']);
        $this->assertEquals(5, $result['downloads']);
    }

    public function testToApiArrayNonAdmin(): void
    {
        $model = $this->createMock(\Model_ServiceDownloadable::class);
        $model->filename = 'test.txt';
        $model->downloads = 5;

        $client = $this->createMock(\Model_Client::class);

        $productService = $this->createMock(\Box\Mod\Product\Service::class);

        $this->di
            ->expects($this->once())
            ->method('offsetGet')
            ->with('mod_service')
            ->willReturn(function() use ($productService) { return $productService; });

        $result = $this->service->toApiArray($model, false, $client);

        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayNotHasKey('downloads', $result);
        $this->assertEquals('test.txt', $result['filename']);
    }

    public function testSendProductFileSuccess(): void
    {
        // Skip this test if in testing environment
        if (Environment::isTesting()) {
            $this->markTestSkipped('File sending is disabled in testing environment');
        }

        $product = $this->createMock(\Model_Product::class);
        $product->config = '{"filename": "test.txt"}';
        $product->id = 1;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Downloaded product %s file by admin', 1);

        $this->di
            ->expects($this->once())
            ->method('offsetGet')
            ->with('logger')
            ->willReturn($this->logger);

        // Mock filesystem to return true for file existence
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $result = $this->service->sendProductFile($product);
        $this->assertTrue($result);
    }

    public function testSendProductFileNoConfig(): void
    {
        $product = $this->createMock(\Model_Product::class);
        $product->config = '{}';

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('No file associated with this product');

        $this->service->sendProductFile($product);
    }

    public function testSendProductFileFileNotFound(): void
    {
        $product = $this->createMock(\Model_Product::class);
        $product->config = '{"filename": "nonexistent.txt"}';

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('File cannot be downloaded at the moment. Please contact support.');

        $this->service->sendProductFile($product);
    }

    public function testSaveProductConfig(): void
    {
        $product = $this->createMock(\Model_Product::class);
        $product->config = '{"filename": "test.txt"}';

        $this->db
            ->expects($this->once())
            ->method('store')
            ->with($product);

        $this->di
            ->expects($this->once())
            ->method('offsetGet')
            ->with('db')
            ->willReturn($this->db);

        $data = ['update_orders' => true];
        $result = $this->service->saveProductConfig($product, $data);

        $this->assertTrue($result);
        
        $config = json_decode($product->config, true);
        $this->assertTrue($config['update_orders']);
    }

    /**
     * Test that various action methods return expected values
     */
    public function testActionMethods(): void
    {
        $order = $this->createMock(\Model_ClientOrder::class);

        $this->assertTrue($this->service->action_activate($order));
        $this->assertTrue($this->service->action_renew($order));
        $this->assertTrue($this->service->action_suspend($order));
        $this->assertTrue($this->service->action_unsuspend($order));
        $this->assertTrue($this->service->action_cancel($order));
        $this->assertTrue($this->service->action_uncancel($order));
    }
} 