# WechatMiniProgramPayBundle

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Build Status](https://img.shields.io/badge/build-passing-brightgreen.svg)](#)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](#)

[English](README.md) | [中文](README.zh-CN.md)

一个用于微信小程序支付集成的 Symfony Bundle，提供完整的支付功能，
包括交易创建、回调处理和退款操作，以及完整的 EasyAdmin 后台管理。

## 功能特性

- 🔄 完整的微信小程序支付流程
- 📱 支持合并支付和单笔支付
- 🔔 支付回调消息处理
- 💰 退款操作支持
- 📊 **EasyAdmin 后台管理界面**
- 🔍 支付回调消息监控和调试
- 🧹 过期数据自动清理
- 🛡️ 完整的安全和权限控制

## EasyAdmin 后台管理

本 Bundle 提供了专业的 EasyAdmin 后台管理功能：

### 核心功能

- **支付回调消息管理**: 查看、监控所有微信支付回调消息
- **数据可视化**: JSON数据语法高亮显示，支持格式化
- **智能搜索**: 支持ID和原始数据内容搜索
- **时间筛选**: 按回调消息接收时间筛选
- **自定义操作**: 
  - 一键格式化JSON数据
  - 批量清理过期数据（30天前）
- **安全设计**: 只读模式，防止误操作

### 访问方式

后台管理界面位于：`/admin/wechat-mini-program-pay/payment-notify-message`

菜单路径：**支付管理** → **微信小程序支付** → **支付回调消息**

### 详细文档

更多后台管理功能请参考：[EasyAdmin 管理指南](docs/easyadmin-guide.md)

## 目录

- [快速开始](#快速开始)
  - [1. Bundle 注册](#1-bundle-注册)
  - [2. 基本使用](#2-基本使用)
- [EasyAdmin 后台管理](#easyadmin-后台管理)
- [功能特性](#功能特性)
- [可用的 Procedure](#可用的-procedure)
- [事件](#事件)
- [安装](#安装)
- [配置](#配置)
- [高级用法](#高级用法)
- [安全性](#安全性)
- [依赖项](#依赖项)
- [许可证](#许可证)

## 快速开始

### 1. Bundle 注册

将 Bundle 添加到您的 `config/bundles.php`：

```php
<?php
return [
    // ... 其他 bundles
    WechatMiniProgramPayBundle\WechatMiniProgramPayBundle::class => ['all' => true],
];
```

### 2. 基本使用

#### 创建支付交易

```php
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramMakePayTransaction;

// 创建支付交易
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
$payTransaction->money = 100; // 金额（分）
$payTransaction->description = '商品购买';
$payTransaction->attach = '自定义数据';

$result = $payTransaction->execute();
```

#### 处理支付回调

Bundle 通过控制器提供自动回调处理：

- `PayCallbackController` - 处理支付成功/失败通知
- `RefundCallbackController` - 处理退款通知
- `CombinePayCallbackController` - 处理合并支付通知

#### 创建退款交易

```php
use WechatMiniProgramPayBundle\Procedure\WechatMiniProgramMakeRefundTransaction;

$refundTransaction = new WechatMiniProgramMakeRefundTransaction(
    // ... 依赖项
);

$refundTransaction->outTradeNo = '原始订单号';
$refundTransaction->refundFee = 50; // 退款金额（分）
$refundTransaction->reason = '客户要求';

$result = $refundTransaction->execute();
```

## 功能特性

- **支付交易**: 为微信小程序创建 JSAPI 支付订单
- **合并支付**: 支持合并支付交易
- **退款处理**: 处理退款请求并进行适当验证
- **回调处理**: 自动处理微信支付回调
- **事件系统**: 为支付成功/失败分发事件
- **Union ID 支持**: 支付成功后自动获取 Union ID
- **安全性**: 内置身份验证和授权检查

## 可用的 Procedure

- `WechatMiniProgramMakePayTransaction` - 创建支付交易
- `WechatMiniProgramMakeCombinePayTransaction` - 创建合并支付交易
- `WechatMiniProgramMakeRefundTransaction` - 处理退款
- `WechatMiniProgramGetPayConfig` - 获取支付配置

## 事件

- `PayCallbackSuccessEvent` - 支付成功时分发
- `PayCallbackFailedEvent` - 支付失败时分发

## 安装

```bash
composer require tourze/wechat-mini-program-pay-bundle
```

## 配置

### 环境变量

在您的 `.env` 文件中配置以下环境变量：

```env
## 微信应用配置
WECHAT_APP_ID=your_app_id
WECHAT_APP_SECRET=your_app_secret

## 微信支付配置
WECHAT_MCH_ID=your_merchant_id
WECHAT_MCH_KEY=your_merchant_key
WECHAT_CERT_PATH=/path/to/cert.pem
WECHAT_KEY_PATH=/path/to/key.pem
```

### Bundle 配置

创建 `config/packages/wechat_mini_program_pay.yaml`：

```yaml
wechat_mini_program_pay:
    app_id: '%env(WECHAT_APP_ID)%'
    mch_id: '%env(WECHAT_MCH_ID)%'
    notify_url: '%env(WECHAT_NOTIFY_URL)%'
```

## 高级用法

### 自定义事件监听器

监听支付事件以实现自定义业务逻辑：

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
        // 支付成功后的自定义逻辑
        $payOrder = $event->getPayOrder();
        // ...
    }
}
```

### 错误处理

正确处理微信支付 API 错误：

```php
use Tourze\JsonRPC\Core\Exception\ApiException;

try {
    $result = $payTransaction->execute();
} catch (ApiException $e) {
    // 处理支付错误
    $errorMessage = $e->getMessage();
    // 记录或处理错误
}
```

## 安全性

### 身份验证要求

所有支付程序都需要用户身份验证：

- 用户必须登录 (`IS_AUTHENTICATED_FULLY`)
- 支付回调使用微信签名验证
- 所有敏感数据在传输过程中都被加密

### 最佳实践

- 始终在服务器端验证支付金额
- 实现适当的错误处理和日志记录
- 为所有支付相关端点使用 HTTPS
- 定期更新您的微信证书

## 依赖项

此 Bundle 需要：

- `symfony/framework-bundle` ^7.3
- `symfony/security-bundle` ^7.3
- `doctrine/orm` ^3.0
- `tourze/wechat-mini-program-bundle` ^0.1.*
- `tourze/wechat-pay-bundle` ^0.0.*
- `tourze/json-rpc-core` ^0.0.*

开发依赖：

- `phpunit/phpunit` ^11.5
- `phpstan/phpstan` ^2.1
- `league/flysystem` ^3.10

## 许可证

此 Bundle 基于 MIT 许可证发布。
