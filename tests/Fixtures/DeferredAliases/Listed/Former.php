<?php

namespace Omnibridge\Symfony\Tests\Fixtures\DeferredAliases\Listed;

// A former name, as omnibase's moved classes keep theirs: the file declares the name as an alias of the class it became.
class_alias(\Omnibridge\Symfony\Tests\Fixtures\DeferredAliases\Moved\Current::class, __NAMESPACE__.'\\Former');
