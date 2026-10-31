<?php

namespace App\Modules\Pedagogie\Contracts;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class ReportCardPublished implements ShouldDispatchAfterCommit
{
    public function __construct(public int $bulletinId) {}
}
