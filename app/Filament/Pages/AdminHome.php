<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

class AdminHome extends Dashboard
{
    protected static ?string $title = 'Admin';

    protected string $view = 'filament.pages.admin-home';
}
