<?php

namespace Omnibridge\Symfony\Tests\Fixtures\DeferredAliases\Listed;

// Not listed itself: loading it loads Shape and Drawable, which are.
class Circle extends Shape
{
}
