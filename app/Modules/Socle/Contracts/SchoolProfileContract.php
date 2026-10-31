<?php

declare(strict_types=1);

namespace App\Modules\Socle\Contracts;

use App\Modules\Socle\Data\SchoolProfileDTO;

/** Public read model for the single Ambassadors establishment. */
interface SchoolProfileContract
{
    public function profile(): SchoolProfileDTO;
}
