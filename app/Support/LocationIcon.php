<?php

namespace App\Support;

final class LocationIcon
{
    /**
     * A location point's marker icon, if it has a usable one.
     *
     * Only URLs count: rows imported by the old owntracks_import.php script
     * have the point's altitude in the icon column.
     */
    public static function url(mixed $icon): ?string
    {
        return is_string($icon) && preg_match('#^https?://#', $icon) ? $icon : null;
    }
}
