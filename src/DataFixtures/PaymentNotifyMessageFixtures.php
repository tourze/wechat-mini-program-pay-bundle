<?php

namespace WechatMiniProgramPayBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use WechatMiniProgramPayBundle\Entity\PaymentNotifyMessage;

#[When(env: 'test')]
#[When(env: 'dev')]
class PaymentNotifyMessageFixtures extends Fixture
{
    public const PAYMENT_SUCCESS_MESSAGE = 'payment-notify-success';
    public const PAYMENT_FAILED_MESSAGE = 'payment-notify-failed';
    public const PAYMENT_PENDING_MESSAGE = 'payment-notify-pending';

    public function load(ObjectManager $manager): void
    {
        // 创建支付成功通知消息
        $successMessage = new PaymentNotifyMessage();
        $successMessage->setRawData('{"transaction_id":"wx123456","out_trade_no":"ORD20240127001","trade_state":"SUCCESS","total_fee":"29990","cash_fee":"29990"}');
        $successMessage->setCreateTime(new \DateTimeImmutable('-1 hour'));
        $manager->persist($successMessage);

        // 创建支付失败通知消息
        $failedMessage = new PaymentNotifyMessage();
        $failedMessage->setRawData('{"transaction_id":"wx789012","out_trade_no":"ORD20240127002","trade_state":"PAYERROR","err_code":"NOTENOUGH","err_code_des":"余额不足"}');
        $failedMessage->setCreateTime(new \DateTimeImmutable('-30 minutes'));
        $manager->persist($failedMessage);

        // 创建待处理支付通知消息
        $pendingMessage = new PaymentNotifyMessage();
        $pendingMessage->setRawData('{"transaction_id":"wx345678","out_trade_no":"ORD20240127003","trade_state":"USERPAYING"}');
        $pendingMessage->setCreateTime(new \DateTimeImmutable('-5 minutes'));
        $manager->persist($pendingMessage);

        $manager->flush();

        // 添加引用供其他 Fixture 使用
        $this->addReference(self::PAYMENT_SUCCESS_MESSAGE, $successMessage);
        $this->addReference(self::PAYMENT_FAILED_MESSAGE, $failedMessage);
        $this->addReference(self::PAYMENT_PENDING_MESSAGE, $pendingMessage);
    }
}
