<?php

namespace Database\Seeders\Clients;

/**
 * Kanozay Foods Manufacturing: the real opening data from the client's Excel files.
 * Only for this client; another company does not need it.
 *
 *   php artisan db:seed                                                     (first, the general records)
 *   php artisan db:seed --class="Database\Seeders\Clients\KanozaySeeder"    (then this)
 *
 * Needs database/seeders/Clients/data/kanozay.php (and kanozay-logo.jpg), which are not in the repository.
 */
class KanozaySeeder extends ClientSeeder
{
    protected function client(): string
    {
        return 'kanozay';
    }
}
