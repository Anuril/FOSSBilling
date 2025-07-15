<?php

namespace Box\Mod\Servicedownloadable\Controller;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $controller;
    protected $di;
    protected $app;

    public function setUp(): void
    {
        $this->controller = new Admin();
        $this->di = new \Pimple\Container();
        $this->app = $this->getMockBuilder('\Box_App')->getMock();
        
        $this->setupMockDependencies();
    }

    private function setupMockDependencies(): void
    {
        // Mock admin authentication check
        $this->di['is_admin_logged'] = function () {
            return true;
        };
        
        $this->controller->setDi($this->di);
    }

    public function testSetDi(): void
    {
        $di = new \Pimple\Container();
        $this->controller->setDi($di);
        $this->assertEquals($di, $this->controller->getDi());
    }

    public function testRegister(): void
    {
        $this->app->expects($this->once())
            ->method('get')
            ->with(
                '/servicedownloadable/get-file/:id',
                'get_download',
                ['id' => '[0-9]+'],
                Admin::class
            );

        $this->controller->register($this->app);
    }

    public function testGetDownloadSuccess(): void
    {
        $productId = 123;

        // Mock API admin
        $apiAdminMock = $this->getMockBuilder('\Api_Admin')->getMock();
        $apiAdminMock->expects($this->once())
            ->method('servicedownloadable_send_file')
            ->with(['id' => $productId]);

        $this->di['api_admin'] = $apiAdminMock;

        $this->controller->get_download($this->app, $productId);
    }

    public function testGetDownloadWithInvalidId(): void
    {
        $productId = 'invalid';

        // Mock API admin that will throw exception
        $apiAdminMock = $this->getMockBuilder('\Api_Admin')->getMock();
        $apiAdminMock->expects($this->once())
            ->method('servicedownloadable_send_file')
            ->with(['id' => $productId])
            ->willThrowException(new \FOSSBilling\Exception('Product ID is missing'));

        $this->di['api_admin'] = $apiAdminMock;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product ID is missing');

        $this->controller->get_download($this->app, $productId);
    }

    public function testGetDownloadProductNotFound(): void
    {
        $productId = 999;

        // Mock API admin that will throw exception
        $apiAdminMock = $this->getMockBuilder('\Api_Admin')->getMock();
        $apiAdminMock->expects($this->once())
            ->method('servicedownloadable_send_file')
            ->with(['id' => $productId])
            ->willThrowException(new \FOSSBilling\Exception('Product not found'));

        $this->di['api_admin'] = $apiAdminMock;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product not found');

        $this->controller->get_download($this->app, $productId);
    }

    public function testGetDownloadNoFileAssociated(): void
    {
        $productId = 123;

        // Mock API admin that will throw exception for no file
        $apiAdminMock = $this->getMockBuilder('\Api_Admin')->getMock();
        $apiAdminMock->expects($this->once())
            ->method('servicedownloadable_send_file')
            ->with(['id' => $productId])
            ->willThrowException(new \FOSSBilling\Exception('No file associated with this product'));

        $this->di['api_admin'] = $apiAdminMock;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('No file associated with this product');

        $this->controller->get_download($this->app, $productId);
    }

    public function testGetDownloadFileNotFound(): void
    {
        $productId = 123;

        // Mock API admin that will throw exception for file not found
        $apiAdminMock = $this->getMockBuilder('\Api_Admin')->getMock();
        $apiAdminMock->expects($this->once())
            ->method('servicedownloadable_send_file')
            ->with(['id' => $productId])
            ->willThrowException(new \FOSSBilling\Exception('File cannot be downloaded at the moment. Please contact support.', null, 404));

        $this->di['api_admin'] = $apiAdminMock;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('File cannot be downloaded at the moment. Please contact support.');

        $this->controller->get_download($this->app, $productId);
    }

    public function testGetDownloadWithZeroId(): void
    {
        $productId = 0;

        // Mock API admin that will throw exception
        $apiAdminMock = $this->getMockBuilder('\Api_Admin')->getMock();
        $apiAdminMock->expects($this->once())
            ->method('servicedownloadable_send_file')
            ->with(['id' => $productId])
            ->willThrowException(new \FOSSBilling\Exception('Product not found'));

        $this->di['api_admin'] = $apiAdminMock;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product not found');

        $this->controller->get_download($this->app, $productId);
    }

    public function testGetDownloadWithNegativeId(): void
    {
        $productId = -1;

        // Mock API admin that will throw exception
        $apiAdminMock = $this->getMockBuilder('\Api_Admin')->getMock();
        $apiAdminMock->expects($this->once())
            ->method('servicedownloadable_send_file')
            ->with(['id' => $productId])
            ->willThrowException(new \FOSSBilling\Exception('Product not found'));

        $this->di['api_admin'] = $apiAdminMock;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product not found');

        $this->controller->get_download($this->app, $productId);
    }

    /**
     * Test authentication requirement
     */
    public function testGetDownloadRequiresAuthentication(): void
    {
        // Override the mock to simulate unauthenticated user
        $this->di['is_admin_logged'] = function () {
            throw new \FOSSBilling\Exception('Authentication required');
        };

        $productId = 123;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Authentication required');

        $this->controller->get_download($this->app, $productId);
    }

    public function testGetDownloadWithLargeId(): void
    {
        $productId = 999999999;

        // Mock API admin that will throw exception
        $apiAdminMock = $this->getMockBuilder('\Api_Admin')->getMock();
        $apiAdminMock->expects($this->once())
            ->method('servicedownloadable_send_file')
            ->with(['id' => $productId])
            ->willThrowException(new \FOSSBilling\Exception('Product not found'));

        $this->di['api_admin'] = $apiAdminMock;

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Product not found');

        $this->controller->get_download($this->app, $productId);
    }
} 