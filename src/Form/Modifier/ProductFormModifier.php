<?php

namespace DpdConnect\Form\Modifier;

use Doctrine\ORM\EntityManagerInterface;
use DpdConnect\Entity\ProductShippingInformation;
use DpdConnect\Form\Type\DpdType;
use DpdConnect\Service\FreshFreezeService;
use PrestaShopBundle\Form\FormBuilderModifier;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

// https://devdocs.prestashop-project.org/8/modules/sample-modules/extend-product-page/
class ProductFormModifier
{
    /**
     * @var FormBuilderModifier
     */
    private $formBuilderModifier;

    private $productShippingInformationRepository;

    /**
     * @param FormBuilderModifier $formBuilderModifier
     */
    public function __construct(
        FormBuilderModifier $formBuilderModifier
    ) {
        $this->formBuilderModifier = $formBuilderModifier;

        $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->productShippingInformationRepository = $entityManager->getRepository(ProductShippingInformation::class);
    }

    /**
     * @param int|null $productId
     * @param FormBuilderInterface $productFormBuilder
     */
    public function modify(
        int $productId,
        FormBuilderInterface $productFormBuilder
    ): void {
        $productShippingInformation = $this->productShippingInformationRepository->findOneBy([
            'productId' => $productId,
        ]);

        $dpdShippingProduct = null;
        $dpdCarrierDescription = null;
        if (null !== $productShippingInformation) {
            $dpdShippingProduct = $productShippingInformation->getDpdShippingProduct();
            $dpdCarrierDescription = $productShippingInformation->getDpdCarrierDescription();
        }

        $this->formBuilderModifier->addAfter(
            $productFormBuilder,
            'shipping',
            'dpd',
            DpdType::class,
            [
                'data' => [
                    'dpd_shipping_product' => $dpdShippingProduct ?? FreshFreezeService::DEFAULT_SHIPPING_TYPE,
                    'dpd_carrier_description' => $dpdCarrierDescription ?? '',
                ],
            ]
        );
    }
}
