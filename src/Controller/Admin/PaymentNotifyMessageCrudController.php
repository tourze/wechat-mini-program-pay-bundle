<?php

declare(strict_types=1);

namespace WechatMiniProgramPayBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WechatMiniProgramPayBundle\Entity\PaymentNotifyMessage;
use WechatMiniProgramPayBundle\Repository\PaymentNotifyMessageRepository;

/**
 * 微信小程序支付回调消息管理控制器.
 *
 * @extends AbstractCrudController<PaymentNotifyMessage>
 */
#[AdminCrud(routePath: '/wechat-mini-program-pay/payment-notify-message', routeName: 'wechat_mini_program_pay_payment_notify_message')]
final class PaymentNotifyMessageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentNotifyMessageRepository $paymentNotifyMessageRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PaymentNotifyMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('支付回调消息')
            ->setEntityLabelInPlural('支付回调消息管理')
            ->setPageTitle('index', '支付回调消息管理')
            ->setPageTitle('detail', '支付回调消息详情')
            ->setPageTitle('new', '新增支付回调消息')
            ->setPageTitle('edit', '编辑支付回调消息')
            ->setHelp('index', '管理微信小程序支付回调消息，用于监控和调试支付流程')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'rawData'])
            ->setPaginatorPageSize(20)
            // 禁用新增和编辑操作，因为这些是系统自动生成的记录
            ->showEntityActionsInlined()
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        // ID字段
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->setHelp('支付回调消息的唯一标识符')
        ;

        // 原始数据字段
        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_EDIT === $pageName) {
            yield CodeEditorField::new('rawData', '原始数据')
                ->setLanguage('javascript')
                ->setNumOfRows(20)
                ->setHelp('微信支付回调的原始JSON数据')
                ->formatValue(function (mixed $value): string {
                    assert(is_string($value) || null === $value);

                    return $this->formatJsonData($value);
                })
            ;
        } else {
            yield TextareaField::new('rawData', '原始数据')
                ->setMaxLength(100)
                ->setHelp('微信支付回调的原始数据预览')
                ->formatValue(function (mixed $value): string {
                    assert(is_string($value) || null === $value);

                    return $this->formatDataPreview($value);
                })
            ;
        }

        // 创建时间字段
        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('回调消息接收时间')
            ->hideOnForm()
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // 格式化数据操作
        $formatDataAction = Action::new('formatData', '格式化数据')
            ->linkToCrudAction('formatData')
            ->setCssClass('btn btn-sm btn-info')
            ->setIcon('fa fa-code')
            ->displayIf(function (PaymentNotifyMessage $message) {
                return null !== $message->getRawData() && '' !== $message->getRawData();
            })
        ;

        // 清理过期数据操作
        $cleanupAction = Action::new('cleanup', '清理过期数据')
            ->linkToCrudAction('cleanup')
            ->setCssClass('btn btn-sm btn-warning')
            ->setIcon('fa fa-broom')
            ->createAsGlobalAction()
        ;

        // 添加详情操作
        $actions->add(Crud::PAGE_INDEX, Action::DETAIL);

        // 添加自定义操作
        $actions->add(Crud::PAGE_INDEX, $formatDataAction);
        $actions->add(Crud::PAGE_DETAIL, $formatDataAction);
        $actions->add(Crud::PAGE_INDEX, $cleanupAction);

        // EasyAdmin默认只有INDEX和DETAIL动作，不需要移除不存在的动作

        // 重新排序操作按钮
        $actions->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'formatData']);

        return $actions;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            // 原始数据筛选
            ->add(TextFilter::new('rawData', '原始数据'))

            // 创建时间筛选
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    /**
     * 格式化JSON数据操作.
     */
    #[AdminAction(routePath: '{entityId}/formatData', routeName: 'format_data')]
    public function formatData(AdminContext $context, Request $request): Response
    {
        $message = $context->getEntity()->getInstance();
        assert($message instanceof PaymentNotifyMessage);

        $rawData = $message->getRawData();
        if (null === $rawData || '' === $rawData) {
            $this->addFlash('warning', '该消息没有原始数据');

            return $this->redirect($context->getRequest()->headers->get('referer') ?? '/');
        }

        // 尝试格式化JSON数据
        $decodedData = json_decode($rawData, true);
        if (JSON_ERROR_NONE === json_last_error()) {
            $formattedData = json_encode($decodedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (false !== $formattedData) {
                $message->setRawData($formattedData);
            }

            $this->entityManager->flush();

            $this->addFlash('success', '数据格式化完成');
        } else {
            $this->addFlash('danger', '数据格式化失败：不是有效的JSON格式');
        }

        return $this->redirect($context->getRequest()->headers->get('referer') ?? '/');
    }

    /**
     * 清理过期数据操作（删除30天前的记录）.
     */
    #[AdminAction(routePath: 'cleanup', routeName: 'cleanup_expired_data')]
    public function cleanup(AdminContext $context, Request $request): Response
    {
        // 删除30天前的记录
        $cutoffDate = new \DateTimeImmutable('-30 days');

        $qb = $this->paymentNotifyMessageRepository->createQueryBuilder('m');
        $qb->delete()
            ->where('m.createTime < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
        ;

        $deletedCount = $qb->getQuery()->execute();
        assert(is_int($deletedCount));

        $this->addFlash('success', sprintf('已清理 %d 条过期的支付回调消息记录', $deletedCount));

        return $this->redirect($context->getRequest()->headers->get('referer') ?? '/');
    }

    /**
     * 自定义查询构建器，优化查询性能.
     */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->orderBy('entity.id', 'DESC')
        ;
    }

    /**
     * 格式化JSON数据显示.
     */
    private function formatJsonData(?string $data): string
    {
        if (null === $data || '' === $data) {
            return '';
        }

        $decoded = json_decode($data, true);
        if (JSON_ERROR_NONE === json_last_error()) {
            $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return false !== $encoded ? $encoded : $data;
        }

        return $data;
    }

    /**
     * 格式化数据预览（显示前100个字符）.
     */
    private function formatDataPreview(?string $data): string
    {
        if (null === $data || '' === $data) {
            return '-';
        }

        $preview = mb_substr($data, 0, 100);
        if (mb_strlen($data) > 100) {
            $preview .= '...';
        }

        // 移除换行符，使预览更整洁
        return str_replace(["\r", "\n"], ' ', $preview);
    }
}
