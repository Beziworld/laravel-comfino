<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Enums;

/**
 * Placement of the widget relative to the element matched by widgetTargetSelector.
 */
enum EmbedMethod: string
{
    case InsertIntoFirst = 'INSERT_INTO_FIRST';
    case InsertIntoLast = 'INSERT_INTO_LAST';
    case InsertBefore = 'INSERT_BEFORE';
    case InsertAfter = 'INSERT_AFTER';
}
