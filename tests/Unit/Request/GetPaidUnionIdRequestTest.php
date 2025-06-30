<?php

namespace WechatMiniProgramPayBundle\Tests\Unit\Request;

use PHPUnit\Framework\TestCase;
use WechatMiniProgramPayBundle\Request\GetPaidUnionIdRequest;

class GetPaidUnionIdRequestTest extends TestCase
{
    public function testGetPaidUnionIdRequest(): void
    {
        $request = new GetPaidUnionIdRequest();
        
        $this->assertInstanceOf(GetPaidUnionIdRequest::class, $request);
    }
}