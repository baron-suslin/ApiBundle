<?php

namespace Requestum\ApiBundle\Serializer\Normalizer;

use Requestum\ApiBundle\Rest\ReferenceWrapper;
use Requestum\ApiBundle\Rest\Metadata\Access;
use Doctrine\ORM\Proxy\Proxy;
use Requestum\ApiBundle\Rest\ResourceMetadataFactory;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer as BaseObjectNormalizer;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

/**
 * Class ObjectNormalizer
 *
 * Custom Object Normalizer for Symfony Serializer
 *
 * @package Requestum\ApiBundle\Serializer\Normalizer
 */
class ObjectNormalizer extends BaseObjectNormalizer
{

    /**
     * @var AttributeExtractionStrategy
     */
    protected $attributeExtractionStrategy;

    /**
     * @var AuthorizationChecker
     */
    protected $authorizationChecker;

    /**
     * @var ResourceMetadataFactory
     */
    protected $resourceMetadataFactory;

    /**
     * @param AttributeExtractionStrategy $attributeExtractionStrategy
     *
     * @return $this
     */
    public function setAttributeExtractionStrategy(AttributeExtractionStrategy $attributeExtractionStrategy)
    {
        $this->attributeExtractionStrategy = $attributeExtractionStrategy;

        return $this;
    }

    /**
     * @param AuthorizationChecker $authorizationChecker
     */
    public function setAuthorizationChecker(AuthorizationChecker $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }


    /**
     * @param ResourceMetadataFactory $resourceMetadataFactory
     */
    public function setResourceMetadataFactory(ResourceMetadataFactory $resourceMetadataFactory)
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    /**
     * @param string|object $object
     * @param string        $attribute
     * @param null|string   $format
     * @param array         $context
     *
     * @return mixed
     */
    protected function getAttributeValue($object, $attribute, $format = null, array $context = array())
    {
        return $this->attributeExtractionStrategy->getValue($object, $attribute, $format, $context);
    }

    /**
     * @param object|string $classOrObject
     * @param string        $attribute
     * @param null          $format
     * @param array         $context
     *
     * @return bool
     */
    protected function isAllowedAttribute($classOrObject, $attribute, $format = null, array $context = array())
    {
        return parent::isAllowedAttribute($classOrObject, $attribute, $format, $context) &&
            (
                !(isset($context['serialization_check_access']) && $context['serialization_check_access']) ||
                $this->checkAccess($classOrObject, $attribute, $context)
            );
    }

    /**
     * @param $classOrObject
     * @param $attribute
     * @param $context
     *
     * @return bool
     */
    protected function checkAccess($classOrObject, $attribute, $context)
    {
        if ($access = $this->resourceMetadataFactory->getPropertyMetadata($classOrObject, $attribute, Access::class)) {
            return $this->authorizationChecker->isGranted($access->value, $context['object']);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if ($object instanceof Proxy) {
            $object->__load();
        }

        $context['object'] = $object;

        $current = null;
        if ($object instanceof ReferenceWrapper) {
            $current = $object->getAttribute();
            $context['path'] = isset($context['path']) ? $context['path'] . '.' . $current : $current;

            return $this->serializer->normalize($object->getObject(), $format, $context);
        }

        return parent::normalize($object, $format, $context);
    }
}