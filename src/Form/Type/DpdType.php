<?php

namespace DpdConnect\Form\Type;

use DpdConnect\classes\FreshFreezeHelper;
use DpdConnect\Service\FreshFreezeService;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

// Form type for the DPD tab in the product page
class DpdType extends TranslatorAwareType
{
    private FreshFreezeService $freshFreezeService;

    /**
     * @param TranslatorInterface $translator
     * @param array $locales
     */
    public function __construct(
        TranslatorInterface $translator,
        array $locales
    ) {
        parent::__construct($translator, $locales);

        $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
        $this->freshFreezeService = $container->get('dpdconnect.fresh_freeze_service');
    }

    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        // Get the data

        $builder
            ->add('dpd_shipping_product', ChoiceType::class, [
                'label' => $this->trans('Shipping type', 'Modules.DpdConnect.Admin'),
                'label_tag_name' => 'h3',
                'required' => true,
                'choices' => $this->freshFreezeService->getAllowedShippingTypes(),
                // No parameter type hints here: `mixed` is PHP 8.0+ and PHP 7.4
                // (PrestaShop 1.7) resolves it as a class name, which makes this
                // closure throw a TypeError as soon as the choice list renders.
                'choice_label' => function ($choice, $key, $value): string {
                    return ucfirst($choice);
                },
                'placeholder' => $this->trans('Select a shipping type', 'Modules.DpdConnect.Admin'),
                'constraints' => [
                    new NotBlank(),
                    new Type(['type' => 'string']),
                ],
            ])
            ->add('dpd_carrier_description', TextareaType::class, [
                'label' => $this->trans('Carrier description', 'Modules.DpdConnect.Admin'),
                'label_subtitle' => $this->trans('This description will be shown to the carrier', 'Modules.DpdConnect.Admin'),
                'label_tag_name' => 'h3',
                'required' => false,
                'constraints' => [
                    new Type(['type' => 'string']),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'label' => $this->trans('DPD', 'Modules.DpdConnect.Admin'),
            ]);
    }
}
