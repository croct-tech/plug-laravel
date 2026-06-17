<?php

declare(strict_types=1);

namespace Croct\Plug\Laravel\Tests\Fixtures;

use Croct\Plug\Plug;

final class FakeCroctStoriesApi
{
    public readonly object $inner;

    public readonly Plug $plug;

    public function __construct(object $inner, Plug $plug)
    {
        $this->inner = $inner;
        $this->plug = $plug;
    }
}
