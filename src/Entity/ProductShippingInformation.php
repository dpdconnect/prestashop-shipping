<?php

namespace DpdConnect\Entity;

use Doctrine\ORM\Mapping as ORM;
use DpdConnect\Sdk\Resources\Product;

/**
 * @ORM\Table()
 * @ORM\Entity()
 */
class ProductShippingInformation
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\OneToOne(targetEntity="Product")
     * @ORM\JoinColumn(name="product_id", referencedColumnName="id")
     * @ORM\Column(type="integer")
     */
    private $productId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     */
    private $dpdShippingProduct;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     */
    private $dpdCarrierDescription;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $product
     */
    public function getProductId()
    {
        return $this->productId;
    }

    /**
     * @param int $product
     */
    public function setProductId($productId)
    {
        $this->productId = $productId;
    }

    /**
     * @return string
     */
    public function getDpdShippingProduct()
    {
        return $this->dpdShippingProduct;
    }

    /**
     * @param string $dpdShippingProduct
     */
    public function setDpdShippingProduct($dpdShippingProduct)
    {
        $this->dpdShippingProduct = $dpdShippingProduct;
    }

    /**
     * @return string
     */
    public function getDpdCarrierDescription()
    {
        return $this->dpdCarrierDescription;
    }

    /**
     * @param string $dpdCarrierDescription
     */
    public function setDpdCarrierDescription($dpdCarrierDescription)
    {
        $this->dpdCarrierDescription = $dpdCarrierDescription;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'product_id' => $this->getProductId(),
            'dpd_shipping_product' => $this->getDpdShippingProduct(),
            'dpd_carrier_description' => $this->getDpdCarrierDescription(),
        ];
    }
}
