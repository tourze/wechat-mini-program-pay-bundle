# WechatMiniProgramPayBundle

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Build Status](https://img.shields.io/badge/build-passing-brightgreen.svg)](#)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](#)

[English](README.md) | [中文](README.zh-CN.md)

A Symfony bundle for WeChat Mini Program payment integration, providing complete
payment functionality including transaction creation, callback handling, and refund operations.

## Table of Contents

- [Quick Start](#quick-start)
  - [1. Bundle Registration](#1-bundle-registration)
  - [2. Basic Usage](#2-basic-usage)
- [Features](#features)
- [Available Procedures](#available-procedures)
- [Events](#events)
- [Installation](#installation)
- [Configuration](#configuration)
- [Advanced Usage](#advanced-usage)
- [Security](#security)
- [Dependencies](#dependencies)
- [License](#license)

## Quick Start

### 1. Bundle Registration

Add the bundle to your `config/bundles.php`:

```php
<?php
return [
    // ... other bundles
    WechatMiniProgramPayBundle\WechatMiniProgramPayBundle::class => ['all' => true],
];
```

### 2. Basic Usage

#### Create Payment Transaction

```php
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramMakePayTransaction;

// Create a payment transaction
$payTransaction = new WechatMiniProgramMakePayTransaction(
    $accountService,
    $merchantRepository,
    $requestStack,
    $snowflake,
    $payConfigApi,
    $security,
    $entityManager
);

$payTransaction->appId = 'your_app_id';
$payTransaction->mchId = 'your_merchant_id';
$payTransaction->money = 100; // Amount in cents
$payTransaction->description = 'Product purchase';
$payTransaction->attach = 'custom_data';

$result = $payTransaction->execute();
```

#### Handle Payment Callbacks

The bundle provides automatic callback handling through controllers:

- `PayCallbackController` - Handles payment success/failure notifications
- `RefundCallbackController` - Handles refund notifications  
- `CombinePayCallbackController` - Handles combined payment notifications

#### Create Refund Transaction

```php
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramMakeRefundTransaction;

$refundTransaction = new WechatMiniProgramMakeRefundTransaction(
    // ... dependencies
);

$refundTransaction->outTradeNo = 'original_order_no';
$refundTransaction->refundFee = 50; // Refund amount in cents
$refundTransaction->reason = 'Customer request';

$result = $refundTransaction->execute();
```

## Features

- **Payment Transactions**: Create JSAPI payment orders for WeChat Mini Programs
- **Combine Payments**: Support for combined payment transactions
- **Refund Processing**: Handle refund requests with proper validation
- **Callback Handling**: Automatic processing of WeChat payment callbacks
- **Event System**: Dispatch events for payment success/failure
- **Union ID Support**: Automatic Union ID retrieval after successful payments
- **Security**: Built-in authentication and authorization checks

## Available Procedures

- `WechatMiniProgramMakePayTransaction` - Create payment transactions
- `WechatMiniProgramMakeCombinePayTransaction` - Create combined payment transactions  
- `WechatMiniProgramMakeRefundTransaction` - Process refunds
- `WechatMiniProgramGetPayConfig` - Get payment configuration

## Events

- `PayCallbackSuccessEvent` - Dispatched on successful payment
- `PayCallbackFailedEvent` - Dispatched on failed payment

## Installation

```bash
composer require tourze/wechat-mini-program-pay-bundle
```

## Configuration

### Environment Variables

Configure the following environment variables in your `.env` file:

```env
## WeChat App Configuration
WECHAT_APP_ID=your_app_id
WECHAT_APP_SECRET=your_app_secret

## WeChat Pay Configuration
WECHAT_MCH_ID=your_merchant_id
WECHAT_MCH_KEY=your_merchant_key
WECHAT_CERT_PATH=/path/to/cert.pem
WECHAT_KEY_PATH=/path/to/key.pem
```

### Bundle Configuration

Create `config/packages/wechat_mini_program_pay.yaml`:

```yaml
wechat_mini_program_pay:
    app_id: '%env(WECHAT_APP_ID)%'
    mch_id: '%env(WECHAT_MCH_ID)%'
    notify_url: '%env(WECHAT_NOTIFY_URL)%'
```

## Advanced Usage

### Custom Event Listeners

Listen to payment events for custom business logic:

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WechatMiniProgramPayBundle\Event\PayCallbackSuccessEvent;

class PaymentEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PayCallbackSuccessEvent::class => 'onPaymentSuccess',
        ];
    }

    public function onPaymentSuccess(PayCallbackSuccessEvent $event): void
    {
        // Custom logic after successful payment
        $payOrder = $event->getPayOrder();
        // ...
    }
}
```

### Error Handling

Handle WeChat Pay API errors properly:

```php
use Tourze\JsonRPC\Core\Exception\ApiException;

try {
    $result = $payTransaction->execute();
} catch (ApiException $e) {
    // Handle payment errors
    $errorMessage = $e->getMessage();
    // Log or handle the error
}
```

## Security

### Authentication Requirements

All payment procedures require user authentication:

- Users must be logged in (`IS_AUTHENTICATED_FULLY`)
- Payment callbacks use WeChat signature verification
- All sensitive data is encrypted in transit

### Best Practices

- Always validate payment amounts on the server side
- Implement proper error handling and logging
- Use HTTPS for all payment-related endpoints
- Regularly update your WeChat certificates

## Dependencies

This bundle requires:

- `symfony/framework-bundle` ^7.3
- `symfony/security-bundle` ^7.3
- `doctrine/orm` ^3.0
- `tourze/wechat-mini-program-bundle` ^0.1.*
- `tourze/wechat-pay-bundle` ^0.0.*
- `tourze/json-rpc-core` ^0.0.*

For development:

- `phpunit/phpunit` ^11.5
- `phpstan/phpstan` ^2.1
- `league/flysystem` ^3.10

## License

This bundle is released under the MIT License.
