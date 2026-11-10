<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Enums;

/**
 * Context of the product type list: the checkout paywall or the product page widget.
 */
enum ProductListType: string
{
    case Paywall = 'paywall';
    case Widget = 'widget';
}
