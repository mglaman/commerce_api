<?php

declare(strict_types=1);

namespace Drupal\commerce_api\Resource;

use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeAttribute;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Symfony\Component\HttpFoundation\Request;

final class CheckoutResource extends ResourceBase
{

    /**
     * Process the resource request.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request.
     * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
     *   The deserialized request document.
     */
    public function process(Request $request, JsonApiDocumentTopLevel $document)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getRouteResourceTypes(Route $route, string $route_name): array
    {
        $fields = [];
        $fields['email'] = new ResourceTypeAttribute('email');
        $fields['billing_information'] = new ResourceTypeAttribute('billing_information', NULL, TRUE, FALSE);
        $fields['shipping_information'] = new ResourceTypeAttribute('shipping_information', NULL, TRUE, FALSE);
        $fields['payment_instrument'] = new ResourceTypeAttribute('payment_instrument', NULL, TRUE, FALSE);

        $resource_type = new ResourceType(
            'checkout_order',
            'checkout_order',
            NULL,
            FALSE,
            FALSE,
            TRUE,
            FALSE,
            $fields
        );
        $resource_type->setRelatableResourceTypes([]);
        return [$$resource_type];
    }
}
