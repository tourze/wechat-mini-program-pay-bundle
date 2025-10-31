<?php

namespace WechatMiniProgramPayBundle\Tests\Request;

use HttpClientBundle\Tests\Request\RequestTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use WechatMiniProgramPayBundle\Request\GetPaidUnionIdRequest;

/**
 * @internal
 */
#[CoversClass(GetPaidUnionIdRequest::class)]
final class GetPaidUnionIdRequestTest extends RequestTestCase
{
    public function testGetPaidUnionIdRequest(): void
    {
        $request = new GetPaidUnionIdRequest();

        $this->assertInstanceOf(GetPaidUnionIdRequest::class, $request);
    }
}
